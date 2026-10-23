<?php
/**
 * Reports and Statistics Page
 *
 * Real database calculations, date range filters, and interactive charts.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reports.php';

$user = requireAuth();
$userId = (int) $user['id'];

$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 14, 30, 90], true)) {
    $days = 30;
}

$report = getProductivityReport($userId, $days);

$pageTitle = 'Reports & Statistics';
$pageSubtitle = "Deep dive into your performance and trends over the last {$days} days.";
$activeNav = 'reports';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Timeframe Filter -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 24px; display: flex; align-items: center; justify-content: space-between;">
        <div style="font-weight: 600; color: var(--text-primary); font-size: 0.95rem;">
            Timeframe: <span style="color: var(--accent-primary);"><?= formatDate($report['start_date']) ?> — <?= formatDate($report['end_date']) ?></span>
        </div>

        <div style="display: flex; gap: 8px;">
            <a href="reports.php?days=7" class="btn <?= $days === 7 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">7 Days</a>
            <a href="reports.php?days=14" class="btn <?= $days === 14 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">14 Days</a>
            <a href="reports.php?days=30" class="btn <?= $days === 30 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">30 Days</a>
            <a href="reports.php?days=90" class="btn <?= $days === 90 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">90 Days</a>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="grid-2" style="margin-bottom: 24px;">
    <!-- Trends Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= renderIcon('chart') ?> Daily Completion Velocity</h3>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 260px;">
                <canvas id="velocityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tasks by Priority Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= renderIcon('check-square') ?> Task Priority Distribution</h3>
        </div>
        <div class="card-body">
            <div style="position: relative; height: 260px;">
                <canvas id="priorityChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Top Consistent Habits -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= renderIcon('flame') ?> Most Consistent Habits</h3>
        </div>
        <div class="card-body">
            <?php if (empty($report['top_habits'])): ?>
                <p style="font-size: 0.88rem; color: var(--text-muted); text-align: center; padding: 24px 0;">
                    No habit completions logged in this timeframe.
                </p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <?php foreach ($report['top_habits'] as $th): ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 6px;">
                                <span style="font-weight: 600; color: var(--text-primary);"><?= escape($th['title']) ?></span>
                                <span style="font-weight: 700; color: #10b981;"><?= (int)$th['completions'] ?> check-ins</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= min(100, (int)$th['completions'] * 4) ?>%; background: <?= escape($th['color'] ?: '#10b981') ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mood Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= renderIcon('book') ?> Emotional Well-Being (Mood Logs)</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; text-align: center;">
                <div style="padding: 14px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-sm);">
                    <div style="font-size: 1.6rem;">🌟</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; margin: 4px 0;"><?= $report['mood_distribution']['great'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Great</div>
                </div>

                <div style="padding: 14px; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-sm);">
                    <div style="font-size: 1.6rem;">😊</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #3b82f6; margin: 4px 0;"><?= $report['mood_distribution']['good'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Good</div>
                </div>

                <div style="padding: 14px; background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-sm);">
                    <div style="font-size: 1.6rem;">😐</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #f59e0b; margin: 4px 0;"><?= $report['mood_distribution']['neutral'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Neutral</div>
                </div>

                <div style="padding: 14px; background: rgba(236, 72, 153, 0.1); border-radius: var(--radius-sm);">
                    <div style="font-size: 1.6rem;">😔</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #ec4899; margin: 4px 0;"><?= $report['mood_distribution']['bad'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Tough</div>
                </div>

                <div style="padding: 14px; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-sm);">
                    <div style="font-size: 1.6rem;">🌧️</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #ef4444; margin: 4px 0;"><?= $report['mood_distribution']['awful'] ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Struggling</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Velocity Chart
    const vCtx = document.getElementById('velocityChart');
    if (vCtx) {
        new Chart(vCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($report['trend']['labels']) ?>,
                datasets: [
                    {
                        label: 'Tasks',
                        data: <?= json_encode($report['trend']['tasks_completed']) ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 4
                    },
                    {
                        label: 'Habits',
                        data: <?= json_encode($report['trend']['habits_completed']) ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#94a3b8' } }
                },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } }
                }
            }
        });
    }

    // Priority Chart
    const pCtx = document.getElementById('priorityChart');
    if (pCtx) {
        new Chart(pCtx, {
            type: 'doughnut',
            data: {
                labels: ['Urgent', 'High', 'Medium', 'Low'],
                datasets: [{
                    data: [
                        <?= $report['priority_breakdown']['urgent'] ?>,
                        <?= $report['priority_breakdown']['high'] ?>,
                        <?= $report['priority_breakdown']['medium'] ?>,
                        <?= $report['priority_breakdown']['low'] ?>
                    ],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#64748b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8' } }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
