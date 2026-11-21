<?php
/**
 * QueueEase - CSRF (Cross-Site Request Forgery) protection
 * File: includes/csrf.php
 *
 * Every POST form includes <?= csrf_field() ?> and every POST handler
 * calls require_csrf() before doing anything.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Stop the request if the CSRF token is missing or wrong. */
function require_csrf(bool $json = false): void
{
    if (csrf_valid()) {
        return;
    }
    if ($json) {
        json_response(['success' => false, 'message' => 'Your session has expired. Please reload the page.'], 419);
    }
    set_flash('error', 'Your form session expired. Please try again.');
    $back = $_SERVER['HTTP_REFERER'] ?? url('index.php');
    header('Location: ' . $back);
    exit;
}
