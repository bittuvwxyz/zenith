<?php
/**
 * Categories Action Controller
 *
 * Handles creation, updates, and deletion of custom categories.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/categories.php';

$user = requireAuth();
$userId = (int) $user['id'];

if (!isPost()) {
    redirect('categories.php');
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    if (isAjax()) {
        jsonResponse(['success' => false, 'error' => 'Invalid or expired security token.'], 403);
    }
    setFlash('danger', 'Security verification failed.');
    redirect('categories.php');
}

$action = $_POST['action'] ?? '';

// 1. Create Category
if ($action === 'create') {
    $result = createCategory(
        $userId,
        $_POST['name'] ?? '',
        $_POST['color'] ?? '#6366f1',
        $_POST['icon'] ?? 'folder'
    );

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Category created successfully.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to create category.');
    }
    redirect('categories.php');
}

// 2. Update Category
if ($action === 'update') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $result = updateCategory(
        $categoryId,
        $userId,
        $_POST['name'] ?? '',
        $_POST['color'] ?? '#6366f1',
        $_POST['icon'] ?? 'folder'
    );

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Category updated.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to update category.');
    }
    redirect('categories.php');
}

// 3. Delete Category
if ($action === 'delete') {
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $result = deleteCategory($categoryId, $userId);

    if (isAjax()) {
        jsonResponse($result);
    }

    if ($result['success']) {
        setFlash('success', 'Category deleted.');
    } else {
        setFlash('danger', $result['error'] ?? 'Failed to delete category.');
    }
    redirect('categories.php');
}

redirect('categories.php');
