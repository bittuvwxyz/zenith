<?php
/**
 * Password Reset Request Page
 *
 * Requests a time-limited password recovery token sent via email outbox.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();
requireGuest();

$submitted = false;
$errorMsg = null;

if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = 'Security token invalid. Please refresh.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $res = requestPasswordReset($email);
        if ($res['success']) {
            $submitted = true;
        } else {
            $errorMsg = $res['error'];
        }
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">
    <div class="card" style="width: 100%; max-width: 440px; box-shadow: var(--shadow-lg);">
        <div class="card-header" style="text-align: center; display: block; padding: 32px 28px 20px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-size: 24px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                <?= renderIcon('lock') ?>
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0;">Reset Password</h1>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">Enter your account email to receive a recovery link</p>
        </div>

        <div class="card-body" style="padding: 24px 28px;">
            <?php if ($submitted): ?>
                <div style="padding: 16px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: var(--radius-sm); font-size: 0.9rem; color: #a7f3d0; text-align: center; line-height: 1.6;">
                    If an account exists with that email address, a password reset link has been queued and dispatched. Please check your inbox.
                </div>
                <div style="margin-top: 24px; text-align: center;">
                    <a href="login.php" class="btn btn-secondary" style="width: 100%;">Return to Sign In</a>
                </div>
            <?php else: ?>
                <?php if ($errorMsg): ?>
                    <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.88rem; color: #fca5a5;">
                        <?= escape($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reset.php">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label class="form-label" for="reset_email">Registered Email Address</label>
                        <input type="email" id="reset_email" name="email" class="form-control" placeholder="you@domain.com" required autocomplete="email" autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                        Send Recovery Link
                    </button>
                </form>

                <div style="margin-top: 20px; text-align: center;">
                    <a href="login.php" style="font-size: 0.88rem; color: var(--text-muted);">
                        &larr; Back to Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
