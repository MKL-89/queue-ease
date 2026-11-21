<?php
/**
 * QueueEase - Core queue logic
 * File: includes/queue_functions.php
 *
 * All ticket generation and queue processing lives here so that the
 * rules are applied the same way everywhere (customer pages, guest page,
 * staff console, admin pages and the AJAX endpoints).
 */

const ACTIVE_STATUSES = ['waiting', 'called', 'serving'];

/**
 * Tickets are only valid on the day they were issued.
 * Waiting/called tickets left over from previous days are cancelled and
 * tickets still "serving" from a previous day are closed as served.
 */
function expire_old_tickets(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE queue_tickets
            SET status = 'cancelled', cancelled_at = NOW(), notes = 'Expired at end of day'
          WHERE status IN ('waiting','called') AND ticket_date < CURDATE()"
    );
    $pdo->exec(
        "UPDATE queue_tickets
            SET status = 'served', completed_at = COALESCE(completed_at, NOW()), notes = 'Closed automatically at end of day'
          WHERE status = 'serving' AND ticket_date < CURDATE()"
    );
}

/** Random tracking code such as TRK-8F3K2Q (no confusing characters like 0/O, 1/I). */
function generate_reference_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $check    = $pdo->prepare('SELECT 1 FROM queue_tickets WHERE reference_code = ?');
    do {
        $code = 'TRK-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $check->execute([$code]);
    } while ($check->fetchColumn());
    return $code;
}

/**
 * Create a new ticket for a customer.
 *
 * Uses a transaction and locks the service row (SELECT ... FOR UPDATE) so
 * two people taking a ticket at the same moment can never get the same
 * sequence number. The UNIQUE key (service_id, ticket_date, sequence_no)
 * is a second safety net.
 *
 * @throws RuntimeException with a user-friendly message on failure
 */
function create_ticket(PDO $pdo, int $serviceId, int $customerId): array
{
    expire_old_tickets($pdo);

    $pdo->beginTransaction();
    try {
        // 1. Lock the service row for the rest of this transaction
        $stmt = $pdo->prepare('SELECT id, name, prefix, is_active FROM services WHERE id = ? FOR UPDATE');
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();
        if (!$service || (int) $service['is_active'] !== 1) {
            throw new RuntimeException('The selected service is not available.');
        }

        // 2. At least one active counter must be open for this service
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM counters WHERE service_id = ? AND is_active = 1');
        $stmt->execute([$serviceId]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException('No counter is currently open for ' . $service['name'] . '.');
        }

        // 3. Prevent duplicate active tickets for the same customer + service
        $stmt = $pdo->prepare(
            "SELECT ticket_number FROM queue_tickets
              WHERE customer_id = ? AND service_id = ? AND ticket_date = CURDATE()
                AND status IN ('waiting','called','serving')
              LIMIT 1"
        );
        $stmt->execute([$customerId, $serviceId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            throw new RuntimeException('You already have an active ticket (' . $existing . ') for ' . $service['name'] . '.');
        }

        // 4. Next sequence number for this service today
        $today = date('Y-m-d');
        $stmt  = $pdo->prepare('SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM queue_tickets WHERE service_id = ? AND ticket_date = ?');
        $stmt->execute([$serviceId, $today]);
        $sequence = (int) $stmt->fetchColumn();

        $ticketNumber  = $service['prefix'] . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $referenceCode = generate_reference_code($pdo);

        // 5. Save the ticket
        $stmt = $pdo->prepare(
            "INSERT INTO queue_tickets (ticket_number, reference_code, service_id, customer_id, ticket_date, sequence_no, status)
             VALUES (?, ?, ?, ?, ?, ?, 'waiting')"
        );
        $stmt->execute([$ticketNumber, $referenceCode, $serviceId, $customerId, $today, $sequence]);
        $ticketId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        error_log('Ticket creation failed: ' . $e->getMessage());
        throw new RuntimeException('The ticket could not be created. Please try again.');
    }

    log_activity('ticket_created', 'Ticket ' . $ticketNumber . ' created for ' . $service['name']);
    return get_ticket($pdo, $ticketId);
}

/** One ticket with its service, counter and customer details. */
function get_ticket(PDO $pdo, int $ticketId): ?array
{
    $stmt = $pdo->prepare(ticket_select_sql() . ' WHERE t.id = ?');
    $stmt->execute([$ticketId]);
    return $stmt->fetch() ?: null;
}

function find_ticket_by_reference(PDO $pdo, string $reference): ?array
{
    $reference = strtoupper(trim($reference));
    if (!preg_match('/^(TRK|DEMO)-[A-Z0-9]{4,8}$/', $reference)) {
        return null;
    }
    $stmt = $pdo->prepare(ticket_select_sql() . ' WHERE t.reference_code = ?');
    $stmt->execute([$reference]);
    return $stmt->fetch() ?: null;
}

function ticket_select_sql(): string
{
    return "SELECT t.*, s.name AS service_name, s.prefix, c.name AS counter_name, c.location AS counter_location,
                   cu.full_name AS customer_name, cu.phone AS customer_phone, cu.is_guest,
                   u.full_name AS staff_name,
                   fb.rating AS feedback_rating, fb.comment AS feedback_comment
              FROM queue_tickets t
              LEFT JOIN service_feedback fb ON fb.ticket_id = t.id
              JOIN services s       ON s.id = t.service_id
              LEFT JOIN counters c  ON c.id = t.counter_id
              LEFT JOIN customers cu ON cu.id = t.customer_id
              LEFT JOIN users u     ON u.id = t.served_by";
}

/** Average real service time (minutes) today, or the configured value as a fallback. */
function service_avg_minutes(PDO $pdo, int $serviceId): float
{
    $stmt = $pdo->prepare(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, service_started_at, completed_at)) / 60
           FROM queue_tickets
          WHERE service_id = ? AND status = 'served' AND ticket_date = CURDATE()
            AND service_started_at IS NOT NULL AND completed_at IS NOT NULL"
    );
    $stmt->execute([$serviceId]);
    $avg = $stmt->fetchColumn();
    if ($avg !== null && $avg !== false && (float) $avg > 0) {
        return (float) $avg;
    }
    $stmt = $pdo->prepare('SELECT avg_service_minutes FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    return (float) ($stmt->fetchColumn() ?: 5);
}

