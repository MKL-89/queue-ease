<?php
/**
 * QueueEase - Application configuration
 * File: config/config.php
 *
 * Change the values below to suit your environment.
 */

// ---------------------------------------------------------------
// Application details
// ---------------------------------------------------------------
define('APP_NAME', 'QueueEase');
define('APP_TAGLINE', 'Digital Queue Management System');
define('INSTITUTION_NAME', 'QueueEase Service Centre');
define('APP_TIMEZONE', 'Africa/Nairobi');

// ---------------------------------------------------------------
// Database connection (XAMPP defaults: user "root", no password)
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'dqms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---------------------------------------------------------------
// Security settings
// ---------------------------------------------------------------
define('SESSION_NAME', 'QUEUEEASE_SESSID');
define('SESSION_IDLE_TIMEOUT', 1800);      // 30 minutes of inactivity logs the user out
define('LOGIN_MAX_ATTEMPTS', 5);           // failed logins allowed ...
define('LOGIN_LOCKOUT_SECONDS', 300);      // ... before a 5 minute lockout
define('PASSWORD_MIN_LENGTH', 8);

// ---------------------------------------------------------------
// Queue settings
// ---------------------------------------------------------------
define('DISPLAY_REFRESH_SECONDS', 5);      // live display polling interval
define('TICKET_REFRESH_SECONDS', 8);       // customer ticket tracking interval
define('ITEMS_PER_PAGE', 20);

// Show detailed PHP errors while developing. Set to false for a live server.
define('APP_DEBUG', true);

// ---------------------------------------------------------------
// Base URL (worked out automatically so the project runs either in
// C:\xampp\htdocs\<folder> or with PHP's built-in server)
// ---------------------------------------------------------------
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$docRoot     = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? $projectRoot) ?: $projectRoot);
$basePath    = '';
if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
    $basePath = substr($projectRoot, strlen($docRoot));
}
$basePath = '/' . trim(str_replace(' ', '%20', $basePath), '/');
define('BASE_URL', rtrim($basePath, '/'));
define('ROOT_PATH', $projectRoot);
