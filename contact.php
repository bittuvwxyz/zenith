<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Contact Us — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 720px; margin: 30px auto; padding: 20px;">
    <div class="card-header">
        <h1 class="card-title" style="font-size: 1.8rem;">Contact &amp; Support</h1>
    </div>
    <div class="card-body" style="line-height: 1.8; color: var(--text-secondary); font-size: 1rem;">
        <p style="margin-bottom: 20px;">
            Need help or have questions regarding your personal workspace? Contact the system administrator or check the application status below.
        </p>

        <div class="stat-card" style="margin-bottom: 24px;">
            <div style="font-size: 0.85rem; color: var(--text-muted);">Primary Support Email</div>
            <div style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-top: 4px;">
                <a href="mailto:<?= escape(MAIL_FROM_EMAIL) ?>"><?= escape(MAIL_FROM_EMAIL) ?></a>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <a href="<?= url('dashboard.php') ?>" class="btn btn-secondary">&larr; Return to Workspace</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
