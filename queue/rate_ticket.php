<?php
/**
 * QueueEase - Save a customer's star rating for a served ticket
 * File: queue/rate_ticket.php
 *
 * POST: ref (tracking code), rating (1-5), comment (optional), csrf_token
 * Allowed for: the logged-in customer who owns the ticket, or the guest
 * who created the ticket in this browser session.
 */
require_once __DIR__ . '/../includes/init.php';

if (!is_post()) {
    redirect('index.php');
}
require_csrf();

$pdo    = db();
$ticket = find_ticket_by_reference($pdo, post('ref'));
$back   = 'track.php?ref=' . urlencode(post('ref'));

if (!$ticket) {
    set_flash('error', 'Ticket not found.');
    redirect('track.php');
}
if (!can_rate_ticket($ticket)) {
    set_flash('error', 'You can only rate your own tickets.');
    redirect($back);
}
if (user_role() === 'customer') {
    $back = 'customer/my_ticket.php?id=' . (int) $ticket['id'];
}

$rating  = filter_var(post('rating'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$comment = mb_substr(post('comment'), 0, 255);

if ($ticket['status'] !== 'served') {
    set_flash('error', 'You can rate the service after you have been served.');
} elseif ($ticket['feedback_rating'] !== null) {
    set_flash('warning', 'You have already rated this visit. Thank you!');
} elseif ($rating === false) {
    set_flash('error', 'Please choose between 1 and 5 stars.');
} else {
    try {
        $pdo->prepare('INSERT INTO service_feedback (ticket_id, rating, comment) VALUES (?, ?, ?)')
            ->execute([$ticket['id'], $rating, $comment !== '' ? $comment : null]);
        log_activity('feedback_given', 'Ticket ' . $ticket['ticket_number'] . ' rated ' . $rating . '/5');
        set_flash('success', 'Thank you for rating our service!');
    } catch (PDOException $ex) {
        // UNIQUE(ticket_id) stops double submissions
        set_flash('warning', 'You have already rated this visit. Thank you!');
    }
}
redirect($back);
