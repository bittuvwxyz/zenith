<?php
/**
 * User Profile Page
 *
 * Update account display name, timezone, and biographical info.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$user = requireAuth();
$userId = (int) $user['id'];

// Handle Profile Update POST
if (isPost()) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        setFlash('danger', 'Security verification failed.');
        redirect('profile.php');
    }

    $username = trim($_POST['username'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Kolkata');
    $bio = trim($_POST['bio'] ?? '');

    if (empty($username) || mb_strlen($username) < 3) {
        setFlash('danger', 'Username must be at least 3 characters.');
        redirect('profile.php');
    }

    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET username = ?, timezone = ?, bio = ? WHERE id = ?');
    $stmt->bind_param('sssi', $username, $timezone, $bio, $userId);

    if ($stmt->execute()) {
        $stmt->close();
        $_SESSION['username'] = $username;
        logActivity($userId, 'auth', 'update_profile', "Updated profile information", 'user', $userId);
        setFlash('success', 'Profile updated successfully.');
    } else {
        $stmt->close();
        setFlash('danger', 'Failed to update profile.');
    }
    redirect('profile.php');
}

$pageTitle = 'Profile';
$pageSubtitle = 'Manage your personal workspace identity and preferences.';
$activeNav = 'profile';

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid-main-side">
    <!-- Left: Profile Edit Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= renderIcon('user') ?> Edit Profile</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label" for="prof_username">Username *</label>
                    <input type="text" id="prof_username" name="username" class="form-control" value="<?= escape($user['username']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prof_email">Email Address</label>
                    <input type="email" id="prof_email" class="form-control" value="<?= escape($user['email']) ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">Email cannot be changed directly for security purposes.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prof_tz">Timezone</label>
                    <select id="prof_tz" name="timezone" class="form-select">
                        <option value="Asia/Kolkata" <?= ($user['timezone'] ?? '') === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                        <option value="UTC" <?= ($user['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                        <option value="America/New_York" <?= ($user['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                        <option value="Europe/London" <?= ($user['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prof_bio">Bio / Philosophy</label>
                    <textarea id="prof_bio" name="bio" class="form-textarea" placeholder="A brief personal statement or your primary focus for this season..."><?= escape($user['bio'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Right: Account Overview Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Account Summary</h3>
        </div>
        <div class="card-body" style="text-align: center;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; font-size: 32px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>

            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
                <?= escape($user['username']) ?>
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                <?= escape($user['email']) ?>
            </p>

            <span class="badge badge-success" style="margin-bottom: 20px;">
                Verified Account
            </span>

            <div style="border-top: 1px solid var(--border-subtle); padding-top: 16px; font-size: 0.82rem; color: var(--text-secondary); text-align: left; display: flex; flex-direction: column; gap: 8px;">
                <div><strong>Joined:</strong> <?= formatDate($user['created_at']) ?></div>
                <div><strong>Timezone:</strong> <?= escape($user['timezone'] ?? 'Asia/Kolkata') ?></div>
                <div><strong>Status:</strong> Active</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
