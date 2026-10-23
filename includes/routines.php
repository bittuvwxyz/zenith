<?php
/**
 * Daily Routine Management Module
 *
 * Implements routine creation, time block categorization (morning/afternoon/evening/night),
 * order management, daily check-ins, and progress calculations.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Retrieves routines for a user, optionally filtered by time block.
 *
 * @param int $userId
 * @param string|null $timeBlock morning|afternoon|evening|night
 * @param string|null $date Date for completion status (defaults to today)
 * @return array
 */
function getRoutines(int $userId, ?string $timeBlock = null, ?string $date = null): array
{
    $db = getDb();
    $checkDate = $date ?: date('Y-m-d');

    $sql = '
        SELECT r.*,
               IF(rl.id IS NOT NULL, 1, 0) AS is_completed_today
        FROM routines r
        LEFT JOIN routine_logs rl ON (r.id = rl.routine_id AND rl.log_date = ?)
        WHERE r.user_id = ?
    ';

    $params = [$checkDate, $userId];
    $types = 'si';

    if (!empty($timeBlock)) {
        $sql .= ' AND r.time_block = ?';
        $params[] = $timeBlock;
        $types .= 's';
    }

    $sql .= ' ORDER BY r.time_block ASC, r.order_num ASC, r.id ASC';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $routines = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $routines;
}

/**
 * Retrieves a single routine by ID.
 *
 * @param int $routineId
 * @param int $userId
 * @return array|null
 */
function getRoutineById(int $routineId, int $userId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM routines WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $routineId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $routine = $result->fetch_assoc();
    $stmt->close();
    return $routine;
}

/**
 * Calculates routine completion progress for a given date.
 *
 * @param int $userId
 * @param string|null $date
 * @return array ['total' => int, 'completed' => int, 'percent' => int]
 */
function getDailyRoutineProgress(int $userId, ?string $date = null): array
{
    $db = getDb();
    $checkDate = $date ?: date('Y-m-d');

    // Total routines
    $totStmt = $db->prepare('SELECT COUNT(*) FROM routines WHERE user_id = ?');
    $totStmt->bind_param('i', $userId);
    $totStmt->execute();
    $total = (int) $totStmt->get_result()->fetch_row()[0];
    $totStmt->close();

    if ($total === 0) {
        return ['total' => 0, 'completed' => 0, 'percent' => 0];
    }

    // Completed today
    $compStmt = $db->prepare('
        SELECT COUNT(*)
        FROM routine_logs rl
        JOIN routines r ON rl.routine_id = r.id
        WHERE rl.user_id = ? AND rl.log_date = ?
    ');
    $compStmt->bind_param('is', $userId, $checkDate);
    $compStmt->execute();
    $completed = (int) $compStmt->get_result()->fetch_row()[0];
    $compStmt->close();

    $percent = (int) round(($completed / $total) * 100);

    return [
        'total'     => $total,
        'completed' => $completed,
        'percent'   => min(100, $percent)
    ];
}

/**
 * Creates a new routine item.
 *
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createRoutine(int $userId, array $data): array
{
    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        return ['success' => false, 'id' => null, 'error' => 'Routine title is required.'];
    }

    $timeBlock = $data['time_block'] ?? 'morning';
    $validBlocks = ['morning', 'afternoon', 'evening', 'night'];
    if (!in_array($timeBlock, $validBlocks, true)) {
        $timeBlock = 'morning';
    }

    $estimatedMinutes = !empty($data['estimated_minutes']) ? (int) $data['estimated_minutes'] : 15;
    $orderNum = !empty($data['order_num']) ? (int) $data['order_num'] : 0;

    $db = getDb();
    $stmt = $db->prepare('
        INSERT INTO routines (user_id, title, time_block, estimated_minutes, order_num, created_at)
        VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('issii', $userId, $title, $timeBlock, $estimatedMinutes, $orderNum);
    if ($stmt->execute()) {
        $routineId = (int) $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'routines', 'create', "Created routine '{$title}' in {$timeBlock}", 'routine', $routineId);
        return ['success' => true, 'id' => $routineId, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'id' => null, 'error' => $error];
}

/**
 * Updates an existing routine.
 *
 * @param int $routineId
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateRoutine(int $routineId, int $userId, array $data): array
{
    $routine = getRoutineById($routineId, $userId);
    if (!$routine) {
        return ['success' => false, 'error' => 'Routine not found.'];
    }

    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        return ['success' => false, 'error' => 'Routine title is required.'];
    }

    $timeBlock = $data['time_block'] ?? 'morning';
    $validBlocks = ['morning', 'afternoon', 'evening', 'night'];
    if (!in_array($timeBlock, $validBlocks, true)) {
        $timeBlock = 'morning';
    }

    $estimatedMinutes = !empty($data['estimated_minutes']) ? (int) $data['estimated_minutes'] : 15;
    $orderNum = !empty($data['order_num']) ? (int) $data['order_num'] : 0;

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE routines
        SET title = ?, time_block = ?, estimated_minutes = ?, order_num = ?
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ssiiii', $title, $timeBlock, $estimatedMinutes, $orderNum, $routineId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'routines', 'update', "Updated routine '{$title}'", 'routine', $routineId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Toggles a routine's completion for a given date.
 *
 * @param int $routineId
 * @param int $userId
 * @param string|null $date
 * @return array ['success' => bool, 'completed' => bool, 'error' => string|null]
 */
function toggleRoutineCompletion(int $routineId, int $userId, ?string $date = null): array
{
    $routine = getRoutineById($routineId, $userId);
    if (!$routine) {
        return ['success' => false, 'completed' => false, 'error' => 'Routine not found.'];
    }

    $logDate = $date ?: date('Y-m-d');
    $db = getDb();

    $check = $db->prepare('SELECT id FROM routine_logs WHERE routine_id = ? AND log_date = ? LIMIT 1');
    $check->bind_param('is', $routineId, $logDate);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    $db->begin_transaction();
    try {
        if ($existing) {
            $del = $db->prepare('DELETE FROM routine_logs WHERE id = ?');
            $del->bind_param('i', $existing['id']);
            $del->execute();
            $del->close();
            $completed = false;
            logActivity($userId, 'routines', 'uncheck', "Unchecked routine '{$routine['title']}' for {$logDate}", 'routine', $routineId);
        } else {
            $ins = $db->prepare('INSERT INTO routine_logs (routine_id, user_id, log_date, completed, completed_at) VALUES (?, ?, ?, 1, UTC_TIMESTAMP())');
            $ins->bind_param('iis', $routineId, $userId, $logDate);
            $ins->execute();
            $ins->close();
            $completed = true;
            logActivity($userId, 'routines', 'check', "Completed routine '{$routine['title']}' for {$logDate}", 'routine', $routineId);
        }

        $db->commit();
        return ['success' => true, 'completed' => $completed, 'error' => null];
    } catch (Throwable $e) {
        $db->rollback();
        return ['success' => false, 'completed' => false, 'error' => 'Failed to update routine status.'];
    }
}

/**
 * Deletes a routine item.
 *
 * @param int $routineId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteRoutine(int $routineId, int $userId): array
{
    $routine = getRoutineById($routineId, $userId);
    if (!$routine) {
        return ['success' => false, 'error' => 'Routine not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('DELETE FROM routines WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $routineId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'routines', 'delete', "Deleted routine '{$routine['title']}'", 'routine', $routineId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}
