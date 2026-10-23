<?php
/**
 * Application Configuration
 *
 * Sets up global constants, timezone, database credentials,
 * email settings, and security parameters.
 */

// Timezone setup
date_default_timezone_set('Asia/Kolkata');

// Base URL calculation (automatically detects protocol and host)
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', getenv('BASE_URL') ?: "$scheme://$host");

// Application Identity
define('APP_NAME', 'Zenith Productivity');
define('APP_TAGLINE', 'Your Personal High-Performance SaaS Workspace');
define('SITE_NAME', APP_NAME);

// Database Configuration (Strictly MySQLi)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'auth');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_CHARSET', 'utf8mb4');

// Mail Configuration (PHPMailer with SMTP)
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_SECURE', getenv('MAIL_SECURE') ?: 'tls'); // 'tls' or 'ssl'
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM_EMAIL') ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// Security & Authentication Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('VERIFY_TOKEN_LIFETIME_HOURS', 24);
define('RESET_TOKEN_LIFETIME_MINUTES', 30);
define('SESSION_LIFETIME_SECONDS', 86400 * 7); // 7 days

// Registration Mode: Set to false after first account is created if single-user mode is active
define('SINGLE_USER_MODE', true);
