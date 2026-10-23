<?php
/**
 * Shared Application Header
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

initAppSession();
$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> — <?= escape(APP_NAME) ?></title>
    <meta name="description" content="<?= escape(APP_TAGLINE) ?>">
    <meta name="csrf-token" content="<?= escape(csrfToken()) ?>">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/design-system.css') ?>">

    <!-- Chart.js for beautiful animated reports and productivity trends -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<?php if ($currentUser): ?>
<div class="app-layout">
    <!-- Mobile Drawer Overlay -->
    <div class="mobile-drawer-overlay"></div>

    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Top Navigation Bar -->
        <?php require_once __DIR__ . '/topbar.php'; ?>

        <!-- Content Area -->
        <main class="app-content">
<?php endif; ?>
