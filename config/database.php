<?php
/**
 * QueueEase - Database connection (PDO)
 * File: config/database.php
 *
 * Returns one shared PDO connection for the whole request.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // return associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Keep MySQL time in step with PHP time (APP_TIMEZONE)
            $offset = (new DateTime('now'))->format('P');
            $pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            $message = APP_DEBUG
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Database connection failed. Please contact the administrator.';
            exit('<div style="font-family:sans-serif;padding:2rem;color:#b91c1c">'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
                . '<br><small>Check that MySQL is running in XAMPP and that the database "'
                . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '" has been imported.</small></div>');
        }
    }

    return $pdo;
}
