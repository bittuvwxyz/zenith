<?php
/**
 * Backward compatibility forwarder for auth/reset.inc.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isPost()) {
    $email = trim($_POST['email'] ?? '');
    requestPasswordReset($email);
    setFlash('info', 'If an account exists, a recovery link has been queued.');
}

redirect('login.php');
