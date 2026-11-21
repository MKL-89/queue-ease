<?php
/**
 * QueueEase - Bootstrap file
 * File: includes/init.php
 *
 * Every page starts with:  require_once __DIR__ . '/../includes/init.php';
 * It loads configuration, starts a secure session and loads helper functions.
 */

require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------
// Secure session start
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,      // JavaScript cannot read the session cookie
        'samesite' => 'Lax',     // helps against CSRF
    ]);
    session_start();
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/queue_functions.php';

// Log out users who have been idle for too long
check_session_timeout();
