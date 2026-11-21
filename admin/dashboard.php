<?php
/**
 * QueueEase - Administrator dashboard
 * File: admin/dashboard.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();
expire_old_tickets($pdo);

// ---- Summary cards ------------------------------------------------------
$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM customers) AS total_customers,
        (SELECT COUNT(*) FROM queue_tickets WHERE ticket_date = CURDATE()) AS tickets_today,
        (SELECT COUNT(*) FROM queue_tickets WHERE ticket_date = CURDATE() AND status = 'waiting') AS waiting_now,
        (SELECT COUNT(*) FROM queue_tickets WHERE ticket_date = CURDATE() AND status IN ('called','serving')) AS in_service,
        (SELECT COUNT(*) FROM queue_tickets WHERE ticket_date = CURDATE() AND status = 'served') AS served_today,
        (SELECT COUNT(*) FROM counters WHERE is_active = 1) AS active_counters,
        (SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, called_at)) / 60 FROM queue_tickets
          WHERE ticket_date = CURDATE() AND called_at IS NOT NULL) AS avg_wait_today,
        (SELECT AVG(rating) FROM service_feedback WHERE created_at >= CURDATE() - INTERVAL 29 DAY) AS avg_rating_30d"
)->fetch();

// ---- Chart: last 7 days generated vs served ----------------------------
$stmt = $pdo->query(
    "SELECT ticket_date, COUNT(*) AS generated, SUM(status = 'served') AS served,
            SUM(status IN ('skipped','cancelled')) AS not_served
       FROM queue_tickets
      WHERE ticket_date >= CURDATE() - INTERVAL 6 DAY
      GROUP BY ticket_date"
);
$byDate = [];
foreach ($stmt->fetchAll() as $row) {
    $byDate[$row['ticket_date']] = $row;
}
$daily = ['labels' => [], 'generated' => [], 'served' => [], 'not_served' => []];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily['labels'][]     = date('D d', strtotime($d));
    $daily['generated'][]  = (int) ($byDate[$d]['generated'] ?? 0);
    $daily['served'][]     = (int) ($byDate[$d]['served'] ?? 0);
    $daily['not_served'][] = (int) ($byDate[$d]['not_served'] ?? 0);
}

// ---- Chart: today's tickets by service ----------------------------------
$byService = $pdo->query(
    "SELECT s.name, COUNT(t.id) AS total
       FROM services s
       LEFT JOIN queue_tickets t ON t.service_id = s.id AND t.ticket_date = CURDATE()
      GROUP BY s.id, s.name ORDER BY s.name"
)->fetchAll();

// ---- Chart: today's status breakdown ------------------------------------
$statusRows = $pdo->query(
    "SELECT status, COUNT(*) AS total FROM queue_tickets WHERE ticket_date = CURDATE() GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$statusOrder = ['waiting', 'called', 'serving', 'served', 'skipped', 'cancelled'];
$statusData  = array_map(fn($s) => (int) ($statusRows[$s] ?? 0), $statusOrder);

// ---- Counter status -----------------------------------------------------
$counterStatus = $pdo->query(
    "SELECT c.name, c.is_active, s.name AS service_name,
            (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM staff_counters sc JOIN users u ON u.id = sc.user_id
              WHERE sc.counter_id = c.id AND u.is_active = 1) AS staff_names,
            (SELECT CONCAT(t.ticket_number, '|', t.status) FROM queue_tickets t
              WHERE t.counter_id = c.id AND t.ticket_date = CURDATE() AND t.status IN ('called','serving')
              ORDER BY t.called_at DESC LIMIT 1) AS current_ticket,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.counter_id = c.id AND t.ticket_date = CURDATE() AND t.status = 'served') AS served_today
       FROM counters c JOIN services s ON s.id = c.service_id
      ORDER BY c.name"
)->fetchAll();

// ---- Latest tickets -----------------------------------------------------
$recent = $pdo->query(ticket_select_sql() . ' ORDER BY t.created_at DESC LIMIT 8')->fetchAll();

$chartData = [
    'daily'    => $daily,
    'services' => ['labels' => array_column($byService, 'name'), 'data' => array_map('intval', array_column($byService, 'total'))],
    'status'   => ['labels' => array_map('ucfirst', $statusOrder), 'data' => $statusData],
];

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';

$hero = [
    'eyebrow' => 'Administrator overview',
    'text'    => 'Here is how ' . INSTITUTION_NAME . ' is running today.',
    'metrics' => [
        [(string) (int) $stats['tickets_today'], 'Tickets today'],
        [(string) (int) $stats['waiting_now'], 'Waiting now'],
        [$stats['avg_rating_30d'] !== null ? number_format((float) $stats['avg_rating_30d'], 1) . ' / 5' : '-', 'Satisfaction'],
    ],
    'actions' => [
        ['All tickets', 'admin/tickets.php', 'ticket', 'light'],
        ['Reports', 'reports/index.php', 'chart', 'glass'],
        ['Live display', 'public/display.php', 'tv', 'glass', true],
    ],
];
require __DIR__ . '/../includes/hero_banner.php';
?>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon tone-primary"><?= icon('users') ?></span><div><div class="stat-value"><?= (int) $stats['total_customers'] ?></div><div class="stat-label">Total customers</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-info"><?= icon('ticket') ?></span><div><div class="stat-value"><?= (int) $stats['tickets_today'] ?></div><div class="stat-label">Tickets today</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-warning"><?= icon('hourglass') ?></span><div><div class="stat-value"><?= (int) $stats['waiting_now'] ?></div><div class="stat-label">Waiting now</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-purple"><?= icon('activity') ?></span><div><div class="stat-value"><?= (int) $stats['in_service'] ?></div><div class="stat-label">Being served</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><div><div class="stat-value"><?= (int) $stats['served_today'] ?></div><div class="stat-label">Served today</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-teal"><?= icon('monitor') ?></span><div><div class="stat-value"><?= (int) $stats['active_counters'] ?></div><div class="stat-label">Active counters</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('clock') ?></span><div><div class="stat-value"><?= e(format_minutes($stats['avg_wait_today'])) ?></div><div class="stat-label">Avg. wait today</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-gold" style="font-size:1.4rem">&#9733;</span><div><div class="stat-value"><?= $stats['avg_rating_30d'] !== null ? e(number_format((float) $stats['avg_rating_30d'], 1)) : '-' ?></div><div class="stat-label">Satisfaction (30 days)</div></div></div>
</div>

<div class="grid grid-wide-left">
    <div class="card">
        <div class="card-header">
            <div><h2>Daily queue statistics</h2><p class="card-subtitle">Tickets generated and served over the last 7 days</p></div>
            <a href="<?= url('reports/index.php') ?>" class="btn btn-outline btn-sm"><?= icon('chart') ?> Full reports</a>
        </div>
        <div class="chart-box"><canvas id="dailyChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>Today by status</h2><p class="card-subtitle"><?= e(date('d M Y')) ?></p></div></div>
        <div class="chart-box"><canvas id="statusChart"></canvas></div>
    </div>
</div>

<div class="grid grid-wide-left">
    <div class="card">
        <div class="card-header">
            <div><h2>Counter status</h2><p class="card-subtitle">Live view of every service counter</p></div>
            <a href="<?= url('admin/counters.php') ?>" class="btn btn-outline btn-sm">Manage</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Counter</th><th>Service</th><th>Staff</th><th>Current</th><th>Served today</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($counterStatus as $c): $cur = $c['current_ticket'] ? explode('|', $c['current_ticket']) : null; ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td><?= e($c['service_name']) ?></td>
                        <td><?= e($c['staff_names'] ?: 'Not assigned') ?></td>
                        <td><?= $cur ? '<span class="ticket-no">' . e($cur[0]) . '</span> ' . status_badge($cur[1]) : '<span class="muted">Idle</span>' ?></td>
                        <td><?= (int) $c['served_today'] ?></td>
                        <td><?= active_badge($c['is_active']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$counterStatus): ?>
                    <tr><td colspan="6" class="table-empty">No counters created yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>Today by service</h2></div></div>
        <div class="chart-box"><canvas id="serviceChart"></canvas></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div><h2>Latest tickets</h2></div>
        <a href="<?= url('admin/tickets.php') ?>" class="btn btn-outline btn-sm">View all tickets</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Ticket</th><th>Customer</th><th>Service</th><th>Counter</th><th>Issued</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $t): ?>
                <tr>
                    <td><span class="ticket-no"><?= e($t['ticket_number']) ?></span></td>
                    <td><?= e($t['customer_name'] ?? '-') ?></td>
                    <td><?= e($t['service_name']) ?></td>
                    <td><?= e($t['counter_name'] ?? '-') ?></td>
                    <td class="nowrap"><?= e(format_datetime($t['created_at'])) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr><td colspan="6" class="table-empty">No tickets yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script id="chartData" type="application/json"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php
$extraScripts = '<script src="' . url('assets/js/vendor/chart.umd.min.js') . '"></script>'
              . '<script src="' . url('assets/js/charts.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
?>
