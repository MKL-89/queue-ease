<?php
/**
 * QueueEase - Live display data API (JSON)
 * File: queue/api/display_data.php
 *
 * Polled by public/display.php every few seconds. Returns only ticket
 * numbers, counters and services (no customer names) because the
 * display is public.
 */
require_once __DIR__ . '/../../includes/init.php';

$pdo = db();
expire_old_tickets($pdo);

// Each active counter with the ticket it is currently handling
$counters = $pdo->query(
    "SELECT c.id, c.name, s.name AS service_name, s.prefix,
            t.ticket_number, t.status, t.called_at
       FROM counters c
       JOIN services s ON s.id = c.service_id
       LEFT JOIN queue_tickets t ON t.id = (
            SELECT t2.id FROM queue_tickets t2
             WHERE t2.counter_id = c.id AND t2.ticket_date = CURDATE() AND t2.status IN ('called','serving')
             ORDER BY t2.called_at DESC LIMIT 1)
      WHERE c.is_active = 1
      ORDER BY c.name"
)->fetchAll();

// Recently called tickets (most recent first)
$recent = $pdo->query(
    "SELECT t.id, t.ticket_number, t.status, t.called_at, t.recall_count, c.name AS counter_name, s.name AS service_name
       FROM queue_tickets t
       JOIN counters c ON c.id = t.counter_id
       JOIN services s ON s.id = t.service_id
      WHERE t.ticket_date = CURDATE() AND t.called_at IS NOT NULL
      ORDER BY t.called_at DESC
      LIMIT 8"
)->fetchAll();

// Waiting tickets per service (first 6 numbers of each)
$services = $pdo->query(
    "SELECT s.id, s.name, s.prefix FROM services s WHERE s.is_active = 1 ORDER BY s.name"
)->fetchAll();
$waitStmt = $pdo->prepare(
    "SELECT ticket_number FROM queue_tickets
      WHERE service_id = ? AND ticket_date = CURDATE() AND status = 'waiting'
      ORDER BY sequence_no LIMIT 6"
);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets WHERE service_id = ? AND ticket_date = CURDATE() AND status = 'waiting'");
$waiting = [];
foreach ($services as $s) {
    $waitStmt->execute([$s['id']]);
    $countStmt->execute([$s['id']]);
    $waiting[] = [
        'service' => $s['name'],
        'prefix'  => $s['prefix'],
        'count'   => (int) $countStmt->fetchColumn(),
        'tickets' => $waitStmt->fetchAll(PDO::FETCH_COLUMN),
    ];
}

$announcements = array_map(
    fn($a) => ['title' => $a['title'], 'message' => $a['message']],
    get_active_announcements($pdo, 10)
);

json_response([
    'success'       => true,
    'server_time'   => date('c'),
    'counters'      => array_map(fn($c) => [
        'name'          => $c['name'],
        'service'       => $c['service_name'],
        'ticket_number' => $c['ticket_number'],
        'status'        => $c['status'],
    ], $counters),
    'recent'        => array_map(fn($r) => [
        // "key" changes on every call AND every recall, so the display knows what to announce
        'key'           => $r['id'] . '-' . $r['called_at'] . '-' . $r['recall_count'],
        'ticket_number' => $r['ticket_number'],
        'counter'       => $r['counter_name'],
        'service'       => $r['service_name'],
        'status'        => $r['status'],
        'time'          => format_time($r['called_at']),
        'announce'      => $r['status'] === 'called',
    ], $recent),
    'waiting'       => $waiting,
    'announcements' => $announcements,
]);
