<?php
/**
 * QueueEase - Customer dashboard
 * File: customer/dashboard.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('customer');

$pdo      = db();
$customer = current_customer();
if (!$customer) {
    exit('Customer profile not found. Please contact the administrator.');
}

$stmt = $pdo->prepare(ticket_select_sql() . "
     WHERE t.customer_id = ? AND t.status IN ('waiting','called','serving') AND t.ticket_date = CURDATE()
     ORDER BY t.created_at DESC");
$stmt->execute([$customer['id']]);
$activeTickets = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status = 'served') AS served,
            SUM(status = 'cancelled') AS cancelled
       FROM queue_tickets WHERE customer_id = ?"
);
$stmt->execute([$customer['id']]);
$totals = $stmt->fetch();

$announcements = get_active_announcements($pdo, 4);

$pageTitle  = 'My Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';

$hero = [
    'eyebrow' => 'Customer area',
    'text'    => $activeTickets
        ? 'You have ' . count($activeTickets) . ' active ticket(s). We will alert you when it is your turn.'
        : 'Skip the line: get a digital ticket and track your place in the queue live.',
    'actions' => [
        ['Get a ticket', 'customer/get_ticket.php', 'ticket', 'light'],
        ['My history', 'customer/history.php', 'history', 'glass'],
    ],
];
require __DIR__ . '/../includes/hero_banner.php';
?>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon tone-primary"><?= icon('ticket') ?></span><div><div class="stat-value"><?= count($activeTickets) ?></div><div class="stat-label">Active tickets</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-teal"><?= icon('history') ?></span><div><div class="stat-value"><?= (int) $totals['total'] ?></div><div class="stat-label">Total tickets</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><div><div class="stat-value"><?= (int) $totals['served'] ?></div><div class="stat-label">Times served</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('x-circle') ?></span><div><div class="stat-value"><?= (int) $totals['cancelled'] ?></div><div class="stat-label">Cancelled</div></div></div>
</div>

<div class="grid grid-wide-left">
    <div class="card">
        <div class="card-header">
            <div>
                <h2>My active tickets</h2>
                <p class="card-subtitle"><span class="live-dot"></span>Status updates automatically</p>
            </div>
            <a href="<?= url('customer/get_ticket.php') ?>" class="btn btn-primary"><?= icon('plus') ?> Get a ticket</a>
        </div>
        <?php if ($activeTickets): ?>
            <div class="mini-tickets">
                <?php foreach ($activeTickets as $t): $tr = get_ticket_tracking($pdo, $t); ?>
                    <div class="mini-ticket" data-mini-ticket="<?= e($t['reference_code']) ?>">
                        <div class="card-header mb-1">
                            <span class="ticket-no"><?= e($t['ticket_number']) ?></span>
                            <span data-mini="badge"><?= status_badge($t['status']) ?></span>
                        </div>
                        <div class="muted small"><?= e($t['service_name']) ?></div>
                        <p class="mt-1 mb-1" data-mini="message"><?= e($tr['message']) ?></p>
                        <div class="small" data-mini="meta">
                            <?php if ($t['status'] === 'waiting'): ?>
                                Position <strong><?= (int) $tr['position'] ?></strong> &middot; ~<?= e(format_minutes($tr['estimated_wait'])) ?>
                            <?php else: ?>
                                Counter: <strong><?= e($t['counter_name'] ?? '-') ?></strong>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-outline btn-sm mt-1" href="<?= url('customer/my_ticket.php?id=' . (int) $t['id']) ?>">View ticket</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon"><?= icon('ticket') ?></div>
                <p>You have no active tickets today.</p>
                <a href="<?= url('customer/get_ticket.php') ?>" class="btn btn-primary">Get a ticket</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h2>Announcements</h2></div>
        <?php foreach ($announcements as $a): ?>
            <div class="announcement">
                <span class="stat-icon tone-purple"><?= icon('megaphone') ?></span>
                <div><h4><?= e($a['title']) ?></h4><p><?= nl2br(e($a['message'])) ?></p></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$announcements): ?>
            <p class="muted">No announcements at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Refresh each active ticket card on the dashboard
(function () {
    var cards = document.querySelectorAll('[data-mini-ticket]');
    if (!cards.length) { return; }
    var labels = { waiting: 'Waiting', called: 'Called', serving: 'Serving', served: 'Served', skipped: 'Skipped', cancelled: 'Cancelled' };
    function refresh() {
        cards.forEach(function (card) {
            fetch(<?= json_encode(url('queue/api/ticket_status.php')) ?> + '?ref=' + encodeURIComponent(card.getAttribute('data-mini-ticket')), { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) { return; }
                    var t = res.ticket;
                    card.querySelector('[data-mini="badge"]').innerHTML = '<span class="badge badge-' + escapeHtml(t.status) + '">' + escapeHtml(labels[t.status]) + '</span>';
                    card.querySelector('[data-mini="message"]').textContent = t.message;
                    card.querySelector('[data-mini="meta"]').innerHTML = t.status === 'waiting'
                        ? 'Position <strong>' + t.position + '</strong> &middot; ~' + t.estimated_wait + ' min'
                        : 'Counter: <strong>' + escapeHtml(t.counter_name || '-') + '</strong>';
                    card.style.borderLeftColor = t.status === 'called' ? '#0284c7' : (t.status === 'serving' ? '#7c3aed' : '');
                });
        });
    }
    setInterval(refresh, <?= (int) TICKET_REFRESH_SECONDS * 1000 ?>);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
