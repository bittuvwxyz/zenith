<?php
/**
 * Backward compatibility forwarder for login.inc.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isPost()) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $res = attemptLogin($email, $password);
    if ($res['success']) {
        redirect('dashboard.php');
    } else {
        setFlash('danger', $res['error']);
        redirect('login.php');
    }
}

redirect('login.php');
