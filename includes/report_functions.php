<?php
/**
 * QueueEase - Report queries (shared by reports/index.php and reports/print.php)
 * File: includes/report_functions.php
 */

/** Read and validate the report filters from the query string. */
function report_filters(): array
{
    $from = valid_date(get('from')) ? get('from') : date('Y-m-d', strtotime('-6 days'));
    $to   = valid_date(get('to')) ? get('to') : date('Y-m-d');
    if ($to < $from) {
        [$from, $to] = [$to, $from];
    }
    return ['from' => $from, 'to' => $to, 'service' => int_param('service', 'get')];
}

/** All report figures for the selected period. */
function get_report_data(PDO $pdo, array $f): array
{
    $where  = 't.ticket_date BETWEEN ? AND ?';
    $params = [$f['from'], $f['to']];
    if ($f['service']) {
        $where   .= ' AND t.service_id = ?';
        $params[] = $f['service'];
    }

    // Overall summary
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS generated,
                SUM(t.status = 'served') AS served,
                SUM(t.status = 'waiting') AS waiting,
                SUM(t.status IN ('called','serving')) AS in_service,
                SUM(t.status = 'cancelled') AS cancelled,
                SUM(t.status = 'skipped') AS skipped,
                AVG(CASE WHEN t.called_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.called_at) END) / 60 AS avg_wait,
                MAX(CASE WHEN t.called_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.called_at) END) / 60 AS max_wait,
                AVG(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) END) / 60 AS avg_service,
                COUNT(DISTINCT t.customer_id) AS customers,
                AVG(fb.rating) AS avg_rating,
                COUNT(fb.id) AS ratings
           FROM queue_tickets t LEFT JOIN service_feedback fb ON fb.ticket_id = t.id WHERE $where"
    );
    $stmt->execute($params);
    $summary = array_map(fn($v) => $v ?? 0, $stmt->fetch());

    // Day by day
    $stmt = $pdo->prepare(
        "SELECT t.ticket_date,
                COUNT(*) AS generated,
                SUM(t.status = 'served') AS served,
                SUM(t.status = 'waiting') AS waiting,
                SUM(t.status = 'cancelled') AS cancelled,
                SUM(t.status = 'skipped') AS skipped,
                AVG(CASE WHEN t.called_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.called_at) END) / 60 AS avg_wait
           FROM queue_tickets t WHERE $where
          GROUP BY t.ticket_date ORDER BY t.ticket_date"
    );
    $stmt->execute($params);
    $daily = $stmt->fetchAll();

    // Service performance
    $stmt = $pdo->prepare(
        "SELECT s.name, s.prefix,
                COUNT(t.id) AS generated,
                SUM(t.status = 'served') AS served,
                SUM(t.status = 'cancelled') AS cancelled,
                SUM(t.status = 'skipped') AS skipped,
                AVG(CASE WHEN t.called_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, t.created_at, t.called_at) END) / 60 AS avg_wait,
                AVG(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) END) / 60 AS avg_service,
                AVG(fb.rating) AS avg_rating
           FROM services s
           JOIN queue_tickets t ON t.service_id = s.id AND $where
           LEFT JOIN service_feedback fb ON fb.ticket_id = t.id
          GROUP BY s.id, s.name, s.prefix
          ORDER BY generated DESC"
    );
    $stmt->execute($params);
    $services = $stmt->fetchAll();

    // Counter performance
    $stmt = $pdo->prepare(
        "SELECT c.name AS counter_name, s.name AS service_name,
                COUNT(t.id) AS handled,
                SUM(t.status = 'served') AS served,
                SUM(t.status = 'skipped') AS skipped,
                AVG(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) END) / 60 AS avg_service,
                SUM(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) ELSE 0 END) / 60 AS total_service
           FROM counters c
           JOIN services s ON s.id = c.service_id
           JOIN queue_tickets t ON t.counter_id = c.id AND $where
          GROUP BY c.id, c.name, s.name
          ORDER BY served DESC"
    );
    $stmt->execute($params);
    $counters = $stmt->fetchAll();

    // Staff performance
    $stmt = $pdo->prepare(
        "SELECT u.full_name,
                COUNT(t.id) AS handled,
                SUM(t.status = 'served') AS served,
                SUM(t.status = 'skipped') AS skipped,
                AVG(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) END) / 60 AS avg_service,
                AVG(fb.rating) AS avg_rating
           FROM users u
           JOIN queue_tickets t ON t.served_by = u.id AND $where
           LEFT JOIN service_feedback fb ON fb.ticket_id = t.id
          GROUP BY u.id, u.full_name
          ORDER BY served DESC"
    );
    $stmt->execute($params);
    $staff = $stmt->fetchAll();

    // Busiest hours
    $stmt = $pdo->prepare(
        "SELECT HOUR(t.created_at) AS hr, COUNT(*) AS total FROM queue_tickets t WHERE $where GROUP BY hr ORDER BY total DESC LIMIT 1"
    );
    $stmt->execute($params);
    $peak = $stmt->fetch();

    // Star rating distribution (1-5)
    $stmt = $pdo->prepare(
        "SELECT fb.rating, COUNT(*) AS total FROM service_feedback fb JOIN queue_tickets t ON t.id = fb.ticket_id
          WHERE $where GROUP BY fb.rating"
    );
    $stmt->execute($params);
    $byStar  = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $ratings = array_map(fn($star) => (int) ($byStar[$star] ?? 0), [1, 2, 3, 4, 5]);

    return compact('summary', 'daily', 'services', 'counters', 'staff', 'peak', 'ratings');
}

/** Percentage helper for "served rate" columns. */
function percent($part, $whole): string
{
    return $whole > 0 ? round($part / $whole * 100) . '%' : '-';
}
