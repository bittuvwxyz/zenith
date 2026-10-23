<?php
/**
 * Authentication & Account Security System
 *
 * Implements login, logout, registration, email verification, password reset,
 * session security, rate-limiting / throttling, and single-user protection.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/outbox.php';

/**
 * Checks if the system has at least one registered user.
 *
 * @return bool
 */
function hasRegisteredUsers(): bool
{
    $db = getDb();
    $result = $db->query('SELECT COUNT(*) FROM users');
    if ($result) {
        $count = (int) $result->fetch_row()[0];
        return $count > 0;
    }
    return false;
}

/**
 * Determines whether registration is currently permitted.
 *
 * @return bool
 */
function isRegistrationAllowed(): bool
{
    if (defined('CLI_TEST_MODE') && CLI_TEST_MODE) {
        return true;
    }
    if (SINGLE_USER_MODE && hasRegisteredUsers()) {
        return false;
    }
    return true;
}

/**
 * Returns the currently authenticated user's data from database, or null.
 *
 * @return array|null
 */
function getCurrentUser(): ?array
{
    initAppSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && (int) $cachedUser['id'] === (int) $_SESSION['user_id']) {
        return $cachedUser;
    }

    $db = getDb();
    $stmt = $db->prepare('SELECT id, username, email, verified, timezone, avatar, bio, created_at FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $userId = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $cachedUser = $user;
        return $user;
    }

    // User was deleted from DB but session exists
    logoutUser();
    return null;
}

/**
 * Enforces that a user is logged in. Redirects to login if not.
 *
 * @return array The authenticated user record.
 */
function requireAuth(): array
{
    $user = getCurrentUser();
    if (!$user) {
        if (isAjax()) {
            jsonResponse(['success' => false, 'error' => 'Authentication required.'], 401);
        }
        setFlash('warning', 'Please sign in to access your dashboard.');
        redirect('login.php');
    }
    return $user;
}

/**
 * Enforces that no user is logged in (for login, register, reset pages).
 */
function requireGuest(): void
{
    if (getCurrentUser()) {
        redirect('dashboard.php');
    }
}

/**
 * Checks if an email address or IP is currently locked out from login.
 *
 * @param string $email
 * @return array ['locked' => bool, 'minutes_left' => int]
 */
function checkLoginThrottling(string $email): array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT failed_login_attempts, lockout_until FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return ['locked' => false, 'minutes_left' => 0];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['lockout_until'])) {
        return ['locked' => false, 'minutes_left' => 0];
    }

    $lockoutTime = strtotime($user['lockout_until'] . ' UTC');
    $currentTime = time();

    if ($lockoutTime > $currentTime) {
        $minutesLeft = (int) ceil(($lockoutTime - $currentTime) / 60);
        return ['locked' => true, 'minutes_left' => $minutesLeft];
    }

    // Lockout expired: reset failed attempts
    $upd = $db->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE email = ?');
    $upd->bind_param('s', $email);
    $upd->execute();
    $upd->close();

    return ['locked' => false, 'minutes_left' => 0];
}

/**
 * Records a failed login attempt and locks account if threshold exceeded.
 *
 * @param string $email
 */
function recordFailedLogin(string $email): void
{
    $db = getDb();
    $stmt = $db->prepare('SELECT id, failed_login_attempts FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $userId = (int) $user['id'];
        $attempts = (int) $user['failed_login_attempts'] + 1;
        $lockoutSql = 'NULL';

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockoutMinutes = LOGIN_LOCKOUT_MINUTES;
            $upd = $db->prepare("UPDATE users SET failed_login_attempts = ?, lockout_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE) WHERE id = ?");
            $upd->bind_param('iii', $attempts, $lockoutMinutes, $userId);
            $upd->execute();
            $upd->close();

            logSecurity($userId, 'account_locked', $email, 'blocked');
            return;
        }

        $upd = $db->prepare('UPDATE users SET failed_login_attempts = ? WHERE id = ?');
        $upd->bind_param('ii', $attempts, $userId);
        $upd->execute();
        $upd->close();
    }

    logSecurity($user ? (int) $user['id'] : null, 'login_failed', $email, 'failed');
}

/**
 * Resets failed login counters after successful authentication.
 *
 * @param int $userId
 */
function resetLoginAttempts(int $userId): void
{
    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Attempts user login with email and password.
 *
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'error' => string|null]
 */
