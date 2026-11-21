<?php
/**
 * QueueEase - Reusable live ticket card
 * File: includes/ticket_view.php
 *
 * Needs: $ticket (row from get_ticket()) and $tracking (from get_ticket_tracking()).
 * Optional: $showCancel (bool) and $cancelAction (form URL).
 * assets/js/ticket.js refreshes the values in this card automatically.
 */
$showCancel   = $showCancel ?? false;
$cancelAction = $cancelAction ?? '';
?>
<div class="ticket-card" id="liveTicket"
     data-reference="<?= e($ticket['reference_code']) ?>"
     data-status="<?= e($tracking['status']) ?>"
     data-endpoint="<?= url('queue/api/ticket_status.php') ?>"
     data-interval="<?= (int) TICKET_REFRESH_SECONDS ?>">
    <div class="ticket-top">
        <small><?= e(INSTITUTION_NAME) ?></small>
        <div class="ticket-number" data-field="ticket_number"><?= e($ticket['ticket_number']) ?></div>
        <div class="ticket-service"><?= e($ticket['service_name']) ?></div>
    </div>
    <div class="ticket-cut"></div>
    <div class="ticket-body">
        <div class="ticket-status-msg st-<?= e($tracking['status']) ?>" data-field="message"><?= e($tracking['message']) ?></div>

        <div class="ticket-stats">
            <div class="ticket-stat"><strong data-field="position"><?= $tracking['position'] ?: '-' ?></strong><span>Position</span></div>
            <div class="ticket-stat"><strong data-field="people_ahead"><?= $tracking['status'] === 'waiting' ? (int) $tracking['people_ahead'] : '-' ?></strong><span>Ahead of you</span></div>
            <div class="ticket-stat"><strong data-field="estimated_wait"><?= $tracking['status'] === 'waiting' ? e(format_minutes($tracking['estimated_wait'])) : '-' ?></strong><span>Est. wait</span></div>
        </div>

        <table class="ticket-details">
            <tr><td>Status</td><td data-field="status_badge"><?= status_badge($tracking['status']) ?></td></tr>
            <tr><td>Counter</td><td data-field="counter_name"><?= e($tracking['counter_name'] ?? 'To be assigned') ?></td></tr>
            <tr><td>Tracking code</td><td class="mono"><?= e($ticket['reference_code']) ?></td></tr>
            <tr><td>Issued</td><td><?= e(format_datetime($ticket['created_at'])) ?></td></tr>
            <tr><td>Called at</td><td data-field="called_at"><?= e($tracking['called_at'] ?? '-') ?></td></tr>
        </table>

        <?php if ($ticket['status'] === 'served' && $ticket['feedback_rating'] !== null): ?>
            <div class="rating-box">
                <h4>Your rating</h4>
                <div style="font-size:1.6rem"><?= star_rating($ticket['feedback_rating']) ?></div>
                <?php if ($ticket['feedback_comment']): ?><p class="small muted mb-0">"<?= e($ticket['feedback_comment']) ?>"</p><?php endif; ?>
            </div>
        <?php elseif ($ticket['status'] === 'served' && can_rate_ticket($ticket)): ?>
            <form class="rating-box no-print" method="post" action="<?= url('queue/rate_ticket.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="ref" value="<?= e($ticket['reference_code']) ?>">
                <h4>How was your service?</h4>
                <p class="small muted mb-0">Rate your visit to help us improve.</p>
                <div class="stars" role="radiogroup" aria-label="Rating">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                        <input type="radio" id="star<?= $s ?>" name="rating" value="<?= $s ?>" required>
                        <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">&#9733;</label>
                    <?php endfor; ?>
                </div>
                <input type="text" name="comment" class="form-control mb-1" maxlength="255" placeholder="Comment (optional)">
                <button type="submit" class="btn btn-primary btn-block"><?= icon('sparkle') ?> Submit rating</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($ticket['status'], ACTIVE_STATUSES, true)): ?>
            <div class="ticket-qr">
                <div class="qr" data-qr="<?= e(absolute_url('track.php?ref=' . urlencode($ticket['reference_code']))) ?>"></div>
                <div>
                    <strong>Scan to track on your phone</strong>
                    <p>Opens this ticket's live status. Code: <span class="mono"><?= e($ticket['reference_code']) ?></span></p>
                </div>
            </div>
        <?php endif; ?>

        <p class="small muted text-center mt-2 mb-0 no-print" data-live-note>
            <span class="live-dot"></span>This page updates automatically. Keep it open.
        </p>

        <div class="btn-group mt-2 no-print" style="justify-content:center">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><?= icon('printer') ?> Print</button>
            <button type="button" class="btn btn-outline btn-sm" id="enableAlerts"><?= icon('bell') ?> Alert me when called</button>
            <?php if ($showCancel && $tracking['status'] === 'waiting'): ?>
                <form method="post" action="<?= e($cancelAction) ?>" class="inline-form" data-confirm="Cancel ticket <?= e($ticket['ticket_number']) ?>?" data-cancel-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
                    <?= $cancelExtraFields ?? '' ?>
                    <button type="submit" class="btn btn-ghost-danger btn-sm"><?= icon('x-circle') ?> Cancel ticket</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
