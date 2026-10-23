<?php
/**
 * Login Page
 *
 * Secure login with email and password, login throttling, CSRF,
 * and single-user setup notifications.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();
requireGuest();

$hasUsers = hasRegisteredUsers();
$isRegAllowed = isRegistrationAllowed();
$errorMsg = null;

if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = 'Security validation failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $login = attemptLogin($email, $password);
        if ($login['success']) {
            redirect('dashboard.php');
        } else {
            $errorMsg = $login['error'];
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">
    <div class="card" style="width: 100%; max-width: 440px; box-shadow: var(--shadow-lg);">
        <div class="card-header" style="text-align: center; display: block; padding: 32px 28px 20px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; font-size: 24px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                ⚡
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0;"><?= escape(APP_NAME) ?></h1>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">Sign in to your productivity workspace</p>
        </div>

        <div class="card-body" style="padding: 24px 28px;">
            <?php if (!$hasUsers): ?>
                <div style="padding: 14px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.88rem; color: #93c5fd;">
                    <strong>First-time Setup:</strong> No user accounts exist yet. Please
                    <a href="register.php" style="color: #ffffff; text-decoration: underline; font-weight: 700;">register your primary account</a>.
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.88rem; color: #fca5a5;">
                    <?= escape($errorMsg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="login_email">Email Address</label>
                    <input type="email" id="login_email" name="email" class="form-control" placeholder="you@domain.com" required autocomplete="email" autofocus value="<?= escape($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <label class="form-label" for="login_password" style="margin: 0;">Password</label>
                        <a href="reset.php" style="font-size: 0.82rem; color: var(--accent-primary);">Forgot password?</a>
                    </div>
                    <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password" style="margin-top: 6px;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                    Sign In &rarr;
                </button>
            </form>
        </div>

        <?php if ($isRegAllowed): ?>
            <div class="card-footer" style="justify-content: center; font-size: 0.88rem; color: var(--text-muted);">
                Don't have an account? <a href="register.php" style="font-weight: 600; margin-left: 6px;">Sign Up</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
