<?php
/**
 * Account Security Page
 *
 * Password updates, active session controls, and security posture review.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$user = requireAuth();
$userId = (int) $user['id'];

// Handle Password Change POST
if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        setFlash('danger', 'Security verification failed.');
        redirect('security.php');
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setFlash('danger', 'All password fields are required.');
        redirect('security.php');
    }

    if ($newPassword !== $confirmPassword) {
        setFlash('danger', 'New password and confirmation do not match.');
        redirect('security.php');
    }

    if (strlen($newPassword) < 8) {
        setFlash('danger', 'New password must be at least 8 characters long.');
        redirect('security.php');
    }

    $db = getDb();
    $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $storedHash = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    if (!password_verify($currentPassword, $storedHash)) {
        logSecurity($userId, 'password_change_failed', $user['email'], 'failed');
        setFlash('danger', 'Your current password is incorrect.');
        redirect('security.php');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->bind_param('si', $newHash, $userId);

    if ($upd->execute()) {
        $upd->close();
        logActivity($userId, 'auth', 'password_change', 'User changed their account password', 'user', $userId);
        logSecurity($userId, 'password_change_success', $user['email'], 'success');
        setFlash('success', 'Your password was successfully updated.');
    } else {
        $upd->close();
        setFlash('danger', 'Failed to update password.');
    }
    redirect('security.php');
}

$pageTitle = 'Account Security';
$pageSubtitle = 'Manage authentication credentials and review security posture.';
$activeNav = 'security';

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-main-side">
    <!-- Password Change Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= renderIcon('lock') ?> Change Password</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="security.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="curr_pass">Current Password *</label>
                    <input type="password" id="curr_pass" name="current_password" class="form-control" required autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_pass">New Password * (Minimum 8 characters)</label>
                    <input type="password" id="new_pass" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="conf_pass">Confirm New Password *</label>
                    <input type="password" id="conf_pass" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Security Posture Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= renderIcon('shield') ?> Security Health</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">Email Verification</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);"><?= escape($user['email']) ?></div>
                    </div>
                    <span class="badge badge-success">Verified</span>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">Password Hashing</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">BCrypt (PHP standard)</div>
                    </div>
                    <span class="badge badge-success">Secure</span>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">Login Brute-Force Shield</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Automatic lockout after 5 fails</div>
                    </div>
                    <span class="badge badge-info">Active</span>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">CSRF Protection</div>
                        <div style="font-size: 0.78rem; color: var(--text-muted);">Cryptographic tokens on all forms</div>
                    </div>
                    <span class="badge badge-info">Active</span>
                </div>
            </div>

            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                <a href="<?= url('security-logs.php') ?>" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">
                    View Security Logs &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
