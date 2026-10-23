<?php
/**
 * Reports & Metrics Module
 *
 * Computes live productivity analytics, completion rates, weekly trends,
 * and dashboard summary metrics directly from MySQL database tables.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Computes real-time dashboard overview metrics for an authenticated user.
 *
 * @param int $userId
 * @return array
 */
function getDashboardMetrics(int $userId): array
{
    $db = getDb();
    $today = date('Y-m-d');

    // 1. Tasks Summary
    $taskStmt = $db->prepare('
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = "pending" AND due_date = ? THEN 1 ELSE 0 END) AS due_today,
            SUM(CASE WHEN status = "pending" AND due_date < ? THEN 1 ELSE 0 END) AS overdue
        FROM tasks
        WHERE user_id = ?
    ');
    $taskStmt->bind_param('ssi', $today, $today, $userId);
    $taskStmt->execute();
    $taskStats = $taskStmt->get_result()->fetch_assoc();
    $taskStmt->close();

    $tasksTotal = (int) ($taskStats['total'] ?? 0);
    $tasksCompleted = (int) ($taskStats['completed'] ?? 0);
    $taskCompletionRate = $tasksTotal > 0 ? (int) round(($tasksCompleted / $tasksTotal) * 100) : 0;

    // 2. Habits Summary
    $habStmt = $db->prepare('
        SELECT
            COUNT(h.id) AS total,
            SUM(CASE WHEN hl.id IS NOT NULL THEN 1 ELSE 0 END) AS completed_today
        FROM habits h
        LEFT JOIN habit_logs hl ON (h.id = hl.habit_id AND hl.log_date = ?)
        WHERE h.user_id = ? AND h.archived = 0
    ');
    $habStmt->bind_param('si', $today, $userId);
    $habStmt->execute();
    $habStats = $habStmt->get_result()->fetch_assoc();
    $habStmt->close();

    $habitsTotal = (int) ($habStats['total'] ?? 0);
    $habitsCompleted = (int) ($habStats['completed_today'] ?? 0);
    $habitCompletionRate = $habitsTotal > 0 ? (int) round(($habitsCompleted / $habitsTotal) * 100) : 0;

    // 3. Routines Summary
    $routStmt = $db->prepare('
        SELECT
            COUNT(r.id) AS total,
            SUM(CASE WHEN rl.id IS NOT NULL THEN 1 ELSE 0 END) AS completed_today
        FROM routines r
        LEFT JOIN routine_logs rl ON (r.id = rl.routine_id AND rl.log_date = ?)
        WHERE r.user_id = ?
    ');
    $routStmt->bind_param('si', $today, $userId);
    $routStmt->execute();
    $routStats = $routStmt->get_result()->fetch_assoc();
    $routStmt->close();

    $routinesTotal = (int) ($routStats['total'] ?? 0);
    $routinesCompleted = (int) ($routStats['completed_today'] ?? 0);
    $routineCompletionRate = $routinesTotal > 0 ? (int) round(($routinesCompleted / $routinesTotal) * 100) : 0;

    // 4. Journal Entries Total
    $jStmt = $db->prepare('SELECT COUNT(*) FROM journal_entries WHERE user_id = ?');
    $jStmt->bind_param('i', $userId);
    $jStmt->execute();
    $journalCount = (int) $jStmt->get_result()->fetch_row()[0];
    $jStmt->close();

    // 5. Recent Activity (last 6 records)
    $actStmt = $db->prepare('
        SELECT module, action, description, status, created_at
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 6
    ');
    $actStmt->bind_param('i', $userId);
    $actStmt->execute();
    $recentActivity = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $actStmt->close();

    // 6. Upcoming Tasks (due today or in future)
    $upStmt = $db->prepare('
        SELECT t.*, c.name AS category_name, c.color AS category_color
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = ? AND t.status = "pending" AND t.due_date IS NOT NULL AND t.due_date >= ?
        ORDER BY t.due_date ASC, t.id DESC
        LIMIT 5
    ');
    $upStmt->bind_param('is', $userId, $today);
    $upStmt->execute();
    $upcomingTasks = $upStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $upStmt->close();

    // 7. Weekly Trend Data (Last 7 Days)
    $weeklyTrends = getWeeklyTrendData($userId, 7);

    return [
        'tasks' => [
            'total'           => $tasksTotal,
            'pending'         => (int) ($taskStats['pending'] ?? 0),
            'completed'       => $tasksCompleted,
            'due_today'       => (int) ($taskStats['due_today'] ?? 0),
            'overdue'         => (int) ($taskStats['overdue'] ?? 0),
            'completion_rate' => $taskCompletionRate,
        ],
        'habits' => [
            'total'           => $habitsTotal,
            'completed_today' => $habitsCompleted,
            'completion_rate' => $habitCompletionRate,
        ],
        'routines' => [
            'total'           => $routinesTotal,
            'completed_today' => $routinesCompleted,
            'completion_rate' => $routineCompletionRate,
        ],
        'journal' => [
            'total' => $journalCount,
        ],
        'recent_activity' => $recentActivity,
        'upcoming_tasks'  => $upcomingTasks,
        'weekly_trends'   => $weeklyTrends,
    ];
}

/**
 * Calculates daily completion counts for the last N days.
 *
 * @param int $userId
 * @param int $days
 * @return array ['labels' => string[], 'tasks_completed' => int[], 'habits_completed' => int[]]
 */
function getWeeklyTrendData(int $userId, int $days = 7): array
{
    $db = getDb();
    $labels = [];
    $taskData = [];
    $habitData = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = (new DateTime("today -{$i} days"))->format('Y-m-d');
        $label = (new DateTime("today -{$i} days"))->format('D, M d');
        $labels[] = $label;

        // Tasks completed on this date
        $tStmt = $db->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = "completed" AND DATE(completed_at) = ?');
        $tStmt->bind_param('is', $userId, $date);
        $tStmt->execute();
        $taskData[] = (int) $tStmt->get_result()->fetch_row()[0];
        $tStmt->close();

        // Habit check-ins on this date
        $hStmt = $db->prepare('SELECT COUNT(*) FROM habit_logs WHERE user_id = ? AND log_date = ?');
        $hStmt->bind_param('is', $userId, $date);
        $hStmt->execute();
        $habitData[] = (int) $hStmt->get_result()->fetch_row()[0];
        $hStmt->close();
    }

    return [
        'labels'           => $labels,
        'tasks_completed'  => $taskData,
        'habits_completed' => $habitData,
    ];
}

/**
 * Calculates in-depth reports and statistics over a date range.
 *
 * @param int $userId
 * @param int $days 7|30|90
 * @return array
 */
function getProductivityReport(int $userId, int $days = 30): array
{
    $db = getDb();
    $startDate = (new DateTime("today -{$days} days"))->format('Y-m-d');
    $endDate = date('Y-m-d');

    // 1. Task Breakdown by Priority
    $pStmt = $db->prepare('
        SELECT priority, COUNT(*) AS count
        FROM tasks
        WHERE user_id = ? AND DATE(created_at) >= ?
        GROUP BY priority
    ');
    $pStmt->bind_param('is', $userId, $startDate);
    $pStmt->execute();
    $priorityRows = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pStmt->close();

    $priorityBreakdown = ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0];
    foreach ($priorityRows as $row) {
        $priorityBreakdown[$row['priority']] = (int) $row['count'];
    }

    // 2. Habit Consistency Leaders
    $habStmt = $db->prepare('
        SELECT h.id, h.title, h.color, COUNT(hl.id) AS completions
        FROM habits h
        LEFT JOIN habit_logs hl ON (h.id = hl.habit_id AND hl.log_date >= ?)
        WHERE h.user_id = ? AND h.archived = 0
        GROUP BY h.id
        ORDER BY completions DESC
        LIMIT 5
    ');
    $habStmt->bind_param('si', $startDate, $userId);
    $habStmt->execute();
    $topHabits = $habStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $habStmt->close();

    // 3. Mood Distribution from Journal
    $mStmt = $db->prepare('
        SELECT mood, COUNT(*) AS count
        FROM journal_entries
        WHERE user_id = ? AND entry_date >= ?
        GROUP BY mood
    ');
    $mStmt->bind_param('is', $userId, $startDate);
    $mStmt->execute();
    $moodRows = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mStmt->close();

    $moodDistribution = ['great' => 0, 'good' => 0, 'neutral' => 0, 'bad' => 0, 'awful' => 0];
    foreach ($moodRows as $row) {
        if (isset($moodDistribution[$row['mood']])) {
            $moodDistribution[$row['mood']] = (int) $row['count'];
        }
    }

    // 4. Daily Trend Data
    $trend = getWeeklyTrendData($userId, min($days, 14));

    return [
        'days'               => $days,
        'start_date'         => $startDate,
        'end_date'           => $endDate,
        'priority_breakdown' => $priorityBreakdown,
        'top_habits'         => $topHabits,
        'mood_distribution'  => $moodDistribution,
        'trend'              => $trend,
    ];
}
