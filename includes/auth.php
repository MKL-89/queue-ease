<?php
/**
 * QueueEase - Authentication and role-based access control
 * File: includes/auth.php
 */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

function user_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/** Where each role goes after logging in. */
function dashboard_for_role(string $role): string
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'staff':
            return 'staff/dashboard.php';
        default:
            return 'customer/dashboard.php';
    }
}

/** Save the logged-in user in the session (called after a successful login). */
function login_user(array $user): void
{
    session_regenerate_id(true);           // prevents session fixation
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['full_name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);        // fresh CSRF token for the new session
}

/** Destroy the session completely. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Log out users who were inactive longer than SESSION_IDLE_TIMEOUT. */
function check_session_timeout(): void
{
    if (!is_logged_in()) {
        return;
    }
    $last = $_SESSION['last_activity'] ?? time();
    if (time() - $last > SESSION_IDLE_TIMEOUT) {
        logout_user();
        session_start();
        set_flash('warning', 'You were logged out after a period of inactivity.');
        return;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Make sure a user is logged in AND still active in the database.
 * Re-checking the database means a deactivated account is blocked at once.
 */
function require_login(bool $json = false): array
{
    if (!is_logged_in()) {
        if ($json) {
            json_response(['success' => false, 'message' => 'Please log in.'], 401);
        }
        set_flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user();
        session_start();
        if ($json) {
            json_response(['success' => false, 'message' => 'Your account is not active.'], 401);
        }
        set_flash('error', 'Your account is not active. Please contact the administrator.');
        redirect('login.php');
    }

    // Keep session data in sync with the database (e.g. name or role changed by admin)
    $_SESSION['user']['name'] = $user['full_name'];
    $_SESSION['user']['role'] = $user['role'];

    return $_SESSION['user'];
}

/**
 * Allow only the listed roles to open a page, e.g. require_role('admin').
 */
function require_role($roles, bool $json = false): array
{
    $user  = require_login($json);
    $roles = (array) $roles;

    if (!in_array($user['role'], $roles, true)) {
        log_activity('access_denied', 'Tried to open ' . ($_SERVER['REQUEST_URI'] ?? 'a page') . ' without permission');
        if ($json) {
            json_response(['success' => false, 'message' => 'You are not allowed to do this.'], 403);
        }
        http_response_code(403);
        $pageTitle = 'Access Denied';
        require __DIR__ . '/access_denied.php';
        exit;
    }
    return $user;
}

/** Customer profile row for the logged-in customer. */
function current_customer(): ?array
{
    if (user_role() !== 'customer') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM customers WHERE user_id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    return $stmt->fetch() ?: null;
}

// ---------------------------------------------------------------
// Login attempt limiting (brute-force protection)
// ---------------------------------------------------------------
function login_locked_seconds(): int
{
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
    return max(0, $lockedUntil - time());
}

function register_failed_login(): void
{
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCKOUT_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
}

function clear_failed_logins(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}