function attemptLogin(string $email, string $password): array
{
    $cleanEmail = trim($email);

    if (empty($cleanEmail) || empty($password)) {
        return ['success' => false, 'error' => 'Email and password are required.'];
    }

    // Check throttling
    $throttle = checkLoginThrottling($cleanEmail);
    if ($throttle['locked']) {
        return [
            'success' => false,
            'error' => "Account temporarily locked due to too many failed attempts. Please try again in {$throttle['minutes_left']} minute(s)."
        ];
    }

    $db = getDb();
    $stmt = $db->prepare('SELECT id, username, email, password, verified, timezone FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error. Please try again later.'];
    }

    $stmt->bind_param('s', $cleanEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedLogin($cleanEmail);
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    if ((int) $user['verified'] !== 1) {
        logSecurity((int) $user['id'], 'login_unverified_attempt', $cleanEmail, 'failed');
        return [
            'success' => false,
            'error' => 'Your email is not verified. Please check your inbox or click below to resend verification.',
            'unverified' => true,
            'email' => $cleanEmail
        ];
    }

    // Authentication successful
    initAppSession();
    if (!headers_sent() && session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }

    $userId = (int) $user['id'];
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    resetLoginAttempts($userId);
    logSecurity($userId, 'login_success', $cleanEmail, 'success');
    logActivity($userId, 'auth', 'login', 'User logged in successfully', 'user', $userId);

    return ['success' => true, 'error' => null];
}

/**
 * Registers a new user account, queues a verification email in a transaction,
 * and commits atomically.
 *
 * @param string $username
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'error' => string|null]
 */
function registerUser(string $username, string $email, string $password): array
{
    $cleanUsername = trim($username);
    $cleanEmail = trim($email);

    if (empty($cleanUsername) || empty($cleanEmail) || empty($password)) {
        return ['success' => false, 'error' => 'All fields are required.'];
    }

    if (mb_strlen($cleanUsername) < 3 || mb_strlen($cleanUsername) > 50) {
        return ['success' => false, 'error' => 'Username must be between 3 and 50 characters.'];
    }

    if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please provide a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
    }

    if (!isRegistrationAllowed()) {
        return ['success' => false, 'error' => 'Registration is currently closed.'];
    }

    $db = getDb();

    // Check if email already registered
    $checkStmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->bind_param('s', $cleanEmail);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        $checkStmt->close();
        return ['success' => false, 'error' => 'An account with that email already exists.'];
    }
    $checkStmt->close();

    // Prepare token & hash
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $expiresHours = VERIFY_TOKEN_LIFETIME_HOURS;

    // Start Transaction
    $db->begin_transaction();
    try {
        $ins = $db->prepare("
            INSERT INTO users (username, email, password, verified, verification_token_hash, verification_expires_at, created_at)
            VALUES (?, ?, ?, 0, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? HOUR), UTC_TIMESTAMP())
        ");
        $ins->bind_param('ssssi', $cleanUsername, $cleanEmail, $hashedPassword, $tokenHash, $expiresHours);
        $ins->execute();
        $newUserId = (int) $ins->insert_id;
        $ins->close();

        // Create default notification settings
        $ns = $db->prepare('INSERT INTO notification_settings (user_id) VALUES (?)');
        $ns->bind_param('i', $newUserId);
        $ns->execute();
        $ns->close();

        // Create default categories for productivity
        $defaultCategories = [
            ['Work', '#6366f1', 'folder'],
            ['Personal', '#10b981', 'user'],
            ['Health & Fitness', '#f59e0b', 'flame'],
            ['Learning', '#06b6d4', 'book']
        ];
        $catStmt = $db->prepare('INSERT INTO categories (user_id, name, color, icon) VALUES (?, ?, ?, ?)');
        foreach ($defaultCategories as $cat) {
            $catStmt->bind_param('isss', $newUserId, $cat[0], $cat[1], $cat[2]);
            $catStmt->execute();
        }
        $catStmt->close();

        // Audit Log
        logActivity($newUserId, 'auth', 'register', "Account registered for {$cleanUsername}", 'user', $newUserId);
        logSecurity($newUserId, 'user_registered', $cleanEmail, 'success');

        // Queue Verification Email
        $verifyUrl = url("verify.php?token=" . urlencode($rawToken));
        $contentHtml = "
            <p>Welcome to <strong>" . escape(APP_NAME) . "</strong>, " . escape($cleanUsername) . "!</p>
            <p>Thank you for setting up your personal productivity account. To activate your workspace and log in securely, please verify your email address using the button below.</p>
            <p style='color: #94a3b8; font-size: 13px;'>This link is valid for " . $expiresHours . " hours.</p>
        ";
        $emailHtml = buildEmailTemplate(
            'Verify Your Email Address',
            'Verify Your Email Address',
            $contentHtml,
            'Verify Email Address',
            $verifyUrl
        );

        $outboxId = queueEmail($newUserId, $cleanEmail, $cleanUsername, 'Verify your email - ' . APP_NAME, $emailHtml);

        // Commit transaction
        $db->commit();

        // Post-commit: dispatch email immediately via outbox processor
        if ($outboxId) {
            processOutbox(1, $outboxId);
        }

        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        error_log('Registration Error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'An error occurred during registration. Please try again.'];
    }
}

/**
 * Verifies an email token.
 *
 * @param string $rawToken
 * @return array ['success' => bool, 'error' => string|null]
 */
