<?php
/**
 * QueueEase - Router for PHP's built-in web server
 * File: router.php
 *
 * Used only when running:  php -S localhost:8080 router.php
 * Apache (XAMPP) uses the .htaccess files instead.
 * It blocks direct access to private folders and serves everything else.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (preg_match('#^/(config|includes|database|docs|tests)(/|$)#i', $path) || preg_match('#/\.#', $path) || $path === '/router.php') {
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

$file = __DIR__ . $path;
if ($path === '/' || is_dir($file)) {
    $index = rtrim($file, '/') . '/index.php';
    if (is_file($index)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
        require $index;
        return true;
    }
    http_response_code(403);
    echo '403 Forbidden';
    return true;
}

return false; // let the built-in server handle the file normally
