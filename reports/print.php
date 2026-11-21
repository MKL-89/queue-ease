<?php
/**
 * QueueEase - Printable report (opens the browser print dialog; "Save as PDF" also works)
 * File: reports/print.php
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/report_functions.php';
$user = require_role('admin');

$pdo     = db();
$filters = report_filters();
$report  = get_report_data($pdo, $filters);
$sum     = $report['summary'];

$serviceName = 'All services';
if ($filters['service']) {
    $stmt = $pdo->prepare('SELECT name FROM services WHERE id = ?');
    $stmt->execute([$filters['service']]);
    $serviceName = $stmt->fetchColumn() ?: $serviceName;
}
log_activity('report_printed', 'Printed report ' . $filters['from'] . ' to ' . $filters['to']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Report <?= e($filters['from']) ?> to <?= e($filters['to']) ?> | <?= e(APP_NAME) ?></title>
    <script>document.documentElement.setAttribute('data-theme', 'light');</script>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
    <style>
        body { background: #fff; }
        .report { max-width: 1000px; margin: 0 auto; padding: 2rem 1.25rem; }
        .report-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 3px solid var(--primary); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .report-head h1 { font-size: 1.5rem; margin: 0; }
        .report-meta { text-align: right; font-size: .85rem; color: var(--text-muted); }
        .summary-table td { padding: .45rem .7rem; border: 1px solid var(--border); }
        .summary-table td:nth-child(odd) { color: var(--text-muted); width: 25%; }
        .summary-table td:nth-child(even) { font-weight: 700; }
        .signature { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 3rem; font-size: .85rem; }
        .signature div { border-top: 1px solid #000; padding-top: .4rem; }
    </style>
</head>
<body>
<div class="report">
    <div class="no-print btn-group mb-2">
        <button class="btn btn-primary" onclick="window.print()"><?= icon('printer') ?> Print / Save as PDF</button>
        <a class="btn btn-outline" href="<?= url('reports/index.php?' . http_build_query(['from' => $filters['from'], 'to' => $filters['to'], 'service' => $filters['service'] ?: ''])) ?>">Back to reports</a>
    </div>

    <div class="report-head">
        <div>
            <h1><?= e(INSTITUTION_NAME) ?></h1>
            <div class="muted">Queue Management Report &middot; <?= e(APP_NAME) ?></div>
        </div>
        <div class="report-meta">
            Period: <strong><?= e(format_datetime($filters['from'], 'd M Y')) ?> &ndash; <?= e(format_datetime($filters['to'], 'd M Y')) ?></strong><br>
            Service: <strong><?= e($serviceName) ?></strong><br>
            Generated: <?= e(date('d M Y, h:i A')) ?> by <?= e($user['name']) ?>
        </div>
    </div>

    <h2>Summary</h2>
    <table class="table summary-table mb-3">
        <tr><td>Tickets generated</td><td><?= (int) $sum['generated'] ?></td><td>Customers served</td><td><?= (int) $sum['served'] ?> (<?= percent($sum['served'], $sum['generated']) ?>)</td></tr>
        <tr><td>Still waiting</td><td><?= (int) $sum['waiting'] ?></td><td>In service</td><td><?= (int) $sum['in_service'] ?></td></tr>
        <tr><td>Cancelled tickets</td><td><?= (int) $sum['cancelled'] ?></td><td>Skipped tickets</td><td><?= (int) $sum['skipped'] ?></td></tr>
        <tr><td>Average waiting time</td><td><?= e(format_minutes($sum['avg_wait'])) ?></td><td>Longest wait</td><td><?= e(format_minutes($sum['max_wait'])) ?></td></tr>
        <tr><td>Average service time</td><td><?= e(format_minutes($sum['avg_service'])) ?></td><td>Unique customers</td><td><?= (int) $sum['customers'] ?></td></tr>
        <tr><td>Average customer rating</td><td><?= $sum['ratings'] ? e(number_format((float) $sum['avg_rating'], 1)) . ' / 5' : '-' ?></td><td>Ratings received</td><td><?= (int) $sum['ratings'] ?></td></tr>
    </table>

    <?php require __DIR__ . '/report_tables.php'; ?>

    <div class="signature">
        <div>Prepared by: <?= e($user['name']) ?></div>
        <div>Approved by / Signature &amp; Date</div>
    </div>
</div>
</body>
</html>
