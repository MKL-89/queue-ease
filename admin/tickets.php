<?php
/**
 * QueueEase - Admin: view, search and filter all tickets; cancel tickets
 * File: admin/tickets.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();
expire_old_tickets($pdo);

if (is_post()) {
    require_csrf();
    if (post('action') === 'cancel') {
        try {
            $reason = post('reason') ?: 'Cancelled by administrator';
            $ticket = cancel_ticket($pdo, int_param('ticket_id', 'post'), null, $reason);
            set_flash('success', 'Ticket ' . $ticket['ticket_number'] . ' cancelled.');
        } catch (RuntimeException $ex) {
            set_flash('error', $ex->getMessage());
        }
    }
    $back = $_SERVER['HTTP_REFERER'] ?? url('admin/tickets.php');
    header('Location: ' . $back);
    exit;
}

$statuses = ['waiting', 'called', 'serving', 'served', 'skipped', 'cancelled'];
$from      = valid_date(get('from')) ? get('from') : date('Y-m-d');
$to        = valid_date(get('to')) ? get('to') : date('Y-m-d');
$status    = in_array(get('status'), $statuses, true) ? get('status') : '';
$serviceId = int_param('service', 'get');
$counterId = int_param('counter', 'get');
$q         = get('q');

$where  = ['t.ticket_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($status) {
    $where[]  = 't.status = ?';
    $params[] = $status;
}
if ($serviceId) {
    $where[]  = 't.service_id = ?';
    $params[] = $serviceId;
}
if ($counterId) {
    $where[]  = 't.counter_id = ?';
    $params[] = $counterId;
}
if ($q !== '') {
    $where[] = '(t.ticket_number LIKE ? OR t.reference_code LIKE ? OR cu.full_name LIKE ? OR cu.phone LIKE ?)';
    $like    = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

// ---- CSV export of the filtered tickets (opens in Excel) ---------------
if (get('export') === 'csv') {
    $stmt = $pdo->prepare(ticket_select_sql() . " WHERE $whereSql ORDER BY t.created_at, t.id");
    $stmt->execute($params);
    log_activity('tickets_exported', 'Exported tickets ' . $from . ' to ' . $to . ' as CSV');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="queueease-tickets-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows characters correctly
    fputcsv($out, ['Ticket', 'Tracking code', 'Date', 'Service', 'Customer', 'Phone', 'Counter', 'Staff', 'Status',
                   'Issued', 'Called', 'Service started', 'Completed', 'Wait (min)', 'Service (min)', 'Rating', 'Notes']);
    while ($t = $stmt->fetch()) {
        // Prefix values starting with =,+,-,@ so Excel never runs them as formulas
        $safe = fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v;
        fputcsv($out, array_map($safe, [
            $t['ticket_number'], $t['reference_code'], $t['ticket_date'], $t['service_name'],
            $t['customer_name'], $t['customer_phone'], $t['counter_name'], $t['staff_name'], ucfirst($t['status']),
            $t['created_at'], $t['called_at'], $t['service_started_at'], $t['completed_at'],
            $t['called_at'] ? round((strtotime($t['called_at']) - strtotime($t['created_at'])) / 60, 1) : '',
            $t['service_started_at'] && $t['completed_at'] ? round((strtotime($t['completed_at']) - strtotime($t['service_started_at'])) / 60, 1) : '',
            $t['feedback_rating'], $t['notes'],
        ]));
    }
    fclose($out);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.status, COUNT(*) AS total FROM queue_tickets t LEFT JOIN customers cu ON cu.id = t.customer_id
      WHERE $whereSql GROUP BY t.status"
);
$stmt->execute($params);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$pager  = paginate((int) array_sum($counts));

$stmt = $pdo->prepare(ticket_select_sql() . " WHERE $whereSql ORDER BY t.created_at DESC, t.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$services = $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();
$counters = $pdo->query('SELECT id, name FROM counters ORDER BY name')->fetchAll();

$pageTitle  = 'All Tickets';
$activeMenu = 'tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="stats-grid">
    <?php
    $tones = ['waiting' => 'warning', 'called' => 'info', 'serving' => 'purple', 'served' => 'success', 'skipped' => 'danger', 'cancelled' => 'danger'];
    $icons = ['waiting' => 'hourglass', 'called' => 'bell', 'serving' => 'activity', 'served' => 'check-circle', 'skipped' => 'skip', 'cancelled' => 'x-circle'];
    foreach ($statuses as $s): ?>
        <div class="stat-card"><span class="stat-icon tone-<?= $tones[$s] ?>"><?= icon($icons[$s]) ?></span><div><div class="stat-value"><?= (int) ($counts[$s] ?? 0) ?></div><div class="stat-label"><?= ucfirst($s) ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <form class="filter-bar" method="get">
        <div class="form-group"><label for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="form-group"><label for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="form-group">
            <label for="service">Service</label>
            <select id="service" name="service" class="form-control">
                <option value="">All services</option>
                <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $serviceId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="counter">Counter</label>
            <select id="counter" name="counter" class="form-control">
                <option value="">All counters</option>
                <?php foreach ($counters as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $counterId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group grow"><label for="q">Search</label><input type="search" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Ticket, code, name, phone"></div>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
        <a class="btn btn-outline" href="<?= url('admin/tickets.php') ?>">Reset</a>
        <a class="btn btn-success" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => null]))) ?>"><?= icon('list') ?> Export CSV</a>
    </form>

    <p class="small muted"><?= (int) $pager['total'] ?> ticket(s) between <?= e(format_datetime($from, 'd M Y')) ?> and <?= e(format_datetime($to, 'd M Y')) ?></p>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Ticket</th><th>Customer</th><th>Service</th><th>Counter / Staff</th><th>Issued</th><th>Called</th><th>Wait</th><th>Status</th><th>Rating</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td><span class="ticket-no"><?= e($t['ticket_number']) ?></span><br><small class="muted mono"><?= e($t['reference_code']) ?></small></td>
                    <td><?= e($t['customer_name'] ?? 'Deleted customer') ?><?= $t['is_guest'] ? ' <span class="badge badge-guest">Guest</span>' : '' ?><br><small class="muted"><?= e($t['customer_phone'] ?? '') ?></small></td>
                    <td><?= e($t['service_name']) ?></td>
                    <td><?= e($t['counter_name'] ?? '-') ?><br><small class="muted"><?= e($t['staff_name'] ?? '') ?></small></td>
                    <td class="nowrap small"><?= e(format_datetime($t['created_at'], 'd M, h:i A')) ?></td>
                    <td class="nowrap small"><?= e(format_time($t['called_at'])) ?></td>
                    <td class="small"><?= $t['called_at'] ? e(format_minutes((strtotime($t['called_at']) - strtotime($t['created_at'])) / 60)) : '-' ?></td>
                    <td><?= status_badge($t['status']) ?><?= $t['notes'] ? '<br><small class="muted">' . e($t['notes']) . '</small>' : '' ?></td>
                    <td><?= $t['feedback_rating'] !== null ? star_rating($t['feedback_rating']) : '<span class="muted">-</span>' ?></td>
                    <td class="actions">
                        <?php if (in_array($t['status'], ['waiting', 'called'], true)): ?>
                            <form method="post" class="inline-form" data-confirm="Cancel ticket <?= e($t['ticket_number']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                                <button class="btn btn-ghost-danger btn-sm"><?= icon('x-circle') ?> Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tickets): ?>
                <tr><td colspan="10" class="table-empty">No tickets match your filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($pager) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
