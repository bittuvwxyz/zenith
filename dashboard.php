<?php
/**
 * Dashboard Page
 *
 * Real-time summary of tasks, habits, daily routine, journal,
 * weekly productivity charts, upcoming deadlines, and recent activity.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/tasks.php';
require_once __DIR__ . '/includes/habits.php';

$user = requireAuth();
$userId = (int) $user['id'];

$metrics = getDashboardMetrics($userId);
$todayHabits = getHabits($userId, false, date('Y-m-d'));

$pageTitle = 'Dashboard';
$pageSubtitle = 'Welcome back, ' . escape($user['username']) . '. Here is your productivity pulse for today.';
$activeNav = 'dashboard';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Quick Stats Row -->
<div class="grid-4" style="margin-bottom: 24px;">
    <!-- Tasks Stat Card -->
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Tasks Due Today</span>
            <div class="stat-icon blue">
                <?= renderIcon('check-square') ?>
            </div>
        </div>
        <div class="stat-value"><?= $metrics['tasks']['due_today'] ?></div>
        <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between;">
            <span><?= $metrics['tasks']['completed'] ?> completed</span>
            <span><?= $metrics['tasks']['pending'] ?> pending</span>
        </div>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width: <?= $metrics['tasks']['completion_rate'] ?>%;"></div>
        </div>
    </div>

    <!-- Habits Stat Card -->
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Habits Today</span>
            <div class="stat-icon green">
                <?= renderIcon('flame') ?>
            </div>
        </div>
        <div class="stat-value"><?= $metrics['habits']['completed_today'] ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">/ <?= $metrics['habits']['total'] ?></span></div>
        <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between;">
            <span>Consistency</span>
            <span><?= $metrics['habits']['completion_rate'] ?>% done</span>
        </div>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width: <?= $metrics['habits']['completion_rate'] ?>%; background: #10b981;"></div>
        </div>
    </div>

    <!-- Routines Stat Card -->
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Daily Routine</span>
            <div class="stat-icon orange">
                <?= renderIcon('clock') ?>
            </div>
        </div>
        <div class="stat-value"><?= $metrics['routines']['completed_today'] ?> <span style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">/ <?= $metrics['routines']['total'] ?></span></div>
        <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between;">
            <span>Daily Progress</span>
            <span><?= $metrics['routines']['completion_rate'] ?>% done</span>
        </div>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width: <?= $metrics['routines']['completion_rate'] ?>%; background: #f59e0b;"></div>
        </div>
    </div>

    <!-- Journal Stat Card -->
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-label">Journal Entries</span>
            <div class="stat-icon purple">
                <?= renderIcon('book') ?>
            </div>
        </div>
        <div class="stat-value"><?= $metrics['journal']['total'] ?></div>
        <div style="font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between;">
            <span>Mindful Reflections</span>
            <a href="<?= url('journal.php') ?>" style="color: var(--accent-purple); font-weight: 600;">View &rarr;</a>
        </div>
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" style="width: <?= min(100, $metrics['journal']['total'] * 10) ?>%; background: #8b5cf6;"></div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid-main-side">
    <!-- Left Column: Weekly Trends Chart & Today's Habits -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Productivity Trends Chart Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= renderIcon('chart') ?> 7-Day Productivity Trends</h2>
                    <p class="card-desc">Daily completed tasks and habit check-ins over the past week</p>
                </div>
                <a href="<?= url('reports.php') ?>" class="btn btn-secondary btn-sm">Full Analytics</a>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="weeklyTrendsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Today's Habits Quick Tracker Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= renderIcon('flame') ?> Today's Habit Check-in</h2>
                    <p class="card-desc">Click the circle to mark completed today and maintain your streak</p>
                </div>
                <a href="<?= url('habits.php') ?>" class="btn btn-secondary btn-sm">Manage Habits</a>
            </div>
            <div class="card-body">
                <?php if (empty($todayHabits)): ?>
                    <div class="empty-state" style="padding: 24px 0;">
                        <div class="empty-state-icon"><?= renderIcon('flame') ?></div>
                        <div class="empty-state-title">No habits created yet</div>
                        <p class="empty-state-desc">Create atomic daily habits to track your progress and build streaks.</p>
                        <a href="<?= url('habits.php?action=new') ?>" class="btn btn-primary btn-sm"><?= renderIcon('plus') ?> Add Habit</a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach (array_slice($todayHabits, 0, 5) as $h): ?>
                            <div class="habit-card">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <button type="button"
                                            class="habit-check-btn <?= $h['is_completed_today'] ? 'completed' : '' ?>"
                                            data-habit-id="<?= (int)$h['id'] ?>"
                                            aria-label="Toggle habit completion">
                                        <?= $h['is_completed_today'] ? '✓' : '' ?>
                                    </button>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-primary);">
                                            <?= escape($h['title']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                                            <span class="badge badge-warning habit-streak-<?= (int)$h['id'] ?>">
                                                <?= (int)$h['streak'] ?> day streak
                                            </span>
                                            <?php if (!empty($h['category_name'])): ?>
                                                <span>&bull; <?= escape($h['category_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Upcoming Tasks & Recent Activity -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Upcoming Tasks Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= renderIcon('check-square') ?> Upcoming Tasks</h2>
                    <p class="card-desc">Deadlines approaching soon</p>
                </div>
                <a href="<?= url('todos.php') ?>" class="btn btn-secondary btn-sm">All Tasks</a>
            </div>
            <div class="card-body">
                <?php if (empty($metrics['upcoming_tasks'])): ?>
                    <div class="empty-state" style="padding: 24px 0;">
                        <div class="empty-state-icon"><?= renderIcon('check') ?></div>
                        <div class="empty-state-title">No upcoming deadlines</div>
                        <p class="empty-state-desc">You are all caught up! Add a new task to stay productive.</p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('quickAddModal')">Create Task</button>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($metrics['upcoming_tasks'] as $t): ?>
                            <div class="task-item" style="padding: 10px 14px;">
                                <button type="button"
                                        class="task-checkbox task-checkbox-btn <?= $t['status'] === 'completed' ? 'checked' : '' ?>"
                                        data-task-id="<?= (int)$t['id'] ?>"
                                        aria-label="Toggle task">
                                    <?= $t['status'] === 'completed' ? '✓' : '' ?>
                                </button>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="task-title" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.9rem;">
                                        <?= escape($t['title']) ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                                        <span><?= formatDate($t['due_date'], 'M j') ?></span>
                                        <?php if ($t['priority'] === 'urgent'): ?>
                                            <span class="badge badge-danger">Urgent</span>
                                        <?php elseif ($t['priority'] === 'high'): ?>
                                            <span class="badge badge-warning">High</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= renderIcon('activity') ?> Recent Activity</h2>
                    <p class="card-desc">Your latest actions</p>
                </div>
                <a href="<?= url('activity-logs.php') ?>" class="btn btn-ghost btn-sm">View Log</a>
            </div>
            <div class="card-body" style="padding: 12px 20px;">
                <?php if (empty($metrics['recent_activity'])): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); padding: 12px 0;">No activity recorded yet.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column;">
                        <?php foreach ($metrics['recent_activity'] as $act): ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                                <div>
                                    <div style="font-size: 0.86rem; color: var(--text-primary); font-weight: 500;">
                                        <?= escape($act['description']) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?= escape(strtoupper($act['module'])) ?> &bull; <?= timeAgo($act['created_at']) ?>
                                    </div>
                                </div>
                                <span class="badge <?= $act['status'] === 'success' ? 'badge-success' : 'badge-danger' ?>" style="font-size: 0.65rem;">
                                    <?= escape($act['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('weeklyTrendsChart');
    if (!ctx) return;

    const chartLabels = <?= json_encode($metrics['weekly_trends']['labels']) ?>;
    const taskData = <?= json_encode($metrics['weekly_trends']['tasks_completed']) ?>;
    const habitData = <?= json_encode($metrics['weekly_trends']['habits_completed']) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Tasks Completed',
                    data: taskData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                },
                {
                    label: 'Habits Completed',
                    data: habitData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        color: '#94a3b8',
                        font: { size: 12, weight: 600 }
                    }
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#f8fafc',
                    bodyColor: '#cbd5e1',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#64748b' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#64748b' },
                    grid: { color: 'rgba(255,255,255,0.04)' }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
