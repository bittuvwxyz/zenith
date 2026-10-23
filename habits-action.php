<?php
/**
 * Habits Action Controller
 *
 * Handles habit creation, updates, completion toggle, template adoption, and deletion.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/habits.php';

$user = requireAuth();
$userId = (int) $user['id'];

if (!isPost()) {
    redirect('habits.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired security token.'], 403);
    }
    setFlash('danger', 'Security verification failed.');
    redirect('habits.php');
}

$action = $_POST['action'] ?? '';

// 1. Toggle Habit Check-in
if ($action === 'toggle') {
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $date = !empty($_POST['date']) ? $_POST['date'] : null;

    $result = toggleHabitCompletion($habitId, $userId, $date);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', $result['completed'] ? 'Habit marked as completed!' : 'Habit uncompleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Action failed.');
    }
    redirect('habits.php');
}

// 2. Create Habit
if ($action === 'create') {
    $result = createHabit($userId, [
        'title'            => $_POST['title'] ?? '',
        'description'      => $_POST['description'] ?? null,
        'target_frequency' => $_POST['target_frequency'] ?? 'daily',
        'category_id'      => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'color'            => $_POST['color'] ?? '#22c55e',
        'icon'             => $_POST['icon'] ?? 'check-circle',
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Habit added successfully.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to add habit.');
    }
    redirect('habits.php');
}

// 3. Update Habit
if ($action === 'update') {
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $result = updateHabit($habitId, $userId, [
        'title'            => $_POST['title'] ?? '',
        'description'      => $_POST['description'] ?? null,
        'target_frequency' => $_POST['target_frequency'] ?? 'daily',
        'category_id'      => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
        'color'            => $_POST['color'] ?? '#22c55e',
        'icon'             => $_POST['icon'] ?? 'check-circle',
        'archived'         => !empty($_POST['archived']) ? 1 : 0,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Habit updated.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to update habit.');
    }
    redirect('habits.php');
}

// 4. Adopt Template
if ($action === 'adopt_template') {
    $templateIndex = (int) ($_POST['template_index'] ?? 0);
    $templates = getHabitTemplates();

    if (isset($templates[$templateIndex])) {
        $tpl = $templates[$templateIndex];
        $result = createHabit($userId, $tpl);
        if ($result['success']) {
            setFlash('success', "Habit '{$tpl['title']}' added to your list!");
        } else {
            setFlash('danger', 'Failed to adopt habit template.');
        }
    } else {
        setFlash('danger', 'Invalid template selected.');
    }
    redirect('habits.php');
}

// 5. Delete Habit
if ($action === 'delete') {
    $habitId = (int) ($_POST['habit_id'] ?? 0);
    $result = deleteHabit($habitId, $userId);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Habit deleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to delete habit.');
    }
    redirect('habits.php');
}

redirect('habits.php');
