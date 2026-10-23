<?php
/**
 * Create New Password Page
 *
 * Sets new password following validation of recovery token.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();
requireGuest();

$rawToken = trim($_GET['token'] ?? '');
$validUser = validatePasswordResetToken($rawToken);
$errorMsg = null;

if (!$validUser) {
    $errorMsg = 'This password reset link is invalid or has expired. Please request a new recovery link.';
}

if ($validUser && isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = 'Security token invalid. Please refresh.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            $errorMsg = 'Passwords do not match.';
        } else {
            $res = completePasswordReset($rawToken, $password);
            if ($res['success']) {
                setFlash('success', 'Your password has been successfully reset! You can now sign in.');
                redirect('login.php');
            } else {
                $errorMsg = $res['error'];
            }
        }
    }
}

$pageTitle = 'Set New Password';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">
    <div class="card" style="width: 100%; max-width: 440px; box-shadow: var(--shadow-lg);">
        <div class="card-header" style="text-align: center; display: block; padding: 32px 28px 20px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 24px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                ✓
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0;">Create New Password</h1>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">Set a strong, unique password for your account</p>
        </div>

        <div class="card-body" style="padding: 24px 28px;">
            <?php if (!$validUser): ?>
                <div style="padding: 14px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); font-size: 0.88rem; color: #fca5a5; line-height: 1.5; margin-bottom: 20px;">
                    <?= escape($errorMsg) ?>
                </div>
                <a href="reset.php" class="btn btn-primary" style="width: 100%;">Request New Reset Link</a>
            <?php else: ?>
                <?php if ($errorMsg): ?>
                    <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.88rem; color: #fca5a5;">
                        <?= escape($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="createpassword.php?token=<?= urlencode($rawToken) ?>">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password (min 8 chars)</label>
                        <input type="password" id="new_password" name="password" class="form-control" minlength="8" required autocomplete="new-password" autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                        Set Password & Sign In
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
