<?php
/**
 * Email Outbox Logs Page
 *
 * Monitors transactional emails, delivery status, retry attempts, and errors.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/outbox.php';

$user = requireAuth();
$userId = (int) $user['id'];

// Trigger manual outbox flush if requested
if (isPost() && isset($_POST['action']) && $_POST['action'] === 'flush_outbox') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $stats = processOutbox(10);
        setFlash('info', "Outbox processed: {$stats['sent']} sent, {$stats['failed']} failed.");
    }
    redirect('email-logs.php');
}

$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$filters = [
    'status' => $statusFilter,
    'search' => $searchFilter,
];

$emailData = getEmailLogs($filters, $limit, $offset);
$logs = $emailData['logs'];
$total = $emailData['total'];
$totalPages = ceil($total / $limit);

$pageTitle = 'Email Logs';
$pageSubtitle = 'Monitor transactional outbox notifications, delivery confirmations, and SMTP logs.';
$activeNav = 'email-logs';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter & Action Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
            <form method="GET" action="email-logs.php" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1;">
                <div style="position: relative; min-width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="Search recipient or subject..." value="<?= escape($searchFilter) ?>" style="padding-left: 36px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                        <?= renderIcon('search') ?>
                    </span>
                </div>

                <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm"><?= renderIcon('filter') ?> Filter</button>
                <?php if (!empty($searchFilter) || !empty($statusFilter)): ?>
                    <a href="email-logs.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </form>

            <form method="POST" action="email-logs.php" style="margin: 0;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="flush_outbox">
                <button type="submit" class="btn btn-primary btn-sm">
                    <?= renderIcon('mail') ?> Process Outbox Now
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= renderIcon('mail') ?> Transactional Outbox (<?= $total ?> messages)</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Created</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Sent At / Error</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            No transactional emails logged yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.82rem;">
                                <?= formatDateTime($log['created_at']) ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-primary); font-size: 0.88rem;">
                                    <?= escape($log['recipient_name'] ?: 'User') ?>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted);">
                                    <?= escape($log['recipient_email']) ?>
                                </div>
                            </td>
                            <td style="font-size: 0.9rem; color: var(--text-primary); font-weight: 500;">
                                <?= escape($log['subject']) ?>
                            </td>
                            <td>
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="badge badge-success">Delivered</span>
                                <?php elseif ($log['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Queued</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <?= (int)$log['attempts'] ?> / <?= (int)$log['max_attempts'] ?>
                            </td>
                            <td style="font-size: 0.8rem; max-width: 250px;">
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span style="color: var(--accent-success);"><?= formatDateTime($log['sent_at']) ?></span>
                                <?php elseif (!empty($log['last_error'])): ?>
                                    <span style="color: var(--accent-danger);" title="<?= escape($log['last_error']) ?>">
                                        <?= escape(mb_substr($log['last_error'], 0, 50)) ?>...
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Pending delivery</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="justify-content: center; gap: 8px;">
            <?php if ($page > 1): ?>
                <a href="email-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
            <?php endif; ?>

            <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0 8px;">
                Page <?= $page ?> of <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="email-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
