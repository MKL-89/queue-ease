<?php
/**
 * QueueEase - Report tables (included by reports/index.php and reports/print.php)
 * File: reports/report_tables.php
 * Needs: $report (from get_report_data())
 */
if (!isset($report)) {
    http_response_code(404);
    exit;
}
$sum = $report['summary'];
?>
<div class="card">
    <div class="card-header"><h2>Daily summary</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Date</th><th>Generated</th><th>Served</th><th>Waiting</th><th>Cancelled</th><th>Skipped</th><th>Served rate</th><th>Avg. wait</th></tr></thead>
            <tbody>
            <?php foreach ($report['daily'] as $d): ?>
                <tr>
                    <td class="nowrap"><?= e(format_datetime($d['ticket_date'], 'D, d M Y')) ?></td>
                    <td><?= (int) $d['generated'] ?></td>
                    <td><?= (int) $d['served'] ?></td>
                    <td><?= (int) $d['waiting'] ?></td>
                    <td><?= (int) $d['cancelled'] ?></td>
                    <td><?= (int) $d['skipped'] ?></td>
                    <td><?= percent($d['served'], $d['generated']) ?></td>
                    <td><?= e(format_minutes($d['avg_wait'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$report['daily']): ?>
                <tr><td colspan="8" class="table-empty">No tickets in this period.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($report['daily']): ?>
                <tfoot><tr><td>Total</td><td><?= (int) $sum['generated'] ?></td><td><?= (int) $sum['served'] ?></td><td><?= (int) $sum['waiting'] ?></td><td><?= (int) $sum['cancelled'] ?></td><td><?= (int) $sum['skipped'] ?></td><td><?= percent($sum['served'], $sum['generated']) ?></td><td><?= e(format_minutes($sum['avg_wait'])) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Service performance</h2></div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Service</th><th>Generated</th><th>Served</th><th>Cancelled</th><th>Skipped</th><th>Served rate</th><th>Avg. wait</th><th>Avg. service</th><th>Rating</th></tr></thead>
            <tbody>
            <?php foreach ($report['services'] as $s): ?>
                <tr>
                    <td><strong><?= e($s['name']) ?></strong> <small class="muted">(<?= e($s['prefix']) ?>)</small></td>
                    <td><?= (int) $s['generated'] ?></td>
                    <td><?= (int) $s['served'] ?></td>
                    <td><?= (int) $s['cancelled'] ?></td>
                    <td><?= (int) $s['skipped'] ?></td>
                    <td><?= percent($s['served'], $s['generated']) ?></td>
                    <td><?= e(format_minutes($s['avg_wait'])) ?></td>
                    <td><?= e(format_minutes($s['avg_service'])) ?></td>
                    <td><?= star_rating($s['avg_rating']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$report['services']): ?>
                <tr><td colspan="9" class="table-empty">No data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2>Counter performance</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Counter</th><th>Handled</th><th>Served</th><th>Skipped</th><th>Avg. service</th><th>Busy time</th></tr></thead>
                <tbody>
                <?php foreach ($report['counters'] as $c): ?>
                    <tr>
                        <td><strong><?= e($c['counter_name']) ?></strong><br><small class="muted"><?= e($c['service_name']) ?></small></td>
                        <td><?= (int) $c['handled'] ?></td>
                        <td><?= (int) $c['served'] ?></td>
                        <td><?= (int) $c['skipped'] ?></td>
                        <td><?= e(format_minutes($c['avg_service'])) ?></td>
                        <td><?= e(format_minutes($c['total_service'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['counters']): ?>
                    <tr><td colspan="6" class="table-empty">No data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Staff performance</h2></div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Staff member</th><th>Handled</th><th>Served</th><th>Skipped</th><th>Avg. service</th><th>Rating</th></tr></thead>
                <tbody>
                <?php foreach ($report['staff'] as $s): ?>
                    <tr>
                        <td><strong><?= e($s['full_name']) ?></strong></td>
                        <td><?= (int) $s['handled'] ?></td>
                        <td><?= (int) $s['served'] ?></td>
                        <td><?= (int) $s['skipped'] ?></td>
                        <td><?= e(format_minutes($s['avg_service'])) ?></td>
                        <td><?= star_rating($s['avg_rating']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$report['staff']): ?>
                    <tr><td colspan="6" class="table-empty">No data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