function verifyEmailToken(string $rawToken): array
{
    $tokenHash = hash('sha256', trim($rawToken));
    $db = getDb();

    $stmt = $db->prepare('
        SELECT id, username, email, verification_expires_at
        FROM users
        WHERE verification_token_hash = ? AND verified = 0
        LIMIT 1
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid or already used verification link.'];
    }

    if (strtotime($user['verification_expires_at'] . ' UTC') < time()) {
        return ['success' => false, 'error' => 'This verification link has expired. Please request a new one.'];
    }

    $userId = (int) $user['id'];

    $db->begin_transaction();
    try {
        $upd = $db->prepare('
            UPDATE users
            SET verified = 1, verification_token_hash = NULL, verification_expires_at = NULL
            WHERE id = ?
        ');
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();

        logActivity($userId, 'auth', 'email_verified', 'User verified their email address', 'user', $userId);
        logSecurity($userId, 'email_verified', $user['email'], 'success');

        $db->commit();
        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'error' => 'Failed to verify email. Please try again.'];
    }
}

/**
 * Initiates a password reset request and queues an email notification.
 *
 * @param string $email
 * @return array ['success' => bool, 'error' => string|null]
 */
function requestPasswordReset(string $email): array
{
    $cleanEmail = trim($email);
    if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please provide a valid email address.'];
    }

    $db = getDb();
    $stmt = $db->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $cleanEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // If user does not exist, return generic success to avoid email enumeration
    if (!$user) {
        logSecurity(null, 'password_reset_request_unknown', $cleanEmail, 'failed');
        return ['success' => true, 'error' => null];
    }

    $userId = (int) $user['id'];
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $minutes = RESET_TOKEN_LIFETIME_MINUTES;

    $db->begin_transaction();
    try {
        $upd = $db->prepare("
            UPDATE users
            SET reset_token_hash = ?, reset_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE)
            WHERE id = ?
        ");
        $upd->bind_param('sii', $tokenHash, $minutes, $userId);
        $upd->execute();
        $upd->close();

        logActivity($userId, 'auth', 'password_reset_requested', 'Password reset requested', 'user', $userId);
        logSecurity($userId, 'password_reset_request', $cleanEmail, 'success');

        $resetUrl = url("createpassword.php?token=" . urlencode($rawToken));
        $contentHtml = "
            <p>Hello <strong>" . escape($user['username']) . "</strong>,</p>
            <p>We received a request to reset the password for your " . escape(APP_NAME) . " account.</p>
            <p>If you requested this change, click the button below to set a new password. This link will expire in <strong>" . $minutes . " minutes</strong>.</p>
            <p style='color: #94a3b8; font-size: 13px;'>If you did not request this, please ignore this email. Your current password will remain safe and unchanged.</p>
        ";
        $emailHtml = buildEmailTemplate(
            'Reset Your Password',
            'Password Reset Request',
            $contentHtml,
            'Set New Password',
            $resetUrl
        );

        $outboxId = queueEmail($userId, $cleanEmail, $user['username'], 'Password Reset - ' . APP_NAME, $emailHtml);

        $db->commit();

        if ($outboxId) {
            processOutbox(1, $outboxId);
        }

        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'error' => 'Unable to process reset request.'];
    }
}

/**
 * Validates a password reset token.
 *
 * @param string $rawToken
 * @return array|false The user record or false.
 */
function validatePasswordResetToken(string $rawToken)
{
    $tokenHash = hash('sha256', trim($rawToken));
    $db = getDb();

    $stmt = $db->prepare('
        SELECT id, username, email, reset_expires_at
        FROM users
        WHERE reset_token_hash = ?
        LIMIT 1
    ');

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return false;
    }

    if (strtotime($user['reset_expires_at'] . ' UTC') < time()) {
        return false;
    }

    return $user;
}

/**
 * Updates password using a valid reset token.
 *
 * @param string $rawToken
 * @param string $newPassword
 * @return array ['success' => bool, 'error' => string|null]
 */
function completePasswordReset(string $rawToken, string $newPassword): array
{
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
    }

    $user = validatePasswordResetToken($rawToken);
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid or expired password reset link. Please request a new one.'];
    }

    $userId = (int) $user['id'];
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $db = getDb();

    $db->begin_transaction();
    try {
        $upd = $db->prepare('
            UPDATE users
            SET password = ?, reset_token_hash = NULL, reset_expires_at = NULL, failed_login_attempts = 0, lockout_until = NULL
            WHERE id = ?
        ');
        $upd->bind_param('si', $hashed, $userId);
        $upd->execute();
        $upd->close();

        logActivity($userId, 'auth', 'password_reset_completed', 'Password reset successfully completed', 'user', $userId);
        logSecurity($userId, 'password_reset_success', $user['email'], 'success');

        $db->commit();
        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'error' => 'Failed to update password.'];
    }
}

/**
 * Logs out the current user and clears session data.
 */
function logoutUser(): void
{
    initAppSession();
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'user';

    if ($userId) {
        logActivity((int) $userId, 'auth', 'logout', "User logged out", 'user', (int) $userId);
        logSecurity((int) $userId, 'logout', $username, 'success');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
