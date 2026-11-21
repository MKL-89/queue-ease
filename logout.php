<?php
/**
 * QueueEase - Secure logout
 * File: logout.php
 *
 * Logout is a POST request with a CSRF token, so another website cannot
 * log a user out by embedding a link.
 */
require_once __DIR__ . '/includes/init.php';

if (!is_post()) {
    redirect(is_logged_in() ? dashboard_for_role(user_role()) : 'login.php');
}
require_csrf();

if (is_logged_in()) {
    log_activity('logout', ($_SESSION['user']['name'] ?? 'User') . ' logged out');
}
logout_user();

session_start();
set_flash('success', 'You have been logged out securely.');
redirect('login.php');
