<?php
/**
 * Notification Settings Page
 *
 * Configure email delivery preferences for reminders and security notifications.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$user = requireAuth();
$userId = (int) $user['id'];
$db = getDb();

// Fetch current notification settings
$stmt = $db->prepare('SELECT * FROM notification_settings WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$settings) {
    // Create defaults if not exist
    $ins = $db->prepare('INSERT INTO notification_settings (user_id) VALUES (?)');
    $ins->bind_param('i', $userId);
    $ins->execute();
    $ins->close();

    $settings = [
        'notify_task_due'        => 1,
        'notify_daily_summary'   => 1,
        'notify_security_alerts' => 1,
        'notify_habit_reminders' => 1,
    ];
}

// Handle Update POST
if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        setFlash('danger', 'Security verification failed.');
        redirect('notifications.php');
    }

    $notifyTaskDue = !empty($_POST['notify_task_due']) ? 1 : 0;
    $notifyDailySummary = !empty($_POST['notify_daily_summary']) ? 1 : 0;
    $notifySecurityAlerts = !empty($_POST['notify_security_alerts']) ? 1 : 0;
    $notifyHabitReminders = !empty($_POST['notify_habit_reminders']) ? 1 : 0;

    $upd = $db->prepare('
        UPDATE notification_settings
        SET notify_task_due = ?, notify_daily_summary = ?, notify_security_alerts = ?, notify_habit_reminders = ?
        WHERE user_id = ?
    ');
    $upd->bind_param('iiiii', $notifyTaskDue, $notifyDailySummary, $notifySecurityAlerts, $notifyHabitReminders, $userId);

    if ($upd->execute()) {
        $upd->close();
        logActivity($userId, 'settings', 'update_notifications', 'Updated notification preferences', 'settings', $userId);
        setFlash('success', 'Notification preferences saved successfully.');
    } else {
        $upd->close();
        setFlash('danger', 'Failed to update preferences.');
    }
    redirect('notifications.php');
}

$pageTitle = 'Notification Settings';
$pageSubtitle = 'Choose which email reminders and transactional alerts you receive.';
$activeNav = 'notifications';

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <h2 class="card-title"><?= renderIcon('bell') ?> Email Notification Preferences</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="notifications.php">
            <?= csrfField() ?>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                <!-- Task Due Reminders -->
                <label style="display: flex; align-items: flex-start; gap: 14px; cursor: pointer;">
                    <input type="checkbox" name="notify_task_due" value="1" <?= $settings['notify_task_due'] ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--accent-primary);">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Task Deadline Reminders</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Receive email notifications for urgent tasks approaching their due dates.</div>
                    </div>
                </label>

                <!-- Daily Summary -->
                <label style="display: flex; align-items: flex-start; gap: 14px; cursor: pointer;">
                    <input type="checkbox" name="notify_daily_summary" value="1" <?= $settings['notify_daily_summary'] ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--accent-primary);">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Daily Morning Briefing</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Receive a morning digest with today's tasks and routine checklist.</div>
                    </div>
                </label>

                <!-- Security Alerts -->
                <label style="display: flex; align-items: flex-start; gap: 14px; cursor: pointer;">
                    <input type="checkbox" name="notify_security_alerts" value="1" <?= $settings['notify_security_alerts'] ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--accent-primary);">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Security & Login Alerts</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Immediate email notification if a new device or suspicious login attempt is detected.</div>
                    </div>
                </label>

                <!-- Habit Reminders -->
                <label style="display: flex; align-items: flex-start; gap: 14px; cursor: pointer;">
                    <input type="checkbox" name="notify_habit_reminders" value="1" <?= $settings['notify_habit_reminders'] ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--accent-primary);">
                    <div>
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.95rem;">Habit Streak Protection</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Evening reminder if any of your active habits remain unchecked.</div>
                    </div>
                </label>
            </div>

            <div style="margin-top: 32px; border-top: 1px solid var(--border-subtle); padding-top: 20px;">
                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
