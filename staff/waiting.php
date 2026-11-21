<?php
/**
 * QueueEase - Staff: waiting customers for the services of my counters
 * File: staff/waiting.php
 */
require_once __DIR__ . '/../includes/init.php';
$user = require_role('staff');

$pdo        = db();
$counters   = get_staff_counters($pdo, (int) $user['id']);
$serviceIds = array_values(array_unique(array_map(fn($c) => (int) $c['service_id'], $counters)));

$tickets = [];
if ($serviceIds) {
    $in   = implode(',', array_fill(0, count($serviceIds), '?'));
    $stmt = $pdo->prepare(ticket_select_sql() . "
         WHERE t.service_id IN ($in) AND t.ticket_date = CURDATE() AND t.status IN ('waiting','called','serving')
         ORDER BY FIELD(t.status, 'serving', 'called', 'waiting'), t.service_id, t.sequence_no");
    $stmt->execute($serviceIds);
    $tickets = $stmt->fetchAll();
}

$pageTitle  = 'Waiting Customers';
$activeMenu = 'waiting';
$extraHead  = '<meta http-equiv="refresh" content="20">';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2>Today's queue</h2>
            <p class="card-subtitle"><span class="live-dot"></span>Services: <?= e(implode(', ', array_unique(array_column($counters, 'service_name'))) ?: 'none') ?> &middot; refreshes every 20 seconds</p>
        </div>
        <div class="search-box" style="min-width:220px">
            <?= icon('search') ?>
            <input type="search" class="form-control" placeholder="Search..." data-table-search="#waitingTable">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="waitingTable">
            <thead><tr><th>#</th><th>Ticket</th><th>Customer</th><th>Service</th><th>Issued</th><th>Waiting for</th><th>Counter</th><th>Status</th></tr></thead>
            <tbody>
            <?php $pos = 0; foreach ($tickets as $t): ?>
                <tr>
                    <td><?= $t['status'] === 'waiting' ? ++$pos : '-' ?></td>
                    <td><span class="ticket-no"><?= e($t['ticket_number']) ?></span></td>
                    <td><?= e($t['customer_name'] ?? '-') ?><?= $t['is_guest'] ? ' <span class="badge badge-guest">Guest</span>' : '' ?></td>
                    <td><?= e($t['service_name']) ?></td>
                    <td><?= e(format_time($t['created_at'])) ?></td>
                    <td><?= e(format_minutes((time() - strtotime($t['created_at'])) / 60)) ?></td>
                    <td><?= e($t['counter_name'] ?? '-') ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr><td colspan="8" class="table-empty">No customers are waiting for your services.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
