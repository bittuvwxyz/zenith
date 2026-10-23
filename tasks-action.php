<?php
/**
 * Tasks Action Controller
 *
 * Handles task creation, updates, completion, reopening, rescheduling, and deletion.
 * Supports both AJAX requests and standard POST form submissions.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tasks.php';

$user = requireAuth();
$userId = (int) $user['id'];

if (!isPost()) {
    redirect('todos.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired security token.'], 403);
    }
    setFlash('danger', 'Security verification failed. Please try again.');
    redirect('todos.php');
}

$action = $_POST['action'] ?? '';

// 1. Toggle Completion
if ($action === 'complete' || $action === 'reopen') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $result = ($action === 'complete') ? completeTask($taskId, $userId) : reopenTask($taskId, $userId);

    if (isAjax()) {
        jsonResponse([
            'success' => $result['success'],
            'status'  => ($action === 'complete') ? 'completed' : 'pending',
            'error'   => $result['error'] ?? null
        ]);
    }

    if ($result['success']) {
        setFlash('success', ($action === 'complete') ? 'Task completed!' : 'Task reopened.');
    } else {
        setFlash('danger', $result['error'] ?? 'Action failed.');
    }
    redirect('todos.php');
}

// 2. Create Task
if ($action === 'create') {
    $result = createTask($userId, [
        'title'       => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? null,
        'priority'    => $_POST['priority'] ?? 'medium',
        'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'due_date'    => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'due_time'    => !empty($_POST['due_time']) ? $_POST['due_time'] : null,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Task created successfully.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to create task.');
    }
    redirect('todos.php');
}

// 3. Update Task
if ($action === 'update') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $result = updateTask($taskId, $userId, [
        'title'       => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? null,
        'priority'    => $_POST['priority'] ?? 'medium',
        'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'due_date'    => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'due_time'    => !empty($_POST['due_time']) ? $_POST['due_time'] : null,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Task updated successfully.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to update task.');
    }
    redirect('todos.php');
}

// 4. Reschedule Task
if ($action === 'reschedule') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $newDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $newTime = !empty($_POST['due_time']) ? $_POST['due_time'] : null;

    $result = rescheduleTask($taskId, $userId, $newDate, $newTime);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Task rescheduled.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to reschedule.');
    }
    redirect('todos.php');
}

// 5. Delete Task
if ($action === 'delete') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $result = deleteTask($taskId, $userId);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Task deleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to delete task.');
    }
    redirect('todos.php');
}

redirect('todos.php');
