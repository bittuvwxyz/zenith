<?php
/**
 * Calendar Page
 *
 * Month navigation with live indicators for tasks and daily habit progress.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tasks.php';

$user = requireAuth();
$userId = (int) $user['id'];

// Month & Year calculation
$month = (int) ($_GET['m'] ?? date('n'));
$year = (int) ($_GET['y'] ?? date('Y'));

if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDayTimestamp = mktime(0, 0, 0, $month, 1, $year);
$monthName = date('F', $firstDayTimestamp);
$daysInMonth = (int) date('t', $firstDayTimestamp);
$startDayOfWeek = (int) date('w', $firstDayTimestamp); // 0 (Sun) to 6 (Sat)

// Fetch tasks for this month
$startDateStr = sprintf('%04d-%02d-01', $year, $month);
$endDateStr = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$db = getDb();
$tStmt = $db->prepare('
    SELECT id, title, priority, status, due_date
    FROM tasks
    WHERE user_id = ? AND due_date BETWEEN ? AND ?
    ORDER BY due_date ASC, priority DESC
');
$tStmt->bind_param('iss', $userId, $startDateStr, $endDateStr);
$tStmt->execute();
$tasksByDate = [];
foreach ($tStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
    $tasksByDate[$t['due_date']][] = $t;
}
$tStmt->close();

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$todayDate = date('Y-m-d');

$pageTitle = 'Calendar';
$pageSubtitle = "Viewing {$monthName} {$year} deadlines and commitments.";
$activeNav = 'calendar';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Month Navigation Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <a href="calendar.php?m=<?= $prevMonth ?>&y=<?= $prevYear ?>" class="btn btn-secondary btn-sm" aria-label="Previous Month">
                &larr; Prev
            </a>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0;">
                <?= $monthName ?> <?= $year ?>
            </h2>
            <a href="calendar.php?m=<?= $nextMonth ?>&y=<?= $nextYear ?>" class="btn btn-secondary btn-sm" aria-label="Next Month">
                Next &rarr;
            </a>
        </div>

        <a href="calendar.php" class="btn btn-ghost btn-sm">Today</a>
    </div>
</div>

<!-- Calendar Grid -->
<div class="card">
    <div class="card-body" style="padding: 16px;">
        <!-- Day Names Header -->
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-weight: 700; font-size: 0.8rem; color: var(--text-muted); padding-bottom: 12px; border-bottom: 1px solid var(--border-subtle); text-transform: uppercase;">
            <div>Sun</div>
            <div>Mon</div>
            <div>Tue</div>
            <div>Wed</div>
            <div>Thu</div>
            <div>Fri</div>
            <div>Sat</div>
        </div>

        <!-- Days Grid -->
        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; padding-top: 8px;">
            <!-- Empty days before first of month -->
            <?php for ($i = 0; $i < $startDayOfWeek; $i++): ?>
                <div style="min-height: 100px; background: rgba(0,0,0,0.06); border-radius: var(--radius-sm); opacity: 0.3;"></div>
            <?php endfor; ?>

            <!-- Days in current month -->
            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $curDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = ($curDate === $todayDate);
                $dayTasks = $tasksByDate[$curDate] ?? [];
                ?>
                <div style="min-height: 100px; padding: 8px; border-radius: var(--radius-sm); background-color: <?= $isToday ? 'rgba(59, 130, 246, 0.08)' : 'var(--bg-surface-elevated)' ?>; border: 1px solid <?= $isToday ? 'var(--accent-primary)' : 'var(--border-subtle)' ?>; display: flex; flex-direction: column; justify-content: space-between;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-size: 0.85rem; font-weight: <?= $isToday ? '800' : '600' ?>; color: <?= $isToday ? 'var(--accent-primary)' : 'var(--text-primary)' ?>;">
                            <?= $d ?>
                        </span>
                        <?php if ($isToday): ?>
                            <span class="badge badge-info" style="font-size: 0.65rem;">Today</span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 4px; overflow-y: auto; max-height: 80px;">
                        <?php foreach (array_slice($dayTasks, 0, 3) as $t): ?>
                            <div style="font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; background: <?= $t['status'] === 'completed' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(59, 130, 246, 0.15)' ?>; color: <?= $t['status'] === 'completed' ? '#10b981' : '#60a5fa' ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; <?= $t['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>">
                                <?= escape($t['title']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($dayTasks) > 3): ?>
                            <div style="font-size: 0.68rem; color: var(--text-muted); text-align: center;">
                                +<?= count($dayTasks) - 3 ?> more
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
