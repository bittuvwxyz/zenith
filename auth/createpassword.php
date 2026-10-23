<?php
/**
 * Backward compatibility forwarder for auth/createpassword.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
redirect('createpassword.php', ['token' => $token]);
