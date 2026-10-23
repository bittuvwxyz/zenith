<?php
/**
 * Logout Handler
 *
 * Securely terminates user session, deletes session cookies, logs audit event,
 * and redirects to the login screen.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

initAppSession();
logoutUser();

setFlash('info', 'You have been securely signed out.');
redirect('login.php');
