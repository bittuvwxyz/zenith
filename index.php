<?php
/**
 * Root Landing / Index Controller
 *
 * Automatically routes logged-in users to the productivity dashboard,
 * or displays a modern showcase landing page for guests.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initAppSession();

if (getCurrentUser()) {
    redirect('dashboard.php');
}

$hasUsers = hasRegisteredUsers();

$pageTitle = APP_NAME . ' — Personal Productivity System';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?></title>
    <meta name="description" content="<?= escape(APP_TAGLINE) ?>">
    <link rel="stylesheet" href="<?= url('assets/css/design-system.css') ?>">
</head>
<body style="display: flex; flex-direction: column; min-height: 100vh;">

    <!-- Landing Navigation -->
    <header style="height: 72px; border-bottom: 1px solid var(--border-subtle); background: var(--bg-surface); display: flex; align-items: center; padding: 0 32px; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 1.25rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px;">⚡</div>
            <span><?= escape(APP_NAME) ?></span>
        </div>

        <div style="display: flex; align-items: center; gap: 14px;">
            <button type="button" class="btn-icon theme-toggle-btn" aria-label="Toggle Theme">
                <?= renderIcon('sun') ?>
            </button>
            <a href="login.php" class="btn btn-secondary btn-sm">Sign In</a>
            <?php if (!$hasUsers): ?>
                <a href="register.php" class="btn btn-primary btn-sm">Initial Setup</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero Section -->
    <main style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 20px; text-align: center;">
        <div style="max-width: 820px; margin: 0 auto;">
            <div class="badge badge-success" style="padding: 6px 14px; font-size: 0.82rem; margin-bottom: 20px;">
                ⚡ High-Performance Personal Operating System
            </div>

            <h1 style="font-size: clamp(2.4rem, 5vw, 4rem); font-weight: 800; line-height: 1.15; letter-spacing: -1.5px; margin-bottom: 20px; color: var(--text-primary);">
                Master Your Focus.<br>
                <span style="background: linear-gradient(135deg, #10b981, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Execute with Precision.</span>
            </h1>

            <p style="font-size: 1.15rem; color: var(--text-secondary); line-height: 1.7; max-width: 640px; margin: 0 auto 36px;">
                A unified personal workspace engineered for to-dos, habit streaks, daily routines, mindful journaling, and deep performance analytics.
            </p>

            <div style="display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
                <?php if ($hasUsers): ?>
                    <a href="login.php" class="btn btn-primary" style="padding: 14px 32px; font-size: 1.05rem;">
                        Open Workspace &rarr;
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary" style="padding: 14px 32px; font-size: 1.05rem;">
                        Complete Initial Setup &rarr;
                    </a>
                <?php endif; ?>
                <a href="login.php" class="btn btn-secondary" style="padding: 14px 28px; font-size: 1.05rem;">
                    Sign In
                </a>
            </div>

            <!-- Features Highlights Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 60px; text-align: left;">
                <div class="card" style="padding: 20px;">
                    <div style="font-size: 1.8rem; margin-bottom: 10px;">📋</div>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; margin-bottom: 4px;">Smart To-Do Tasks</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Priorities, categories, due dates, filtering, and seamless completion.</div>
                </div>

                <div class="card" style="padding: 20px;">
                    <div style="font-size: 1.8rem; margin-bottom: 10px;">🔥</div>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; margin-bottom: 4px;">Habit Streaks</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Track daily repetitions, build atomic consistency, and maintain streaks.</div>
                </div>

                <div class="card" style="padding: 20px;">
                    <div style="font-size: 1.8rem; margin-bottom: 10px;">⏱️</div>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; margin-bottom: 4px;">Daily Routines</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Morning, afternoon, evening, and night rituals with live completion rates.</div>
                </div>

                <div class="card" style="padding: 20px;">
                    <div style="font-size: 1.8rem; margin-bottom: 10px;">📊</div>
                    <div style="font-weight: 700; color: var(--text-primary); font-size: 1rem; margin-bottom: 4px;">Live Analytics</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">Weekly velocity trends, mood distributions, and tamper-evident audit logs.</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="padding: 24px 32px; border-top: 1px solid var(--border-subtle); background: var(--bg-surface); text-align: center; font-size: 0.85rem; color: var(--text-muted);">
        &copy; <?= date('Y') ?> <?= escape(APP_NAME) ?>. All rights reserved. Built with PHP &amp; MySQLi.
    </footer>

    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
