<?php
/**
 * Planner Alias
 *
 * Redirects legacy planner.php requests to dashboard.php.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

redirect('dashboard.php');
