<?php
/**
 * Categories Management Module
 *
 * Provides CRUD operations for user categories.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Retrieves all categories for a user.
 *
 * @param int $userId
 * @return array
 */
function getCategories(int $userId): array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $categories;
}

/**
 * Retrieves a single category by ID.
 *
 * @param int $categoryId
 * @param int $userId
 * @return array|null
 */
function getCategoryById(int $categoryId, int $userId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $categoryId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    $stmt->close();
    return $category;
}

/**
 * Creates a new category.
 *
 * @param int $userId
 * @param string $name
 * @param string $color
 * @param string $icon
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createCategory(int $userId, string $name, string $color = '#6366f1', string $icon = 'folder'): array
{
    $cleanName = trim($name);
    if (empty($cleanName)) {
        return ['success' => false, 'id' => null, 'error' => 'Category name cannot be empty.'];
    }

    $db = getDb();
    $stmt = $db->prepare('INSERT INTO categories (user_id, name, color, icon, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('isss', $userId, $cleanName, $color, $icon);
    if ($stmt->execute()) {
        $insertId = (int) $stmt->insert_id;
        $stmt->close();
        logActivity($userId, 'categories', 'create', "Created category '{$cleanName}'", 'category', $insertId);
        return ['success' => true, 'id' => $insertId, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'id' => null, 'error' => $error];
}

/**
 * Updates an existing category.
 *
 * @param int $categoryId
 * @param int $userId
 * @param string $name
 * @param string $color
 * @param string $icon
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateCategory(int $categoryId, int $userId, string $name, string $color = '#6366f1', string $icon = 'folder'): array
{
    $cleanName = trim($name);
    if (empty($cleanName)) {
        return ['success' => false, 'error' => 'Category name cannot be empty.'];
    }

    $db = getDb();
    $stmt = $db->prepare('UPDATE categories SET name = ?, color = ?, icon = ? WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('sssii', $cleanName, $color, $icon, $categoryId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'categories', 'update', "Updated category #{$categoryId} to '{$cleanName}'", 'category', $categoryId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Deletes a category.
 *
 * @param int $categoryId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteCategory(int $categoryId, int $userId): array
{
    $category = getCategoryById($categoryId, $userId);
    if (!$category) {
        return ['success' => false, 'error' => 'Category not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $categoryId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'categories', 'delete', "Deleted category '{$category['name']}'", 'category', $categoryId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}
