<?php
/**
 * Database Connection & Transaction Management
 *
 * Uses MySQLi only. Never PDO.
 */

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Returns the active MySQLi database connection.
 *
 * @return mysqli
 */
function getDb(): mysqli
{
    static $conn = null;

    if ($conn !== null && @mysqli_ping($conn)) {
        return $conn;
    }

    try {
        $conn = mysqli_init();
        if (!$conn) {
            throw new RuntimeException('mysqli_init failed');
        }

        $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        if (!@$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)) {
            throw new RuntimeException('Database connection failed: ' . mysqli_connect_error());
        }

        if (!$conn->set_charset(DB_CHARSET)) {
            throw new RuntimeException('Failed to set charset: ' . $conn->error);
        }

        return $conn;
    } catch (Throwable $e) {
        error_log('Database Connection Error: ' . $e->getMessage());
        if (php_sapi_name() === 'cli') {
            throw $e;
        }
        http_response_code(500);
        die('<!DOCTYPE html><html><head><title>Database Error</title><style>body{font-family:sans-serif;background:#0f172a;color:#f8fafc;padding:50px;text-align:center;}h1{color:#ef4444;}</style></head><body><h1>Database Connection Failed</h1><p>Please check your database configuration in <code>config/config.php</code> and ensure the MySQL service is running.</p></body></html>');
    }
}

// Initialize global connection for backward compatibility
$conn = getDb();

/**
 * Starts a database transaction.
 *
 * @param mysqli|null $db
 * @return bool
 */
function dbBeginTransaction(?mysqli $db = null): bool
{
    $db = $db ?: getDb();
    return $db->begin_transaction();
}

/**
 * Commits a database transaction.
 *
 * @param mysqli|null $db
 * @return bool
 */
function dbCommit(?mysqli $db = null): bool
{
    $db = $db ?: getDb();
    return $db->commit();
}

/**
 * Rolls back a database transaction.
 *
 * @param mysqli|null $db
 * @return bool
 */
function dbRollback(?mysqli $db = null): bool
{
    $db = $db ?: getDb();
    return $db->rollback();
}