function active_counter_count(PDO $pdo, int $serviceId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM counters WHERE service_id = ? AND is_active = 1');
    $stmt->execute([$serviceId]);
    return max(1, (int) $stmt->fetchColumn());
}

/**
 * Live tracking information for a ticket:
 * people ahead, queue position and estimated waiting time.
 */
function get_ticket_tracking(PDO $pdo, array $ticket): array
{
    $ahead    = 0;
    $position = 0;
    $estimate = 0;

    if ($ticket['status'] === 'waiting') {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM queue_tickets
              WHERE service_id = ? AND ticket_date = ? AND status = 'waiting' AND sequence_no < ?"
        );
        $stmt->execute([$ticket['service_id'], $ticket['ticket_date'], $ticket['sequence_no']]);
        $ahead    = (int) $stmt->fetchColumn();
        $position = $ahead + 1;
        $avg      = service_avg_minutes($pdo, (int) $ticket['service_id']);
        $counters = active_counter_count($pdo, (int) $ticket['service_id']);
        $estimate = (int) ceil(($position * $avg) / $counters);
    }

    $messages = [
        'waiting'   => $ahead === 0 ? 'You are next in line. Please stay close.' : 'Please wait. We will call your number.',
        'called'    => 'It is your turn! Please go to ' . ($ticket['counter_name'] ?? 'the counter') . ' now.',
        'serving'   => 'You are being served at ' . ($ticket['counter_name'] ?? 'the counter') . '.',
        'served'    => 'Service completed. Thank you for visiting.',
        'skipped'   => 'Your ticket was skipped because you did not respond. Please see the reception desk.',
        'cancelled' => 'This ticket has been cancelled.',
    ];

    return [
        'ticket_number'   => $ticket['ticket_number'],
        'reference_code'  => $ticket['reference_code'],
        'service_name'    => $ticket['service_name'],
        'status'          => $ticket['status'],
        'status_label'    => ucfirst($ticket['status']),
        'counter_name'    => $ticket['counter_name'],
        'people_ahead'    => $ahead,
        'position'        => $position,
        'estimated_wait'  => $estimate,
        'message'         => $messages[$ticket['status']] ?? '',
        'issued_at'       => format_datetime($ticket['created_at']),
        'called_at'       => $ticket['called_at'] ? format_time($ticket['called_at']) : null,
        'recall_count'    => (int) $ticket['recall_count'],
        'is_active'       => in_array($ticket['status'], ACTIVE_STATUSES, true),
    ];
}

