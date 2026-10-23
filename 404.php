<?php
/**
 * 404 Error Page
 */

http_response_code(404);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = '404 Not Found';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <link rel="stylesheet" href="<?= url('assets/css/design-system.css') ?>">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 480px; text-align: center; padding: 48px 32px; box-shadow: var(--shadow-lg);">
        <div style="font-size: 3.5rem; font-weight: 800; color: var(--accent-danger); line-height: 1; margin-bottom: 12px;">404</div>
        <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 8px;">Page Not Found</h1>
        <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 28px;">
            The page you are looking for doesn't exist, was removed, or you followed an expired link.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="<?= url('dashboard.php') ?>" class="btn btn-primary">Go to Dashboard</a>
            <a href="<?= url('index.php') ?>" class="btn btn-secondary">Homepage</a>
        </div>
    </div>
</body>
</html>
