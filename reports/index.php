<?php
/**
 * QueueEase - Reports and analytics (administrator)
 * File: reports/index.php
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/report_functions.php';
require_role('admin');

$pdo      = db();
$filters  = report_filters();
$report   = get_report_data($pdo, $filters);
$services = $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();
$sum      = $report['summary'];

$chartData = [
    'trend' => [
        'labels'    => array_map(fn($d) => date('d M', strtotime($d['ticket_date'])), $report['daily']),
        'generated' => array_map(fn($d) => (int) $d['generated'], $report['daily']),
        'served'    => array_map(fn($d) => (int) $d['served'], $report['daily']),
    ],
    'services' => [
        'labels' => array_column($report['services'], 'name'),
        'data'   => array_map(fn($s) => (int) $s['generated'], $report['services']),
    ],
    'ratings' => $report['ratings'],
];

$pageTitle  = 'Reports';
$activeMenu = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="card no-print">
    <form class="filter-bar mb-0" method="get">
        <div class="form-group"><label for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= e($filters['from']) ?>"></div>
        <div class="form-group"><label for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= e($filters['to']) ?>"></div>
        <div class="form-group">
            <label for="service">Service</label>
            <select id="service" name="service" class="form-control">
                <option value="">All services</option>
                <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['service'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?> Generate</button>
        <a class="btn btn-outline" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
        <a class="btn btn-outline" href="?from=<?= date('Y-m-d', strtotime('-29 days')) ?>&to=<?= date('Y-m-d') ?>">Last 30 days</a>
        <a class="btn btn-success" target="_blank" rel="noopener" href="<?= url('reports/print.php?' . http_build_query(['from' => $filters['from'], 'to' => $filters['to'], 'service' => $filters['service'] ?: ''])) ?>"><?= icon('printer') ?> Printable report</a>
    </form>
</div>

<p class="muted">Report period: <strong><?= e(format_datetime($filters['from'], 'd M Y')) ?></strong> to <strong><?= e(format_datetime($filters['to'], 'd M Y')) ?></strong></p>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon tone-primary"><?= icon('ticket') ?></span><div><div class="stat-value"><?= (int) $sum['generated'] ?></div><div class="stat-label">Tickets generated</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><div><div class="stat-value"><?= (int) $sum['served'] ?></div><div class="stat-label">Customers served (<?= percent($sum['served'], $sum['generated']) ?>)</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-warning"><?= icon('hourglass') ?></span><div><div class="stat-value"><?= (int) $sum['waiting'] ?></div><div class="stat-label">Still waiting</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('x-circle') ?></span><div><div class="stat-value"><?= (int) $sum['cancelled'] ?></div><div class="stat-label">Cancelled</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('skip') ?></span><div><div class="stat-value"><?= (int) $sum['skipped'] ?></div><div class="stat-label">Skipped</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-info"><?= icon('clock') ?></span><div><div class="stat-value"><?= e(format_minutes($sum['avg_wait'])) ?></div><div class="stat-label">Average waiting time</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-purple"><?= icon('activity') ?></span><div><div class="stat-value"><?= e(format_minutes($sum['avg_service'])) ?></div><div class="stat-label">Average service time</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-primary"><?= icon('users') ?></span><div><div class="stat-value"><?= (int) $sum['customers'] ?></div><div class="stat-label">Unique customers</div></div></div>
    <div class="stat-card"><span class="stat-icon tone-gold">&#9733;</span><div><div class="stat-value"><?= $sum['ratings'] ? e(number_format((float) $sum['avg_rating'], 1)) : '-' ?></div><div class="stat-label">Avg. rating (<?= (int) $sum['ratings'] ?> reviews)</div></div></div>
</div>

<div class="grid grid-wide-left">
    <div class="card">
        <div class="card-header"><div><h2>Daily trend</h2><p class="card-subtitle">Generated vs served per day</p></div></div>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><div><h2>Tickets by service</h2><p class="card-subtitle">Peak hour: <?= $report['peak'] ? e(sprintf('%02d:00 - %02d:00', $report['peak']['hr'], $report['peak']['hr'] + 1)) : '-' ?></p></div></div>
        <div class="chart-box"><canvas id="serviceChart"></canvas></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div><h2>Customer satisfaction</h2><p class="card-subtitle">Star ratings given by customers after being served &middot; average <?= $sum['ratings'] ? e(number_format((float) $sum['avg_rating'], 1)) . ' / 5' : 'no ratings yet' ?></p></div></div>
    <div class="chart-box" style="height:240px"><canvas id="ratingChart"></canvas></div>
</div>

<?php require __DIR__ . '/report_tables.php'; ?>

<script id="chartData" type="application/json"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php
$extraScripts = '<script src="' . url('assets/js/vendor/chart.umd.min.js') . '"></script>'
              . '<script src="' . url('assets/js/charts.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
?>
