<?php
/**
 * Activity Logs (Audit Trail) Page
 *
 * Searchable, filterable, paginated audit logging with sensitive data redaction.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$user = requireAuth();
$userId = (int) $user['id'];

$moduleFilter = $_GET['module'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$filters = [
    'module' => $moduleFilter,
    'status' => $statusFilter,
    'search' => $searchFilter,
];

$logData = getActivityLogs($userId, $filters, $limit, $offset);
$logs = $logData['logs'];
$total = $logData['total'];
$totalPages = ceil($total / $limit);

$pageTitle = 'Activity Logs';
$pageSubtitle = 'Tamper-evident audit trail of system and user activities.';
$activeNav = 'activity-logs';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="activity-logs.php" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1;">
                <div style="position: relative; min-width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="Search description..." value="<?= escape($searchFilter) ?>" style="padding-left: 36px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                        <?= renderIcon('search') ?>
                    </span>
                </div>

                <select name="module" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Modules</option>
                    <option value="auth" <?= $moduleFilter === 'auth' ? 'selected' : '' ?>>Auth</option>
                    <option value="tasks" <?= $moduleFilter === 'tasks' ? 'selected' : '' ?>>Tasks</option>
                    <option value="habits" <?= $moduleFilter === 'habits' ? 'selected' : '' ?>>Habits</option>
                    <option value="routines" <?= $moduleFilter === 'routines' ? 'selected' : '' ?>>Routines</option>
                    <option value="journal" <?= $moduleFilter === 'journal' ? 'selected' : '' ?>>Journal</option>
                    <option value="categories" <?= $moduleFilter === 'categories' ? 'selected' : '' ?>>Categories</option>
                </select>

                <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failure" <?= $statusFilter === 'failure' ? 'selected' : '' ?>>Failure</option>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm"><?= renderIcon('filter') ?> Filter</button>
                <?php if (!empty($searchFilter) || !empty($moduleFilter) || !empty($statusFilter)): ?>
                    <a href="activity-logs.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= renderIcon('activity') ?> Audit Trail (<?= $total ?> records)</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Client IP</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            No activity logs found matching criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.82rem;">
                                <?= formatDateTime($log['created_at']) ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-primary);">
                                    <?= escape(strtoupper($log['module'])) ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem;">
                                <?= escape($log['action']) ?>
                            </td>
                            <td style="max-width: 380px;">
                                <div style="font-weight: 500; color: var(--text-primary);">
                                    <?= escape($log['description']) ?>
                                </div>
                                <?php if (!empty($log['details']) && $log['details'] !== 'null'): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace; margin-top: 2px;">
                                        <?= escape(mb_substr($log['details'], 0, 100)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: monospace; font-size: 0.82rem; color: var(--text-muted);">
                                <?= escape($log['ip_address']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $log['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= escape($log['status']) ?>
                                </span>
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
                <a href="activity-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
            <?php endif; ?>

            <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0 8px;">
                Page <?= $page ?> of <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="activity-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
