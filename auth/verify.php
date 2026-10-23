<?php
/**
 * Backward compatibility forwarder for auth/verify.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
redirect('verify.php', ['token' => $token]);
