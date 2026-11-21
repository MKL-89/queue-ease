<?php
/**
 * QueueEase - Customer: ticket history
 * File: customer/history.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('customer');

$pdo      = db();
$customer = current_customer();
if (!$customer) {
    exit('Customer profile not found. Please contact the administrator.');
}

$status = get('status');
$where  = 't.customer_id = ?';
$params = [$customer['id']];
if (in_array($status, ['waiting', 'called', 'serving', 'served', 'skipped', 'cancelled'], true)) {
    $where   .= ' AND t.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets t WHERE $where");
$stmt->execute($params);
$pager = paginate((int) $stmt->fetchColumn());

$stmt = $pdo->prepare(ticket_select_sql() . " WHERE $where ORDER BY t.created_at DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle  = 'My Ticket History';
$activeMenu = 'history';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2>Previous tickets</h2>
            <p class="card-subtitle"><?= (int) $pager['total'] ?> ticket(s) found</p>
        </div>
    </div>
    <form class="filter-bar" method="get">
        <div class="form-group">
            <label for="status">Status</label>
            <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['waiting', 'called', 'serving', 'served', 'skipped', 'cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group grow search-box">
            <label for="q">Quick search</label>
            <input type="search" id="q" class="form-control" placeholder="Ticket number, service..." data-table-search="#historyTable">
        </div>
    </form>
    <div class="table-responsive">
        <table class="table" id="historyTable">
            <thead><tr><th>Ticket</th><th>Service</th><th>Date</th><th>Counter</th><th>Status</th><th>Wait time</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><span class="ticket-no"><?= e($t['ticket_number']) ?></span></td>
                    <td><?= e($t['service_name']) ?></td>
                    <td class="nowrap"><?= e(format_datetime($t['created_at'])) ?></td>
                    <td><?= e($t['counter_name'] ?? '-') ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td><?= $t['called_at'] ? e(format_minutes((strtotime($t['called_at']) - strtotime($t['created_at'])) / 60)) : '-' ?></td>
                    <td class="actions"><a class="btn btn-outline btn-sm" href="<?= url('customer/my_ticket.php?id=' . (int) $t['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr><td colspan="7" class="table-empty">No tickets yet. <a href="<?= url('customer/get_ticket.php') ?>">Get your first ticket</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($pager) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
