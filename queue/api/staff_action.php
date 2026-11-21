<?php
/**
 * QueueEase - Staff counter API (JSON)
 * File: queue/api/staff_action.php
 *
 * GET  ?counter_id=ID                 -> current state of the counter
 * POST counter_id=ID&action=call|recall|start|complete|skip (+ csrf_token)
 *                                     -> performs the action, returns the new state
 * Only logged-in staff assigned to the counter may use it.
 */
require_once __DIR__ . '/../../includes/init.php';

$user      = require_role('staff', true);
$pdo       = db();
$staffId   = (int) $user['id'];
$counterId = int_param('counter_id');

$counter = staff_counter($pdo, $staffId, $counterId);
if (!$counter) {
    json_response(['success' => false, 'message' => 'You are not assigned to this counter.'], 403);
}

/** Everything the console needs to draw itself. */
function counter_state(PDO $pdo, array $counter, int $staffId): array
{
    $current = get_counter_current_ticket($pdo, (int) $counter['id']);

    $stmt = $pdo->prepare(
        "SELECT t.ticket_number, t.created_at, cu.full_name
           FROM queue_tickets t LEFT JOIN customers cu ON cu.id = t.customer_id
          WHERE t.service_id = ? AND t.ticket_date = CURDATE() AND t.status = 'waiting'
          ORDER BY t.sequence_no LIMIT 10"
    );
    $stmt->execute([$counter['service_id']]);
    $waiting = array_map(fn($r) => [
        'ticket_number' => $r['ticket_number'],
        'customer'      => $r['full_name'] ?? 'Customer',
        'since'         => format_time($r['created_at']),
        'waited'        => format_minutes((time() - strtotime($r['created_at'])) / 60),
    ], $stmt->fetchAll());

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets WHERE service_id = ? AND ticket_date = CURDATE() AND status = 'waiting'");
    $stmt->execute([$counter['service_id']]);
    $waitingCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT SUM(status = 'served') AS served, SUM(status = 'skipped') AS skipped,
                AVG(CASE WHEN status = 'served' THEN TIMESTAMPDIFF(SECOND, service_started_at, completed_at) END) / 60 AS avg_service
           FROM queue_tickets WHERE served_by = ? AND ticket_date = CURDATE()"
    );
    $stmt->execute([$staffId]);
    $mine = $stmt->fetch();

    return [
        'counter' => ['id' => (int) $counter['id'], 'name' => $counter['name'], 'service' => $counter['service_name']],
        'current' => $current ? [
            'id'            => (int) $current['id'],
            'ticket_number' => $current['ticket_number'],
            'status'        => $current['status'],
            'customer'      => $current['customer_name'] ?? 'Customer',
            'phone'         => $current['customer_phone'] ?? '',
            'called_at'     => format_time($current['called_at']),
            'started_at'    => $current['service_started_at'] ? format_time($current['service_started_at']) : null,
            'recall_count'  => (int) $current['recall_count'],
        ] : null,
        'waiting'       => $waiting,
        'waiting_count' => $waitingCount,
        'served_today'  => (int) ($mine['served'] ?? 0),
        'skipped_today' => (int) ($mine['skipped'] ?? 0),
        'avg_service'   => $mine['avg_service'] !== null ? format_minutes($mine['avg_service']) : '-',
    ];
}

if (is_post()) {
    require_csrf(true);
    $action = post('action');

    try {
        if ($action === 'call') {
            $ticket  = call_next_ticket($pdo, $staffId, $counterId);
            $message = $ticket ? 'Now calling ' . $ticket['ticket_number'] . '.' : 'No customers are waiting right now.';
        } elseif (in_array($action, ['recall', 'start', 'complete', 'skip'], true)) {
            $ticket   = staff_ticket_action($pdo, $staffId, $counterId, $action);
            $messages = [
                'recall'   => 'Ticket ' . $ticket['ticket_number'] . ' recalled.',
                'start'    => 'Serving ' . $ticket['ticket_number'] . '.',
                'complete' => 'Ticket ' . $ticket['ticket_number'] . ' marked as served.',
                'skip'     => 'Ticket ' . $ticket['ticket_number'] . ' skipped.',
            ];
            $message = $messages[$action];
        } else {
            json_response(['success' => false, 'message' => 'Unknown action.'], 400);
        }
        json_response(['success' => true, 'message' => $message, 'state' => counter_state($pdo, $counter, $staffId)]);
    } catch (RuntimeException $ex) {
        json_response(['success' => false, 'message' => $ex->getMessage(), 'state' => counter_state($pdo, $counter, $staffId)], 409);
    }
}

json_response(['success' => true, 'state' => counter_state($pdo, $counter, $staffId)]);
