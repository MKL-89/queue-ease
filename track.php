<?php
/**
 * QueueEase - Track a ticket using its reference (tracking) code
 * File: track.php
 */
require_once __DIR__ . '/includes/init.php';

$pdo       = db();
$reference = strtoupper(get('ref'));
$ticket    = null;
$error     = '';

if (is_post() && post('action') === 'cancel') {
    // Guests may cancel only tickets created in their own browser session
    require_csrf();
    $ticket = find_ticket_by_reference($pdo, post('ref'));
    $mine   = in_array($ticket['reference_code'] ?? '', $_SESSION['guest_tickets'] ?? [], true);
    if (!$ticket || !$mine) {
        set_flash('error', 'You can only cancel tickets you created on this device.');
    } else {
        try {
            cancel_ticket($pdo, (int) $ticket['id'], (int) $ticket['customer_id'], 'Cancelled by guest');
            set_flash('success', 'Ticket ' . $ticket['ticket_number'] . ' has been cancelled.');
        } catch (RuntimeException $ex) {
            set_flash('error', $ex->getMessage());
        }
    }
    redirect('track.php?ref=' . urlencode($ticket['reference_code'] ?? ''));
}

if ($reference !== '') {
    $ticket = find_ticket_by_reference($pdo, $reference);
    if (!$ticket) {
        $error = 'No ticket was found with that tracking code. Check the code and try again.';
    }
}

$pageTitle = $ticket ? 'Ticket ' . $ticket['ticket_number'] : 'Track Ticket';
require __DIR__ . '/includes/public_header.php';
?>
<?php if ($ticket):
    $tracking     = get_ticket_tracking($pdo, $ticket);
    $showCancel   = in_array($ticket['reference_code'], $_SESSION['guest_tickets'] ?? [], true);
    $cancelAction = url('track.php');
    $cancelExtraFields = '<input type="hidden" name="action" value="cancel">'
        . '<input type="hidden" name="ref" value="' . e($ticket['reference_code']) . '">';
?>
    <div class="no-print text-center mb-2">
        <a href="<?= url('track.php') ?>" class="small">&larr; Track a different ticket</a>
    </div>
    <?php require __DIR__ . '/includes/ticket_view.php'; ?>
<?php else: ?>
    <section class="auth-wrap">
        <div class="card auth-card">
            <div class="text-center mb-2">
                <div class="empty-icon"><?= icon('search') ?></div>
                <h2>Track your ticket</h2>
                <p class="muted">Enter the tracking code printed on your ticket, for example <span class="mono">TRK-8F3K2Q</span>.</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <form method="get">
                <div class="form-group">
                    <label for="ref">Tracking code</label>
                    <input type="text" id="ref" name="ref" class="form-control mono" value="<?= e($reference) ?>" placeholder="TRK-XXXXXX" maxlength="12" required autofocus style="text-transform:uppercase">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Track ticket</button>
            </form>
            <p class="auth-footer">Don't have a ticket? <a href="<?= url('queue/guest_ticket.php') ?>">Get one now</a></p>
        </div>
    </section>
<?php endif; ?>
<?php
$extraScripts = '<script src="' . url('assets/js/vendor/qrcode.js') . '"></script>'
              . '<script src="' . url('assets/js/ticket.js') . '"></script>';
require __DIR__ . '/includes/public_footer.php';
?>
