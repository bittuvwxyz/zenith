<?php
/**
 * Registration Page
 *
 * Initial account setup and single-user registration gateway.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();
requireGuest();

$isRegAllowed = isRegistrationAllowed();
$errorMsg = null;
$successMsg = null;

if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        $errorMsg = 'Security validation failed. Please refresh.';
    } elseif (!$isRegAllowed) {
        $errorMsg = 'Registration is closed. Only the primary workspace owner account is permitted.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $res = registerUser($username, $email, $password);
        if ($res['success']) {
            setFlash('success', 'Account created! Please check your email to verify your address before signing in.');
            redirect('login.php');
        } else {
            $errorMsg = $res['error'];
        }
    }
}

$pageTitle = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px 0;">
    <div class="card" style="width: 100%; max-width: 460px; box-shadow: var(--shadow-lg);">
        <div class="card-header" style="text-align: center; display: block; padding: 32px 28px 20px;">
            <div style="width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; font-size: 24px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                ⚡
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0;">Create Workspace</h1>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">Get started with your personal productivity suite</p>
        </div>

        <div class="card-body" style="padding: 24px 28px;">
            <?php if (!$isRegAllowed): ?>
                <div style="padding: 16px; background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-sm); font-size: 0.88rem; color: #fde68a; line-height: 1.5; margin-bottom: 20px; text-align: center;">
                    <strong>Registration Closed:</strong> This instance is configured for a single workspace owner and already has an account configured.
                </div>
                <a href="login.php" class="btn btn-primary" style="width: 100%;">Go to Sign In &rarr;</a>
            <?php else: ?>
                <?php if ($errorMsg): ?>
                    <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.88rem; color: #fca5a5;">
                        <?= escape($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label class="form-label" for="reg_username">Username *</label>
                        <input type="text" id="reg_username" name="username" class="form-control" placeholder="john_doe" required autocomplete="username" autofocus value="<?= escape($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg_email">Email Address *</label>
                        <input type="email" id="reg_email" name="email" class="form-control" placeholder="you@domain.com" required autocomplete="email" value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg_password">Password * (Min 8 characters)</label>
                        <input type="password" id="reg_password" name="password" class="form-control" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; margin-top: 8px;">
                        Create Account &rarr;
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card-footer" style="justify-content: center; font-size: 0.88rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600; margin-left: 6px;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
