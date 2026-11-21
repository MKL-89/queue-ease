<?php
/**
 * QueueEase - Staff: my service history
 * File: staff/history.php
 */
require_once __DIR__ . '/../includes/init.php';
$user = require_role('staff');

$pdo    = db();
$from   = valid_date(get('from')) ? get('from') : date('Y-m-d', strtotime('-6 days'));
$to     = valid_date(get('to')) ? get('to') : date('Y-m-d');
$status = get('status');

$where  = 't.served_by = ? AND t.ticket_date BETWEEN ? AND ?';
$params = [(int) $user['id'], $from, $to];
if (in_array($status, ['called', 'serving', 'served', 'skipped', 'cancelled'], true)) {
    $where   .= ' AND t.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(t.status = 'served') AS served, SUM(t.status = 'skipped') AS skipped,
            AVG(CASE WHEN t.status = 'served' THEN TIMESTAMPDIFF(SECOND, t.service_started_at, t.completed_at) END) / 60 AS avg_service
       FROM queue_tickets t WHERE $where"
);
$stmt->execute($params);
$summary = $stmt->fetch();
$pager   = paginate((int) $summary['total']);

$stmt = $pdo->prepare(ticket_select_sql() . " WHERE $where ORDER BY t.called_at DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle  = 'Service History';
$activeMenu = 'history';
require __DIR__ . '/../includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon tone-primary"><?= icon('ticket') ?></span><div><div class="stat-value"><?= (int) $summary['total'] ?></div><div class="stat-label">Tickets handled</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><div><div class="stat-value"><?= (int) $summary['served'] ?></div><div class="stat-label">Served</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('skip') ?></span><div><div class="stat-value"><?= (int) $summary['skipped'] ?></div><div class="stat-label">Skipped</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-info"><?= icon('clock') ?></span><div><div class="stat-value"><?= e(format_minutes($summary['avg_service'])) ?></div><div class="stat-label">Avg. service time</div></div></div>
</div>

<div class="card">
    <form class="filter-bar" method="get">
        <div class="form-group"><label for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="form-group"><label for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All</option>
                <?php foreach (['called', 'serving', 'served', 'skipped', 'cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
    </form>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Ticket</th><th>Customer</th><th>Service</th><th>Counter</th><th>Called</th><th>Service time</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><span class="ticket-no"><?= e($t['ticket_number']) ?></span><br><small class="muted"><?= e(format_datetime($t['ticket_date'], 'd M Y')) ?></small></td>
                    <td><?= e($t['customer_name'] ?? '-') ?></td>
                    <td><?= e($t['service_name']) ?></td>
                    <td><?= e($t['counter_name'] ?? '-') ?></td>
                    <td><?= e(format_time($t['called_at'])) ?></td>
                    <td><?= $t['service_started_at'] && $t['completed_at'] ? e(format_minutes((strtotime($t['completed_at']) - strtotime($t['service_started_at'])) / 60)) : '-' ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr><td colspan="7" class="table-empty">No tickets in this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($pager) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
