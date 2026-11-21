<?php
/**
 * QueueEase - General helper functions
 * File: includes/functions.php
 */

/** Escape output for HTML (protects against XSS). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build an absolute URL inside the project, e.g. url('admin/dashboard.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Full URL including http://host, e.g. for QR codes. */
function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

/** Star rating as HTML, e.g. ★★★★☆ */
function star_rating($rating): string
{
    if ($rating === null || $rating === '') {
        return '<span class="muted">-</span>';
    }
    $rating  = (float) $rating;
    $rounded = (int) round($rating);
    return '<span class="star-display" title="' . e(number_format($rating, 1)) . ' / 5">'
        . str_repeat('&#9733;', $rounded) . '<span class="off">' . str_repeat('&#9733;', 5 - $rounded) . '</span></span>';
}

/** Redirect to a page inside the project and stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Store a one-time message to show on the next page. */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Get and clear all flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Read a trimmed value from POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Read a trimmed value from GET. */
function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Read a positive integer from GET/POST (0 if missing or invalid). */
function int_param(string $key, string $source = 'any'): int
{
    if ($source === 'post') {
        $value = $_POST[$key] ?? 0;
    } elseif ($source === 'get') {
        $value = $_GET[$key] ?? 0;
    } else {
        $value = $_POST[$key] ?? $_GET[$key] ?? 0;
    }
    $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $int === false ? 0 : (int) $int;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Keep previously typed form values after a validation error. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/** Validate a date string in Y-m-d format. */
function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/** Validate a phone number (digits, optional leading +, 9-15 digits). */
function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9]{9,15}$/', $phone);
}

/** Validate a person's name. */
function valid_name(string $name): bool
{
    return (bool) preg_match("/^[\\p{L} .'-]{2,100}$/u", $name);
}

/** Password rule: minimum length, at least one letter and one number. */
function valid_password(string $password): bool
{
    return strlen($password) >= PASSWORD_MIN_LENGTH
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

function password_rule_text(): string
{
    return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters and contain letters and numbers.';
}

/** Format a datetime for display. */
function format_datetime(?string $value, string $format = 'd M Y, h:i A'): string
{
    if (empty($value)) {
        return '-';
    }
    return date($format, strtotime($value));
}

function format_time(?string $value): string
{
    return format_datetime($value, 'h:i A');
}

/** Turn a number of minutes into readable text. */
function format_minutes($minutes): string
{
    if ($minutes === null || $minutes === '') {
        return '-';
    }
    $minutes = (float) $minutes;
    if ($minutes < 1) {
        return $minutes <= 0 ? '0 min' : '< 1 min';
    }
    $minutes = (int) round($minutes);
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    return intdiv($minutes, 60) . ' h ' . ($minutes % 60) . ' min';
}

/** Coloured status badge HTML for a ticket status. */
function status_badge(string $status): string
{
    $labels = [
        'waiting'   => 'Waiting',
        'called'    => 'Called',
        'serving'   => 'Serving',
        'served'    => 'Served',
        'skipped'   => 'Skipped',
        'cancelled' => 'Cancelled',
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

function active_badge($isActive): string
{
    return (int) $isActive === 1
        ? '<span class="badge badge-active">Active</span>'
        : '<span class="badge badge-inactive">Inactive</span>';
}

/** Client IP address (for activity logs). */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

/** Send a JSON response and stop. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

/**
 * Simple pagination helper.
 * Returns ['page' => int, 'pages' => int, 'offset' => int, 'limit' => int].
 */
function paginate(int $totalRows, int $perPage = ITEMS_PER_PAGE): array
{
    $pages = max(1, (int) ceil($totalRows / $perPage));
    $page  = min(max(1, int_param('page', 'get') ?: 1), $pages);
    return [
        'page'   => $page,
        'pages'  => $pages,
        'offset' => ($page - 1) * $perPage,
        'limit'  => $perPage,
        'total'  => $totalRows,
    ];
}

/** Render pagination links that keep the current filters. */
function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $query = $_GET;
    $html  = '<nav class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 10 && abs($i - $p['page']) > 2 && $i !== 1 && $i !== $p['pages']) {
            if ($i === 2 || $i === $p['pages'] - 1) {
                $html .= '<span class="page-gap">&hellip;</span>';
            }
            continue;
        }
        $query['page'] = $i;
        $class = $i === $p['page'] ? ' class="active"' : '';
        $html .= '<a' . $class . ' href="?' . e(http_build_query($query)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Get currently active announcements. */
function get_active_announcements(PDO $pdo, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT id, title, message, created_at FROM announcements
         WHERE is_active = 1
           AND (start_date IS NULL OR start_date <= CURDATE())
           AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY created_at DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Validation error message under a form field. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="field-error">' . e($errors[$key]) . '</div>' : '';
}

/** Adds the "is-invalid" CSS class when a field has an error. */
function invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}
