<?php
/**
 * QueueEase - Ticket status API (JSON)
 * File: queue/api/ticket_status.php
 *
 * GET ?ref=TRK-XXXXXX  ->  live status, position and estimated wait.
 * Public: the reference code acts as the key. No personal data is returned.
 */
require_once __DIR__ . '/../../includes/init.php';

$ticket = find_ticket_by_reference(db(), get('ref'));
if (!$ticket) {
    json_response(['success' => false, 'message' => 'Ticket not found.'], 404);
}

json_response(['success' => true, 'ticket' => get_ticket_tracking(db(), $ticket)]);
