<?php
/**
 * QueueEase - Activity logging
 * File: includes/logger.php
 */

/**
 * Record an action in the activity_logs table.
 * Logging must never break the main operation, so errors are ignored.
 */
function log_activity(string $action, string $description, ?int $userId = null): void
{
    try {
        if ($userId === null && !empty($_SESSION['user']['id'])) {
            $userId = (int) $_SESSION['user']['id'];
        }
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, substr($action, 0, 50), mb_substr($description, 0, 255), client_ip()]);
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}
