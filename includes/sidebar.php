<?php
/**
 * Shared Application Sidebar Navigation
 */
$activeNav = $activeNav ?? 'dashboard';
?>
<aside class="app-sidebar" aria-label="Main Navigation">
    <div class="sidebar-header">
        <a href="<?= url('dashboard.php') ?>" class="sidebar-logo">
            <div class="logo-icon">⚡</div>
            <span><?= escape(APP_NAME) ?></span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-title">Workspace</div>

        <a href="<?= url('dashboard.php') ?>" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
            <?= renderIcon('dashboard') ?>
            <span>Dashboard</span>
        </a>

        <a href="<?= url('todos.php') ?>" class="nav-link <?= $activeNav === 'todos' ? 'active' : '' ?>">
            <?= renderIcon('check-square') ?>
            <span>To-Do List</span>
        </a>

        <a href="<?= url('habits.php') ?>" class="nav-link <?= $activeNav === 'habits' ? 'active' : '' ?>">
            <?= renderIcon('flame') ?>
            <span>My Habits</span>
        </a>

        <a href="<?= url('routine.php') ?>" class="nav-link <?= $activeNav === 'routine' ? 'active' : '' ?>">
            <?= renderIcon('clock') ?>
            <span>Daily Routine</span>
        </a>

        <a href="<?= url('journal.php') ?>" class="nav-link <?= $activeNav === 'journal' ? 'active' : '' ?>">
            <?= renderIcon('book') ?>
            <span>Daily Journal</span>
        </a>

        <a href="<?= url('calendar.php') ?>" class="nav-link <?= $activeNav === 'calendar' ? 'active' : '' ?>">
            <?= renderIcon('calendar') ?>
            <span>Calendar</span>
        </a>

        <a href="<?= url('categories.php') ?>" class="nav-link <?= $activeNav === 'categories' ? 'active' : '' ?>">
            <?= renderIcon('folder') ?>
            <span>Categories</span>
        </a>

        <div class="nav-group-title">Analytics & Logs</div>

        <a href="<?= url('reports.php') ?>" class="nav-link <?= $activeNav === 'reports' ? 'active' : '' ?>">
            <?= renderIcon('chart') ?>
            <span>Reports & Stats</span>
        </a>

        <a href="<?= url('activity-logs.php') ?>" class="nav-link <?= $activeNav === 'activity-logs' ? 'active' : '' ?>">
            <?= renderIcon('activity') ?>
            <span>Activity Logs</span>
        </a>

        <a href="<?= url('security-logs.php') ?>" class="nav-link <?= $activeNav === 'security-logs' ? 'active' : '' ?>">
            <?= renderIcon('shield') ?>
            <span>Security Logs</span>
        </a>

        <a href="<?= url('email-logs.php') ?>" class="nav-link <?= $activeNav === 'email-logs' ? 'active' : '' ?>">
            <?= renderIcon('mail') ?>
            <span>Email Logs</span>
        </a>

        <div class="nav-group-title">Account</div>

        <a href="<?= url('profile.php') ?>" class="nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
            <?= renderIcon('user') ?>
            <span>Profile</span>
        </a>

        <a href="<?= url('notifications.php') ?>" class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>">
            <?= renderIcon('bell') ?>
            <span>Notifications</span>
        </a>

        <a href="<?= url('security.php') ?>" class="nav-link <?= $activeNav === 'security' ? 'active' : '' ?>">
            <?= renderIcon('lock') ?>
            <span>Account Security</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <button type="button" class="btn-icon desktop-sidebar-toggle" title="Toggle Sidebar (Collapse/Expand)" aria-label="Toggle Sidebar">
            <?= renderIcon('chevron-left') ?>
        </button>

        <a href="<?= url('auth/logout.php') ?>" class="btn btn-ghost btn-sm" title="Log Out">
            <?= renderIcon('logout') ?>
            <span class="sidebar-footer-text">Sign Out</span>
        </a>
    </div>
</aside>