/** Active services with their current waiting count and estimated wait. */
function get_services_overview(PDO $pdo): array
{
    $services = $pdo->query(
        "SELECT s.*,
                (SELECT COUNT(*) FROM queue_tickets t
                  WHERE t.service_id = s.id AND t.ticket_date = CURDATE() AND t.status = 'waiting') AS waiting_count,
                (SELECT COUNT(*) FROM counters c WHERE c.service_id = s.id AND c.is_active = 1) AS open_counters
           FROM services s
          WHERE s.is_active = 1
          ORDER BY s.name"
    )->fetchAll();

    foreach ($services as &$service) {
        $avg = service_avg_minutes($pdo, (int) $service['id']);
        $counters = max(1, (int) $service['open_counters']);
        $service['estimated_wait'] = (int) ceil((((int) $service['waiting_count']) + 1) * $avg / $counters);
    }
    return $services;
}

// =====================================================================
//  STAFF / COUNTER OPERATIONS
// =====================================================================

/** Counters assigned to a staff member (active counters and services only). */
function get_staff_counters(PDO $pdo, int $staffId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.location, c.service_id, s.name AS service_name, s.prefix
           FROM staff_counters sc
           JOIN counters c ON c.id = sc.counter_id AND c.is_active = 1
           JOIN services s ON s.id = c.service_id AND s.is_active = 1
          WHERE sc.user_id = ?
          ORDER BY c.name"
    );
    $stmt->execute([$staffId]);
    return $stmt->fetchAll();
}

/** Return the counter if the staff member is assigned to it, otherwise null. */
function staff_counter(PDO $pdo, int $staffId, int $counterId): ?array
{
    foreach (get_staff_counters($pdo, $staffId) as $counter) {
        if ((int) $counter['id'] === $counterId) {
            return $counter;
        }
    }
    return null;
}

