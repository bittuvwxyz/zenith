<?php
/**
 * Habits Management Module
 *
 * Implements habit creation, templates, streak tracking, daily check-ins,
 * progress calculations, and audit logging.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Returns pre-built habit templates for quick onboarding.
 *
 * @return array
 */
function getHabitTemplates(): array
{
    return [
        [
            'title' => 'Hydrate (Drink 2.5L Water)',
            'description' => 'Stay sharp, energized, and properly hydrated throughout the day.',
            'target_frequency' => 'daily',
            'color' => '#06b6d4',
            'icon' => 'check-circle'
        ],
        [
            'title' => 'Morning 30-Min Workout',
            'description' => 'Cardio, strength training, or stretching to boost morning energy.',
            'target_frequency' => 'daily',
            'color' => '#10b981',
            'icon' => 'flame'
        ],
        [
            'title' => 'Read 20 Pages',
            'description' => 'Read non-fiction, industry books, or literature daily.',
            'target_frequency' => 'daily',
            'color' => '#6366f1',
            'icon' => 'book'
        ],
        [
            'title' => 'Daily Journal & Reflection',
            'description' => 'Record wins, lessons, and thoughts before bed.',
            'target_frequency' => 'daily',
            'color' => '#f59e0b',
            'icon' => 'edit'
        ],
        [
            'title' => '10-Minute Mindfulness Meditation',
            'description' => 'Calm the mind, reduce stress, and improve mental clarity.',
            'target_frequency' => 'daily',
            'color' => '#ec4899',
            'icon' => 'clock'
        ]
    ];
}

/**
 * Retrieves a single habit by ID.
 *
 * @param int $habitId
 * @param int $userId
 * @return array|null
 */
