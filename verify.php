<?php
/**
 * Email Verification Page
 *
 * Verifies single-use cryptographic tokens sent via transactional emails.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();

$token = trim($_GET['token'] ?? '');
$status = 'pending';
$message = '';

if (!empty($token)) {
    $verifyResult = verifyEmailToken($token);
    if ($verifyResult['success']) {
        $status = 'success';
        $message = 'Your email address has been verified successfully! You may now sign in to your workspace.';
    } else {
        $status = 'error';
        $message = $verifyResult['error'];
    }
}

$pageTitle = 'Email Verification';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">
    <div class="card" style="width: 100%; max-width: 480px; text-align: center; box-shadow: var(--shadow-lg);">
        <div class="card-body" style="padding: 40px 32px;">
            <?php if ($status === 'success'): ?>
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    ✓
                </div>
                <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 10px;">Email Verified!</h1>
                <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 28px;">
                    <?= escape($message) ?>
                </p>
                <a href="login.php" class="btn btn-primary" style="width: 100%;">Proceed to Sign In &rarr;</a>

            <?php elseif ($status === 'error'): ?>
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #ef4444; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    ✕
                </div>
                <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 10px;">Verification Failed</h1>
                <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 28px;">
                    <?= escape($message) ?>
                </p>
                <a href="login.php" class="btn btn-secondary" style="width: 100%;">Return to Login</a>

            <?php else: ?>
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(59, 130, 246, 0.15); color: #3b82f6; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <?= renderIcon('mail') ?>
                </div>
                <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 10px;">Email Verification</h1>
                <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 28px;">
                    Please click the verification link sent to your email address to activate your account.
                </p>
                <a href="login.php" class="btn btn-secondary" style="width: 100%;">Back to Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