/** The ticket currently called/being served at a counter (if any). */
function get_counter_current_ticket(PDO $pdo, int $counterId): ?array
{
    $stmt = $pdo->prepare(ticket_select_sql() . "
          WHERE t.counter_id = ? AND t.status IN ('called','serving') AND t.ticket_date = CURDATE()
          ORDER BY t.called_at DESC LIMIT 1");
    $stmt->execute([$counterId]);
    return $stmt->fetch() ?: null;
}

/**
 * CALL NEXT
 * Takes the oldest waiting ticket for the counter's service and assigns
 * it to this counter.
 *
 * Concurrency protection:
 *  - the counter row is locked (FOR UPDATE) so the same counter cannot
 *    call two tickets at once (e.g. double click);
 *  - the chosen ticket row is locked (FOR UPDATE);
 *  - the UPDATE only succeeds if the ticket is STILL 'waiting', so if
 *    another counter took it first, rowCount() is 0 and we try again.
 *
 * Returns the called ticket, or null when nobody is waiting.
 */
function call_next_ticket(PDO $pdo, int $staffId, int $counterId): ?array
{
    $counter = staff_counter($pdo, $staffId, $counterId);
    if (!$counter) {
        throw new RuntimeException('You are not assigned to this counter.');
    }
    expire_old_tickets($pdo);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('SELECT id FROM counters WHERE id = ? FOR UPDATE')->execute([$counterId]);

            $stmt = $pdo->prepare(
                "SELECT ticket_number FROM queue_tickets
                  WHERE counter_id = ? AND status IN ('called','serving') AND ticket_date = CURDATE() LIMIT 1"
            );
            $stmt->execute([$counterId]);
            if ($current = $stmt->fetchColumn()) {
                throw new RuntimeException('Finish or skip ticket ' . $current . ' before calling the next customer.');
            }

            $stmt = $pdo->prepare(
                "SELECT id, ticket_number FROM queue_tickets
                  WHERE service_id = ? AND ticket_date = CURDATE() AND status = 'waiting'
                  ORDER BY sequence_no ASC
                  LIMIT 1
                  FOR UPDATE"
            );
            $stmt->execute([$counter['service_id']]);
            $next = $stmt->fetch();

            if (!$next) {
                $pdo->commit();
                return null;
            }

            $update = $pdo->prepare(
                "UPDATE queue_tickets
                    SET status = 'called', counter_id = ?, served_by = ?, called_at = NOW()
                  WHERE id = ? AND status = 'waiting'"
            );
            $update->execute([$counterId, $staffId, $next['id']]);

            if ($update->rowCount() === 1) {
                $pdo->commit();
                log_activity('ticket_called', 'Called ticket ' . $next['ticket_number'] . ' to ' . $counter['name']);
                return get_ticket($pdo, (int) $next['id']);
            }

            // Someone else took this ticket a moment ago - try again
            $pdo->rollBack();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
    throw new RuntimeException('The queue is busy. Please try again.');
}

/**
 * Recall / Start / Complete / Skip for the counter's current ticket.
 * Each UPDATE checks the expected current status, so an action can never
 * be applied twice or to a ticket in the wrong state.
 */
function staff_ticket_action(PDO $pdo, int $staffId, int $counterId, string $action): array
{
    $counter = staff_counter($pdo, $staffId, $counterId);
    if (!$counter) {
        throw new RuntimeException('You are not assigned to this counter.');
    }

    $ticket = get_counter_current_ticket($pdo, $counterId);
    if (!$ticket) {
        throw new RuntimeException('There is no current ticket at this counter.');
    }

    $rules = [
        //  action     => [required status, SQL SET part, log text]
        'recall'   => ['called',  "recall_count = recall_count + 1, called_at = NOW()", 'Recalled'],
        'start'    => ['called',  "status = 'serving', service_started_at = NOW()",       'Started serving'],
        'complete' => ['serving', "status = 'served', completed_at = NOW()",              'Completed'],
        'skip'     => ['called',  "status = 'skipped', notes = 'Customer did not respond'", 'Skipped'],
    ];

    if (!isset($rules[$action])) {
        throw new RuntimeException('Unknown action.');
    }
    [$requiredStatus, $setSql, $logText] = $rules[$action];

    if ($ticket['status'] !== $requiredStatus) {
        throw new RuntimeException('This action is not allowed while the ticket is ' . $ticket['status'] . '.');
    }

    $stmt = $pdo->prepare(
        "UPDATE queue_tickets SET $setSql, served_by = ?
          WHERE id = ? AND counter_id = ? AND status = ?"
    );
    $stmt->execute([$staffId, $ticket['id'], $counterId, $requiredStatus]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The ticket was changed by someone else. Please refresh.');
    }

    log_activity('ticket_' . $action, $logText . ' ticket ' . $ticket['ticket_number'] . ' at ' . $counter['name']);
    return get_ticket($pdo, (int) $ticket['id']);
}

/**
 * May the current visitor rate this ticket?
 * Yes for the customer who owns it, or a guest who created it in this browser session.
 */
function can_rate_ticket(array $ticket): bool
{
    if (user_role() === 'customer') {
        $customer = current_customer();
        return $customer && (int) $customer['id'] === (int) $ticket['customer_id'];
    }
    return in_array($ticket['reference_code'], $_SESSION['guest_tickets'] ?? [], true);
}

/**
 * Cancel a ticket.
 *  - customers may cancel their own WAITING tickets ($customerId given)
 *  - administrators may cancel WAITING or CALLED tickets
 */
function cancel_ticket(PDO $pdo, int $ticketId, ?int $customerId = null, string $reason = ''): array
{
    $ticket = get_ticket($pdo, $ticketId);
    if (!$ticket) {
        throw new RuntimeException('Ticket not found.');
    }
    if ($customerId !== null && (int) $ticket['customer_id'] !== $customerId) {
        throw new RuntimeException('You can only cancel your own tickets.');
    }

    $allowed = $customerId !== null ? ['waiting'] : ['waiting', 'called'];
    if (!in_array($ticket['status'], $allowed, true)) {
        throw new RuntimeException('Ticket ' . $ticket['ticket_number'] . ' can no longer be cancelled.');
    }

    $placeholders = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $pdo->prepare(
        "UPDATE queue_tickets SET status = 'cancelled', cancelled_at = NOW(), notes = ?
          WHERE id = ? AND status IN ($placeholders)"
    );
    $stmt->execute(array_merge([$reason !== '' ? mb_substr($reason, 0, 255) : 'Cancelled', $ticketId], $allowed));

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The ticket status changed. Please refresh and try again.');
    }

    log_activity('ticket_cancelled', 'Cancelled ticket ' . $ticket['ticket_number'] . ($reason ? ' (' . $reason . ')' : ''));
    return get_ticket($pdo, $ticketId);
}
