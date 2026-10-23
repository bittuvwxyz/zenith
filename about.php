<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About Us — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 900px; margin: 30px auto; padding: 20px;">
    <div class="card-header">
        <h1 class="card-title" style="font-size: 1.8rem;"><?= escape(APP_NAME) ?> — About the System</h1>
    </div>
    <div class="card-body" style="line-height: 1.8; color: var(--text-secondary); font-size: 1rem;">
        <p style="margin-bottom: 18px;">
            <strong><?= escape(APP_NAME) ?></strong> is an executive personal productivity and focus management workspace built for high-performers, builders, and professionals who demand seamless execution without the bloat of fragmented tools.
        </p>

        <h3 style="color: var(--text-primary); font-size: 1.2rem; margin: 24px 0 12px;">Core Architecture</h3>
        <ul style="padding-left: 20px; margin-bottom: 24px; display: flex; flex-direction: column; gap: 8px;">
            <li><strong>Database-First Relational Model:</strong> Powered by MySQL with MySQLi prepared statements.</li>
            <li><strong>Transactional Outbox:</strong> All notifications are queued atomically with database changes.</li>
            <li><strong>Privacy-Conscious Logging:</strong> Tamper-evident activity logs with automatic redaction of journal content and secrets.</li>
            <li><strong>Multi-Dimensional Productivity:</strong> Combines task due dates, habit streaks, daily routines, and mindful journaling.</li>
        </ul>

        <div style="margin-top: 30px;">
            <a href="<?= url('dashboard.php') ?>" class="btn btn-primary">&larr; Return to Workspace</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
