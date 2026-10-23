<?php
/**
 * Shared Application Topbar
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$currentUser = getCurrentUser();
$todayStr = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('l, F j, Y');
?>
<header class="app-topbar">
    <div class="topbar-left">
        <button type="button" class="btn-icon mobile-menu-toggle" aria-label="Open Navigation Menu" style="display: none;">
            <?= renderIcon('menu') ?>
        </button>

        <div>
            <h1 class="topbar-title"><?= escape($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <div class="topbar-subtitle"><?= escape($pageSubtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-right">
        <div class="date-pill" title="Current Local Time (Asia/Kolkata)">
            <?= renderIcon('calendar', 'icon-sm') ?>
            <span><?= escape($todayStr) ?></span>
        </div>

        <button type="button" class="btn btn-primary btn-sm" onclick="openModal('quickAddModal')" title="Quick Add New Item">
            <?= renderIcon('plus') ?>
            <span>Quick Add</span>
        </button>

        <button type="button" class="btn-icon theme-toggle-btn" title="Toggle Dark/Light Mode" aria-label="Toggle Theme">
            <span class="theme-icon-dark"><?= renderIcon('sun') ?></span>
        </button>

        <a href="<?= url('profile.php') ?>" class="btn btn-ghost btn-sm" style="display: inline-flex; align-items: center; gap: 8px;">
            <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #3b82f6); color: #fff; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center;">
                <?= strtoupper(substr($currentUser['username'] ?? 'U', 0, 1)) ?>
            </div>
            <span style="font-weight: 600; color: var(--text-primary); font-size: 0.9rem;">
                <?= escape($currentUser['username'] ?? 'User') ?>
            </span>
        </a>
    </div>
</header>

<style>
@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: inline-flex !important;
    }
}
</style>
