<?php
/**
 * Routine Action Controller
 *
 * Handles routine creation, updates, completion toggle, and deletion.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/routines.php';

$user = requireAuth();
$userId = (int) $user['id'];

if (!isPost()) {
    redirect('routine.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired security token.'], 403);
    }
    setFlash('danger', 'Security verification failed.');
    redirect('routine.php');
}

$action = $_POST['action'] ?? '';

// 1. Toggle Routine Step
if ($action === 'toggle') {
    $routineId = (int) ($_POST['routine_id'] ?? 0);
    $date = !empty($_POST['date']) ? $_POST['date'] : null;

    $result = toggleRoutineCompletion($routineId, $userId, $date);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', $result['completed'] ? 'Routine step checked!' : 'Routine step unchecked.');
    } else {
        setFlash('danger', $result['error'] ?? 'Action failed.');
    }
    redirect('routine.php');
}

// 2. Create Routine
if ($action === 'create') {
    $result = createRoutine($userId, [
        'title'             => $_POST['title'] ?? '',
        'time_block'        => $_POST['time_block'] ?? 'morning',
        'estimated_minutes' => !empty($_POST['estimated_minutes']) ? (int) $_POST['estimated_minutes'] : 15,
        'order_num'         => !empty($_POST['order_num']) ? (int) $_POST['order_num'] : 0,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Routine step created.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to create routine.');
    }
    redirect('routine.php');
}

// 3. Update Routine
if ($action === 'update') {
    $routineId = (int) ($_POST['routine_id'] ?? 0);
    $result = updateRoutine($routineId, $userId, [
        'title'             => $_POST['title'] ?? '',
        'time_block'        => $_POST['time_block'] ?? 'morning',
        'estimated_minutes' => !empty($_POST['estimated_minutes']) ? (int) $_POST['estimated_minutes'] : 15,
        'order_num'         => !empty($_POST['order_num']) ? (int) $_POST['order_num'] : 0,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Routine updated.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to update routine.');
    }
    redirect('routine.php');
}

// 4. Delete Routine
if ($action === 'delete') {
    $routineId = (int) ($_POST['routine_id'] ?? 0);
    $result = deleteRoutine($routineId, $userId);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Routine deleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to delete routine.');
    }
    redirect('routine.php');
}

redirect('routine.php');
