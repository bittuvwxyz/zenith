<?php
/**
 * Transactional Email Outbox & Dispatcher
 *
 * Implements the outbox pattern so database transactions never stall on SMTP.
 * Dispatches queued emails via PHPMailer post-commit and records delivery attempts.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Enqueues an email into the database outbox table.
 *
 * @param int|null $userId
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $subject
 * @param string $bodyHtml
 * @return int|false Enqueued outbox record ID or false on failure.
 */
function queueEmail(
    ?int $userId,
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $bodyHtml
) {
    $db = getDb();

    $stmt = $db->prepare('
        INSERT INTO email_outbox (user_id, recipient_email, recipient_name, subject, body_html, status, attempts, created_at)
        VALUES (?, ?, ?, ?, ?, "pending", 0, UTC_TIMESTAMP())
    ');

    if (!$stmt) {
        error_log('Failed to prepare queueEmail: ' . $db->error);
        return false;
    }

    $stmt->bind_param('issss', $userId, $recipientEmail, $recipientName, $subject, $bodyHtml);
    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    }

    $stmt->close();
    return false;
}

/**
 * Sends a single email via PHPMailer.
 *
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $subject
 * @param string $bodyHtml
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendEmailViaSmtp(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $bodyHtml
): array {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->Timeout    = 8; // Timeout fast so we never block user

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>'], ["\n", "\n\n"], $bodyHtml));

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Processes queued emails from the outbox.
 * Bounded retries with max 3 attempts.
 *
 * @param int $limit
 * @param int|null $specificId
 * @return array ['processed' => int, 'sent' => int, 'failed' => int]
 */
function processOutbox(int $limit = 5, ?int $specificId = null): array
{
    $db = getDb();
    $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

    if ($specificId !== null) {
        $stmt = $db->prepare('SELECT * FROM email_outbox WHERE id = ? AND status = "pending" LIMIT 1');
        $stmt->bind_param('i', $specificId);
    } else {
        $stmt = $db->prepare('SELECT * FROM email_outbox WHERE status = "pending" AND attempts < max_attempts ORDER BY id ASC LIMIT ?');
        $stmt->bind_param('i', $limit);
    }

    if (!$stmt) {
        return $stats;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $emails = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($emails as $email) {
        $stats['processed']++;
        $id = (int) $email['id'];
        $attempts = (int) $email['attempts'] + 1;

        $delivery = sendEmailViaSmtp(
            $email['recipient_email'],
            $email['recipient_name'],
            $email['subject'],
            $email['body_html']
        );

        if ($delivery['success']) {
            $stats['sent']++;
            $upd = $db->prepare('UPDATE email_outbox SET status = "sent", attempts = ?, last_error = NULL, sent_at = UTC_TIMESTAMP() WHERE id = ?');
            $upd->bind_param('ii', $attempts, $id);
            $upd->execute();
            $upd->close();
        } else {
            $stats['failed']++;
            $newStatus = ($attempts >= (int) $email['max_attempts']) ? 'failed' : 'pending';
            $upd = $db->prepare('UPDATE email_outbox SET status = ?, attempts = ?, last_error = ? WHERE id = ?');
            $upd->bind_param('sisi', $newStatus, $attempts, $delivery['error'], $id);
            $upd->execute();
            $upd->close();
        }
    }

    return $stats;
}

/**
 * Retrieves paginated email outbox logs for display in Email Logs page.
 *
 * @param array $filters ['status' => '', 'search' => '']
 * @param int $limit
 * @param int $offset
 * @return array ['logs' => array, 'total' => int]
 */
function getEmailLogs(array $filters = [], int $limit = 20, int $offset = 0): array
{
    $db = getDb();

    $where = [];
    $params = [];
    $types = '';

    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (!empty($filters['search'])) {
        $where[] = '(recipient_email LIKE ? OR subject LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    $whereClause = empty($where) ? '1' : implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM email_outbox WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $sql = "SELECT id, user_id, recipient_email, recipient_name, subject, status, attempts, max_attempts, last_error, sent_at, created_at FROM email_outbox WHERE $whereClause ORDER BY id DESC LIMIT ? OFFSET ?";
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
 * Builds a responsive, premium HTML template for transactional emails.
 *
 * @param string $title
 * @param string $heading
 * @param string $contentHtml
 * @param string|null $buttonText
 * @param string|null $buttonUrl
 * @return string
 */
function buildEmailTemplate(
    string $title,
    string $heading,
    string $contentHtml,
    ?string $buttonText = null,
    ?string $buttonUrl = null
): string {
    $buttonBlock = '';
    if ($buttonText && $buttonUrl) {
        $buttonBlock = '
            <table cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0;">
                <tr>
                    <td bgcolor="#10b981" style="border-radius: 8px;">
                        <a href="' . escape($buttonUrl) . '" target="_blank" style="display: inline-block; padding: 14px 28px; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; border-radius: 8px; letter-spacing: 0.2px;">
                            ' . escape($buttonText) . '
                        </a>
                    </td>
                </tr>
            </table>
            <p style="font-size: 13px; color: #94a3b8; word-break: break-all; margin-top: 15px;">
                Button not working? Copy and paste this URL into your browser:<br>
                <a href="' . escape($buttonUrl) . '" style="color: #38bdf8;">' . escape($buttonUrl) . '</a>
            </p>
        ';
    }

    $year = date('Y');
    $appName = escape(APP_NAME);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #e2e8f0;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #0b1120; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 580px; background-color: #1e293b; border-radius: 16px; border: 1px solid #334155; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 32px 40px; background-color: #0f172a; border-bottom: 1px solid #334155;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <div style="font-size: 22px; font-weight: 800; color: #f8fafc; letter-spacing: -0.5px;">
                                            <span style="color: #10b981;">⚡</span> {$appName}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 40px;">
                            <h1 style="margin: 0 0 18px 0; font-size: 24px; font-weight: 700; color: #f8fafc; line-height: 1.3;">
                                {$heading}
                            </h1>
                            <div style="font-size: 15px; line-height: 1.7; color: #cbd5e1;">
                                {$contentHtml}
                            </div>
                            {$buttonBlock}
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #0f172a; border-top: 1px solid #334155; text-align: center; font-size: 13px; color: #64748b; line-height: 1.6;">
                            <p style="margin: 0 0 6px 0;">This email was securely generated by {$appName}.</p>
                            <p style="margin: 0;">&copy; {$year} {$appName}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