function getHabitById(int $habitId, int $userId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('
        SELECT h.*, c.name AS category_name, c.color AS category_color
        FROM habits h
        LEFT JOIN categories c ON h.category_id = c.id
        WHERE h.id = ? AND h.user_id = ?
        LIMIT 1
    ');

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $habitId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $habit = $result->fetch_assoc();
    $stmt->close();
    return $habit;
}

/**
 * Retrieves all habits for a user with streak & today status.
 *
 * @param int $userId
 * @param bool $includeArchived
 * @param string|null $date Date for completion status (defaults to today)
 * @return array
 */
function getHabits(int $userId, bool $includeArchived = false, ?string $date = null): array
{
    $db = getDb();
    $checkDate = $date ?: date('Y-m-d');

    $sql = '
        SELECT h.*,
               c.name AS category_name, c.color AS category_color,
               IF(hl.id IS NOT NULL, 1, 0) AS is_completed_today
        FROM habits h
        LEFT JOIN categories c ON h.category_id = c.id
        LEFT JOIN habit_logs hl ON (h.id = hl.habit_id AND hl.log_date = ?)
        WHERE h.user_id = ?
    ';

    if (!$includeArchived) {
        $sql .= ' AND h.archived = 0';
    }

    $sql .= ' ORDER BY h.id ASC';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('si', $checkDate, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $habits = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Attach streaks
    foreach ($habits as &$habit) {
        $habit['streak'] = calculateHabitStreak((int) $habit['id'], $userId);
        $habit['total_completions'] = getHabitTotalCompletions((int) $habit['id']);
    }

    return $habits;
}

/**
 * Calculates current streak (consecutive days) for a habit.
 *
 * @param int $habitId
 * @param int $userId
 * @return int Consecutive days completed
 */
function calculateHabitStreak(int $habitId, int $userId): int
{
    $db = getDb();
    $stmt = $db->prepare('
        SELECT log_date
        FROM habit_logs
        WHERE habit_id = ? AND user_id = ?
        ORDER BY log_date DESC
    ');

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('ii', $habitId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dates = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($dates)) {
        return 0;
    }

    $streak = 0;
    $today = new DateTime('today');
    $yesterday = (new DateTime('today'))->modify('-1 day');

    $mostRecentDate = new DateTime($dates[0]['log_date']);

    // If most recent is neither today nor yesterday, streak is broken
    if ($mostRecentDate != $today && $mostRecentDate != $yesterday) {
        return 0;
    }

    $expectedDate = $mostRecentDate;
    foreach ($dates as $row) {
        $currentDate = new DateTime($row['log_date']);
        if ($currentDate == $expectedDate) {
            $streak++;
            $expectedDate = (clone $expectedDate)->modify('-1 day');
        } else {
            break;
        }
    }

    return $streak;
}

/**
 * Returns total completion count for a habit.
 *
 * @param int $habitId
 * @return int
 */
function getHabitTotalCompletions(int $habitId): int
{
    $db = getDb();
    $stmt = $db->prepare('SELECT COUNT(*) FROM habit_logs WHERE habit_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $habitId);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count;
}

/**
 * Creates a new habit.
 *
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createHabit(int $userId, array $data): array
{
    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        return ['success' => false, 'id' => null, 'error' => 'Habit title is required.'];
    }

    $description = !empty($data['description']) ? trim($data['description']) : null;
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
    $targetFrequency = $data['target_frequency'] ?? 'daily';
    $color = $data['color'] ?? '#22c55e';
    $icon = $data['icon'] ?? 'check-circle';

    $db = getDb();
    $stmt = $db->prepare('
        INSERT INTO habits (user_id, category_id, title, description, target_frequency, icon, color, archived, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('iisssss', $userId, $categoryId, $title, $description, $targetFrequency, $icon, $color);
    if ($stmt->execute()) {
        $habitId = (int) $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'habits', 'create', "Created habit '{$title}'", 'habit', $habitId);
        return ['success' => true, 'id' => $habitId, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'id' => null, 'error' => $error];
}

/**
 * Updates a habit.
 *
 * @param int $habitId
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateHabit(int $habitId, int $userId, array $data): array
{
    $habit = getHabitById($habitId, $userId);
    if (!$habit) {
        return ['success' => false, 'error' => 'Habit not found.'];
    }

    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        return ['success' => false, 'error' => 'Habit title is required.'];
    }

    $description = !empty($data['description']) ? trim($data['description']) : null;
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
    $targetFrequency = $data['target_frequency'] ?? 'daily';
    $color = $data['color'] ?? '#22c55e';
    $icon = $data['icon'] ?? 'check-circle';
    $archived = !empty($data['archived']) ? 1 : 0;

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE habits
        SET title = ?, description = ?, category_id = ?, target_frequency = ?, icon = ?, color = ?, archived = ?
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ssisssiii', $title, $description, $categoryId, $targetFrequency, $icon, $color, $archived, $habitId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'habits', 'update', "Updated habit '{$title}'", 'habit', $habitId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Toggles a habit's completion status for a specific date (atomically).
 *
 * @param int $habitId
 * @param int $userId
 * @param string|null $date Defaults to today
 * @return array ['success' => bool, 'completed' => bool, 'streak' => int, 'error' => string|null]
 */
function toggleHabitCompletion(int $habitId, int $userId, ?string $date = null): array
{
    $habit = getHabitById($habitId, $userId);
    if (!$habit) {
        return ['success' => false, 'completed' => false, 'streak' => 0, 'error' => 'Habit not found.'];
    }

    $logDate = $date ?: date('Y-m-d');
    $db = getDb();

    // Check if currently completed
    $check = $db->prepare('SELECT id FROM habit_logs WHERE habit_id = ? AND log_date = ? LIMIT 1');
    $check->bind_param('is', $habitId, $logDate);
    $check->execute();
    $res = $check->get_result();
    $existing = $res->fetch_assoc();
    $check->close();

    $db->begin_transaction();
    try {
        if ($existing) {
            // Uncomplete
            $del = $db->prepare('DELETE FROM habit_logs WHERE id = ?');
            $del->bind_param('i', $existing['id']);
            $del->execute();
            $del->close();
            $completed = false;
            logActivity($userId, 'habits', 'uncheck', "Unchecked habit '{$habit['title']}' for {$logDate}", 'habit', $habitId);
        } else {
            // Complete
            $ins = $db->prepare('INSERT INTO habit_logs (habit_id, user_id, log_date, completed, created_at) VALUES (?, ?, ?, 1, UTC_TIMESTAMP())');
            $ins->bind_param('iis', $habitId, $userId, $logDate);
            $ins->execute();
            $ins->close();
            $completed = true;
            logActivity($userId, 'habits', 'check', "Completed habit '{$habit['title']}' for {$logDate}", 'habit', $habitId);
        }

        $db->commit();
        $streak = calculateHabitStreak($habitId, $userId);

        return ['success' => true, 'completed' => $completed, 'streak' => $streak, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'completed' => false, 'streak' => 0, 'error' => 'Failed to update habit status.'];
    }
}

/**
 * Deletes a habit.
 *
 * @param int $habitId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteHabit(int $habitId, int $userId): array
{
    $habit = getHabitById($habitId, $userId);
    if (!$habit) {
        return ['success' => false, 'error' => 'Habit not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('DELETE FROM habits WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $habitId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'habits', 'delete', "Deleted habit '{$habit['title']}'", 'habit', $habitId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}
