<?php
/**
 * Tasks / To-Do List Module
 *
 * Implements CRUD, priority management, due dates, completion toggling,
 * rescheduling, searching, filtering, and audit logging for tasks.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Validates task input data.
 *
 * @param array $data
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateTaskInput(array $data): array
{
    $errors = [];
    $title = trim($data['title'] ?? '');

    if (empty($title)) {
        $errors[] = 'Task title is required.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Task title cannot exceed 255 characters.';
    }

    $validPriorities = ['low', 'medium', 'high', 'urgent'];
    if (!empty($data['priority']) && !in_array($data['priority'], $validPriorities, true)) {
        $errors[] = 'Invalid priority specified.';
    }

    if (!empty($data['due_date'])) {
        $d = DateTime::createFromFormat('Y-m-d', $data['due_date']);
        if (!$d || $d->format('Y-m-d') !== $data['due_date']) {
            $errors[] = 'Due date must be in YYYY-MM-DD format.';
        }
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Retrieves a single task by ID for a user.
 *
 * @param int $taskId
 * @param int $userId
 * @return array|null
 */
function getTaskById(int $taskId, int $userId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('
        SELECT t.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE t.id = ? AND t.user_id = ?
        LIMIT 1
    ');

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $taskId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();
    return $task;
}

/**
 * Retrieves paginated, filterable tasks for a user.
 *
 * @param int $userId
 * @param array $filters ['status' => '', 'priority' => '', 'category_id' => '', 'due' => 'today|upcoming|overdue', 'search' => '']
 * @param int $limit
 * @param int $offset
 * @return array ['tasks' => array, 'total' => int]
 */
function getTasks(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    $db = getDb();

    $where = ['t.user_id = ?'];
    $params = [$userId];
    $types = 'i';

    if (!empty($filters['status'])) {
        $where[] = 't.status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['priority'])) {
        $where[] = 't.priority = ?';
        $params[] = $filters['priority'];
        $types .= 's';
    }

    if (!empty($filters['category_id'])) {
        $where[] = 't.category_id = ?';
        $params[] = (int) $filters['category_id'];
        $types .= 'i';
    }

    if (!empty($filters['due'])) {
        $today = date('Y-m-d');
        if ($filters['due'] === 'today') {
            $where[] = 't.due_date = ?';
            $params[] = $today;
            $types .= 's';
        } elseif ($filters['due'] === 'upcoming') {
            $where[] = 't.due_date > ?';
            $params[] = $today;
            $types .= 's';
        } elseif ($filters['due'] === 'overdue') {
            $where[] = 't.due_date < ? AND t.status = "pending"';
            $params[] = $today;
            $types .= 's';
        }
    }

    if (!empty($filters['search'])) {
        $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countSql = "SELECT COUNT(*) FROM tasks t WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Data query with ordering: pending first, then by due date ascending, then created descending
    $sql = "
        SELECT t.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
        FROM tasks t
        LEFT JOIN categories c ON t.category_id = c.id
        WHERE $whereClause
        ORDER BY
            CASE WHEN t.status = 'pending' THEN 0 ELSE 1 END,
            CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
            t.due_date ASC,
            t.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $db->prepare($sql);
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['tasks' => $tasks, 'total' => (int) $total];
}

/**
 * Creates a new task.
 *
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createTask(int $userId, array $data): array
{
    $validation = validateTaskInput($data);
    if (!$validation['valid']) {
        return ['success' => false, 'id' => null, 'error' => implode(' ', $validation['errors'])];
    }

    $title = trim($data['title']);
    $description = !empty($data['description']) ? trim($data['description']) : null;
    $priority = $data['priority'] ?? 'medium';
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
    $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
    $dueTime = !empty($data['due_time']) ? $data['due_time'] : null;

    $db = getDb();
    $stmt = $db->prepare('
        INSERT INTO tasks (user_id, category_id, title, description, priority, status, due_date, due_time, created_at)
        VALUES (?, ?, ?, ?, ?, "pending", ?, ?, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('iisssss', $userId, $categoryId, $title, $description, $priority, $dueDate, $dueTime);
    if ($stmt->execute()) {
        $taskId = (int) $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'tasks', 'create', "Created task '{$title}'", 'task', $taskId);
        return ['success' => true, 'id' => $taskId, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'id' => null, 'error' => $error];
}

/**
 * Updates an existing task.
 *
 * @param int $taskId
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateTask(int $taskId, int $userId, array $data): array
{
    $task = getTaskById($taskId, $userId);
    if (!$task) {
        return ['success' => false, 'error' => 'Task not found.'];
    }

    $validation = validateTaskInput($data);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => implode(' ', $validation['errors'])];
    }

    $title = trim($data['title']);
    $description = !empty($data['description']) ? trim($data['description']) : null;
    $priority = $data['priority'] ?? 'medium';
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
    $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
    $dueTime = !empty($data['due_time']) ? $data['due_time'] : null;

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE tasks
        SET title = ?, description = ?, priority = ?, category_id = ?, due_date = ?, due_time = ?
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('sssisssi', $title, $description, $priority, $categoryId, $dueDate, $dueTime, $taskId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'tasks', 'update', "Updated task '{$title}'", 'task', $taskId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Marks a task as completed.
 *
 * @param int $taskId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function completeTask(int $taskId, int $userId): array
{
    $task = getTaskById($taskId, $userId);
    if (!$task) {
        return ['success' => false, 'error' => 'Task not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE tasks
        SET status = "completed", completed_at = UTC_TIMESTAMP()
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $taskId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'tasks', 'complete', "Completed task '{$task['title']}'", 'task', $taskId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Reopens a completed task.
 *
 * @param int $taskId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function reopenTask(int $taskId, int $userId): array
{
    $task = getTaskById($taskId, $userId);
    if (!$task) {
        return ['success' => false, 'error' => 'Task not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE tasks
        SET status = "pending", completed_at = NULL
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $taskId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'tasks', 'reopen', "Reopened task '{$task['title']}'", 'task', $taskId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Reschedules a task to a new due date and time.
 *
 * @param int $taskId
 * @param int $userId
 * @param string|null $newDate
 * @param string|null $newTime
 * @return array ['success' => bool, 'error' => string|null]
 */
function rescheduleTask(int $taskId, int $userId, ?string $newDate, ?string $newTime = null): array
{
    $task = getTaskById($taskId, $userId);
    if (!$task) {
        return ['success' => false, 'error' => 'Task not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE tasks
        SET due_date = ?, due_time = ?
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ssii', $newDate, $newTime, $taskId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        $dateLabel = $newDate ? formatDate($newDate) : 'no date';
        logActivity($userId, 'tasks', 'reschedule', "Rescheduled task '{$task['title']}' to {$dateLabel}", 'task', $taskId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Deletes a task.
 *
 * @param int $taskId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteTask(int $taskId, int $userId): array
{
    $task = getTaskById($taskId, $userId);
    if (!$task) {
        return ['success' => false, 'error' => 'Task not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $taskId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'tasks', 'delete', "Deleted task '{$task['title']}'", 'task', $taskId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}
