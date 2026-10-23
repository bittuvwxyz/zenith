<?php
/**
 * Security Logs Page
 *
 * Tracks authentication attempts, password resets, IP addresses, and security events.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$user = requireAuth();
$userId = (int) $user['id'];

$eventFilter = $_GET['event_type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$filters = [
    'event_type' => $eventFilter,
    'status'     => $statusFilter,
    'search'     => $searchFilter,
];

$secData = getSecurityLogs($userId, $filters, $limit, $offset);
$logs = $secData['logs'];
$total = $secData['total'];
$totalPages = ceil($total / $limit);

$pageTitle = 'Security Logs';
$pageSubtitle = 'Monitor authentication events, suspicious attempts, and access history.';
$activeNav = 'security-logs';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="security-logs.php" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1;">
                <div style="position: relative; min-width: 220px;">
                    <input type="text" name="search" class="form-control" placeholder="Search identifier or IP..." value="<?= escape($searchFilter) ?>" style="padding-left: 36px;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none;">
                        <?= renderIcon('search') ?>
                    </span>
                </div>

                <select name="event_type" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Events</option>
                    <option value="login_success" <?= $eventFilter === 'login_success' ? 'selected' : '' ?>>Login Success</option>
                    <option value="login_failed" <?= $eventFilter === 'login_failed' ? 'selected' : '' ?>>Login Failed</option>
                    <option value="logout" <?= $eventFilter === 'logout' ? 'selected' : '' ?>>Logout</option>
                    <option value="password_reset_request" <?= $eventFilter === 'password_reset_request' ? 'selected' : '' ?>>Password Reset Request</option>
                    <option value="password_reset_success" <?= $eventFilter === 'password_reset_success' ? 'selected' : '' ?>>Password Reset Success</option>
                    <option value="account_locked" <?= $eventFilter === 'account_locked' ? 'selected' : '' ?>>Account Locked</option>
                </select>

                <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="blocked" <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm"><?= renderIcon('filter') ?> Filter</button>
                <?php if (!empty($searchFilter) || !empty($eventFilter) || !empty($statusFilter)): ?>
                    <a href="security-logs.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= renderIcon('shield') ?> Authentication & Security Events (<?= $total ?>)</h2>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Event Type</th>
                    <th>Target Identifier</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--text-muted);">
                            No security events recorded matching criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.82rem;">
                                <?= formatDateTime($log['created_at']) ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem;">
                                    <?= escape($log['event_type']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.88rem; color: var(--text-primary);">
                                <?= escape($log['identifier']) ?>
                            </td>
                            <td style="font-family: monospace; font-size: 0.82rem; color: var(--text-muted);">
                                <?= escape($log['ip_address']) ?>
                            </td>
                            <td style="font-size: 0.78rem; color: var(--text-muted); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= escape($log['user_agent']) ?>">
                                <?= escape($log['user_agent']) ?>
                            </td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php elseif ($log['status'] === 'blocked'): ?>
                                    <span class="badge badge-danger">Blocked</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Failed</span>
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
                <a href="security-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">&larr; Prev</a>
            <?php endif; ?>

            <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0 8px;">
                Page <?= $page ?> of <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
                <a href="security-logs.php?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
