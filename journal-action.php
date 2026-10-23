<?php
/**
 * Daily Journal Action Controller
 *
 * Handles creation, updates, and deletion of journal entries.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/journal.php';

$user = requireAuth();
$userId = (int) $user['id'];

if (!isPost()) {
    redirect('journal.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired security token.'], 403);
    }
    setFlash('danger', 'Security verification failed.');
    redirect('journal.php');
}

$action = $_POST['action'] ?? '';

// 1. Create Journal Entry
if ($action === 'create') {
    $result = createJournalEntry($userId, [
        'title'       => $_POST['title'] ?? '',
        'content'     => $_POST['content'] ?? '',
        'entry_date'  => !empty($_POST['entry_date']) ? $_POST['entry_date'] : date('Y-m-d'),
        'mood'        => $_POST['mood'] ?? 'neutral',
        'tags'        => $_POST['tags'] ?? null,
        'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Journal entry saved securely.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to save entry.');
    }
    redirect('journal.php');
}

// 2. Update Journal Entry
if ($action === 'update') {
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    $result = updateJournalEntry($entryId, $userId, [
        'title'       => $_POST['title'] ?? '',
        'content'     => $_POST['content'] ?? '',
        'entry_date'  => !empty($_POST['entry_date']) ? $_POST['entry_date'] : null,
        'mood'        => $_POST['mood'] ?? 'neutral',
        'tags'        => $_POST['tags'] ?? null,
        'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
    ]);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Journal entry updated.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to update entry.');
    }
    redirect('journal.php');
}

// 3. Delete Journal Entry
if ($action === 'delete') {
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    $result = deleteJournalEntry($entryId, $userId);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Journal entry deleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to delete entry.');
    }
    redirect('journal.php');
}

redirect('journal.php');
