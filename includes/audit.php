<?php
/**
 * Audit and Security Logging System
 *
 * Implements centralized activity logs and security event logs.
 * Strictly redacts passwords, tokens, full journal text, and secrets.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Logs a general user or system activity to `activity_logs`.
 *
 * @param int|null $userId
 * @param string $module
 * @param string $action
 * @param string $description
 * @param string|null $entityType
 * @param int|null $entityId
 * @param array|null $details
 * @param string $status success|failure
 * @return bool
 */
function logActivity(
    ?int $userId,
    string $module,
    string $action,
    string $description,
    ?string $entityType = null,
    ?int $entityId = null,
    ?array $details = null,
    string $status = 'success'
): bool {
    $db = getDb();

    // Redact sensitive keys from details if present
    $safeDetails = null;
    if ($details !== null) {
        $redacted = $details;
        $sensitiveKeys = ['password', 'pass', 'token', 'csrf_token', 'verification_token', 'reset_token', 'journal_content', 'content'];
        array_walk_recursive($redacted, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        });
        $safeDetails = json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $ip = getClientIp();
    $ua = getClientUserAgent();

    $stmt = $db->prepare('
        INSERT INTO activity_logs (user_id, module, action, entity_type, entity_id, description, details, ip_address, user_agent, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        error_log('Failed to prepare activity log: ' . $db->error);
        return false;
    }

    $stmt->bind_param(
        'isssisssss',
        $userId,
        $module,
        $action,
        $entityType,
        $entityId,
        $description,
        $safeDetails,
        $ip,
        $ua,
        $status
    );

    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Logs an authentication or security event to `security_logs`.
 *
 * @param int|null $userId
 * @param string $eventType
 * @param string $identifier
 * @param string $status success|failed|blocked
 * @return bool
 */
function logSecurity(
    ?int $userId,
    string $eventType,
    string $identifier,
    string $status = 'success'
): bool {
    $db = getDb();
    $ip = getClientIp();
    $ua = getClientUserAgent();

    // Never log raw password in identifier
    $cleanIdentifier = mb_substr(trim($identifier), 0, 255);

    $stmt = $db->prepare('
        INSERT INTO security_logs (user_id, event_type, identifier, ip_address, user_agent, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        error_log('Failed to prepare security log: ' . $db->error);
        return false;
    }

    $stmt->bind_param('isssss', $userId, $eventType, $cleanIdentifier, $ip, $ua, $status);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Retrieves paginated activity logs for a user.
 *
 * @param int $userId
 * @param array $filters ['module' => '', 'action' => '', 'search' => '', 'status' => '']
 * @param int $limit
 * @param int $offset
 * @return array ['logs' => array, 'total' => int]
 */
function getActivityLogs(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
{
    $db = getDb();

    $where = ['user_id = ?'];
    $params = [$userId];
    $types = 'i';

    if (!empty($filters['module'])) {
        $where[] = 'module = ?';
        $params[] = $filters['module'];
        $types .= 's';
    }

    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['search'])) {
        $where[] = '(description LIKE ? OR entity_type LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = implode(' AND ', $where);

    // Total count
    $countSql = "SELECT COUNT(*) FROM activity_logs WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Paginated records
    $sql = "SELECT * FROM activity_logs WHERE $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['logs' => $logs, 'total' => (int) $total];
}

/**
 * Retrieves paginated security logs.
 *
 * @param int|null $userId
 * @param array $filters ['event_type' => '', 'status' => '', 'search' => '']
 * @param int $limit
 * @param int $offset
 * @return array ['logs' => array, 'total' => int]
 */
function getSecurityLogs(?int $userId = null, array $filters = [], int $limit = 20, int $offset = 0): array
{
    $db = getDb();

    $where = [];
    $params = [];
    $types = '';

    if ($userId !== null) {
        $where[] = '(user_id = ? OR user_id IS NULL)';
        $params[] = $userId;
        $types .= 'i';
    }

    if (!empty($filters['event_type'])) {
        $where[] = 'event_type = ?';
        $params[] = $filters['event_type'];
        $types .= 's';
    }

    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['search'])) {
        $where[] = '(identifier LIKE ? OR ip_address LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = empty($where) ? '1' : implode(' AND ', $where);

    // Total count
    $countSql = "SELECT COUNT(*) FROM security_logs WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    // Paginated records
    $sql = "SELECT * FROM security_logs WHERE $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
    $stmt = $db->prepare($sql);
    $typesWithLimit = $types . 'ii';
    $paramsWithLimit = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['logs' => $logs, 'total' => (int) $total];
}
