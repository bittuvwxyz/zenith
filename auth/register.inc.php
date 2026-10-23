<?php
/**
 * Backward compatibility forwarder for register.inc.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isPost()) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $res = registerUser($username, $email, $password);
    if ($res['success']) {
        setFlash('success', 'Account registered! Please verify your email.');
        redirect('login.php');
    } else {
        setFlash('danger', $res['error']);
        redirect('register.php');
    }
}

redirect('register.php');
