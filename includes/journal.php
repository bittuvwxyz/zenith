<?php
/**
 * Daily Journal Management Module
 *
 * Implements private journaling with mood tracking, tags, categories,
 * searching, filtering, and privacy-protected audit logging.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/audit.php';

/**
 * Retrieves a single journal entry by ID for a user.
 *
 * @param int $entryId
 * @param int $userId
 * @return array|null
 */
function getJournalEntryById(int $entryId, int $userId): ?array
{
    $db = getDb();
    $stmt = $db->prepare('
        SELECT j.*, c.name AS category_name, c.color AS category_color
        FROM journal_entries j
        LEFT JOIN categories c ON j.category_id = c.id
        WHERE j.id = ? AND j.user_id = ?
        LIMIT 1
    ');

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $entryId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $entry = $result->fetch_assoc();
    $stmt->close();
    return $entry;
}

/**
 * Retrieves paginated, filterable journal entries for a user.
 *
 * @param int $userId
 * @param array $filters ['mood' => '', 'category_id' => '', 'search' => '', 'date' => '']
 * @param int $limit
 * @param int $offset
 * @return array ['entries' => array, 'total' => int]
 */
function getJournalEntries(int $userId, array $filters = [], int $limit = 10, int $offset = 0): array
{
    $db = getDb();

    $where = ['j.user_id = ?'];
    $params = [$userId];
    $types = 'i';

    if (!empty($filters['mood'])) {
        $where[] = 'j.mood = ?';
        $params[] = $filters['mood'];
        $types .= 's';
    }

    if (!empty($filters['category_id'])) {
        $where[] = 'j.category_id = ?';
        $params[] = (int) $filters['category_id'];
        $types .= 'i';
    }

    if (!empty($filters['date'])) {
        $where[] = 'j.entry_date = ?';
        $params[] = $filters['date'];
        $types .= 's';
    }

    if (!empty($filters['search'])) {
        $where[] = '(j.title LIKE ? OR j.tags LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countSql = "SELECT COUNT(*) FROM journal_entries j WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Data query: newest entry date first
    $sql = "
        SELECT j.*, c.name AS category_name, c.color AS category_color
        FROM journal_entries j
        LEFT JOIN categories c ON j.category_id = c.id
        WHERE $whereClause
        ORDER BY j.entry_date DESC, j.id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $db->prepare($sql);
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['entries' => $entries, 'total' => (int) $total];
}

/**
 * Creates a new journal entry.
 * Note: Full private content is never stored in audit logs.
 *
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function createJournalEntry(int $userId, array $data): array
{
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $entryDate = !empty($data['entry_date']) ? $data['entry_date'] : date('Y-m-d');
    $mood = $data['mood'] ?? 'neutral';
    $tags = !empty($data['tags']) ? trim($data['tags']) : null;
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

    if (empty($title)) {
        return ['success' => false, 'id' => null, 'error' => 'Entry title is required.'];
    }
    if (empty($content)) {
        return ['success' => false, 'id' => null, 'error' => 'Journal content cannot be empty.'];
    }

    $validMoods = ['great', 'good', 'neutral', 'bad', 'awful'];
    if (!in_array($mood, $validMoods, true)) {
        $mood = 'neutral';
    }

    $db = getDb();
    $stmt = $db->prepare('
        INSERT INTO journal_entries (user_id, category_id, entry_date, title, content, mood, tags, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        return ['success' => false, 'id' => null, 'error' => 'Database error.'];
    }

    $stmt->bind_param('iisssss', $userId, $categoryId, $entryDate, $title, $content, $mood, $tags);
    if ($stmt->execute()) {
        $entryId = (int) $stmt->insert_id;
        $stmt->close();

        // Privacy: only log metadata, never full journal content
        logActivity($userId, 'journal', 'create', "Created journal entry '{$title}' for {$entryDate}", 'journal', $entryId, [
            'title' => $title,
            'mood' => $mood,
            'entry_date' => $entryDate
        ]);

        return ['success' => true, 'id' => $entryId, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'id' => null, 'error' => $error];
}

/**
 * Updates an existing journal entry.
 *
 * @param int $entryId
 * @param int $userId
 * @param array $data
 * @return array ['success' => bool, 'error' => string|null]
 */
function updateJournalEntry(int $entryId, int $userId, array $data): array
{
    $entry = getJournalEntryById($entryId, $userId);
    if (!$entry) {
        return ['success' => false, 'error' => 'Journal entry not found.'];
    }

    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $entryDate = !empty($data['entry_date']) ? $data['entry_date'] : $entry['entry_date'];
    $mood = $data['mood'] ?? 'neutral';
    $tags = !empty($data['tags']) ? trim($data['tags']) : null;
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

    if (empty($title)) {
        return ['success' => false, 'error' => 'Entry title is required.'];
    }
    if (empty($content)) {
        return ['success' => false, 'error' => 'Journal content cannot be empty.'];
    }

    $db = getDb();
    $stmt = $db->prepare('
        UPDATE journal_entries
        SET title = ?, content = ?, entry_date = ?, mood = ?, tags = ?, category_id = ?
        WHERE id = ? AND user_id = ?
    ');

    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('sssssiii', $title, $content, $entryDate, $mood, $tags, $categoryId, $entryId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'journal', 'update', "Updated journal entry '{$title}'", 'journal', $entryId, [
            'title' => $title,
            'mood' => $mood,
            'entry_date' => $entryDate
        ]);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}

/**
 * Deletes a journal entry.
 *
 * @param int $entryId
 * @param int $userId
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteJournalEntry(int $entryId, int $userId): array
{
    $entry = getJournalEntryById($entryId, $userId);
    if (!$entry) {
        return ['success' => false, 'error' => 'Journal entry not found.'];
    }

    $db = getDb();
    $stmt = $db->prepare('DELETE FROM journal_entries WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        return ['success' => false, 'error' => 'Database error.'];
    }

    $stmt->bind_param('ii', $entryId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        logActivity($userId, 'journal', 'delete', "Deleted journal entry '{$entry['title']}'", 'journal', $entryId);
        return ['success' => true, 'error' => null];
    }

    $error = $stmt->error;
    $stmt->close();
    return ['success' => false, 'error' => $error];
}
