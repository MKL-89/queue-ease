<?php
/**
 * QueueEase - Admin: system activity logs
 * File: admin/activity_logs.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo    = db();
$from   = valid_date(get('from')) ? get('from') : date('Y-m-d', strtotime('-6 days'));
$to     = valid_date(get('to')) ? get('to') : date('Y-m-d');
$role   = in_array(get('role'), ['admin', 'staff', 'customer', 'none'], true) ? get('role') : '';
$action = get('action');
$q      = get('q');

$where  = ['l.created_at >= ? AND l.created_at < ? + INTERVAL 1 DAY'];
$params = [$from . ' 00:00:00', $to];
if ($role === 'none') {
    $where[] = 'l.user_id IS NULL';
} elseif ($role) {
    $where[]  = 'u.role = ?';
    $params[] = $role;
}
if ($action !== '') {
    $where[]  = 'l.action = ?';
    $params[] = $action;
}
if ($q !== '') {
    $where[] = '(l.description LIKE ? OR u.full_name LIKE ? OR l.ip_address LIKE ?)';
    $like    = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $whereSql");
$stmt->execute($params);
$pager = paginate((int) $stmt->fetchColumn(), 30);

$stmt = $pdo->prepare(
    "SELECT l.*, u.full_name, u.role FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
      WHERE $whereSql ORDER BY l.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

function log_tone(string $action): string
{
    if (str_contains($action, 'failed') || str_contains($action, 'denied') || str_contains($action, 'deleted') || str_contains($action, 'blocked')) {
        return 'badge-cancelled';
    }
    if (str_starts_with($action, 'ticket_')) {
        return 'badge-called';
    }
    if (str_starts_with($action, 'log')) {
        return 'badge-served';
    }
    return 'badge-role';
}

$pageTitle  = 'Activity Logs';
$activeMenu = 'logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div><h2>System activity</h2><p class="card-subtitle"><?= (int) $pager['total'] ?> record(s) &middot; every login, change and queue action is recorded here</p></div>
    </div>
    <form class="filter-bar" method="get">
        <div class="form-group"><label for="from">From</label><input type="date" id="from" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="form-group"><label for="to">To</label><input type="date" id="to" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="form-group">
            <label for="role">User role</label>
            <select id="role" name="role" class="form-control">
                <option value="">All</option>
                <?php foreach (['admin' => 'Administrator', 'staff' => 'Staff', 'customer' => 'Customer', 'none' => 'Guest / system'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $role === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="action">Action</label>
            <select id="action" name="action" class="form-control">
                <option value="">All actions</option>
                <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group grow"><label for="q">Search</label><input type="search" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Description, user, IP"></div>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?> Filter</button>
    </form>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Date &amp; time</th><th>User</th><th>Action</th><th>Description</th><th>IP address</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="nowrap small"><?= e(format_datetime($log['created_at'], 'd M Y, h:i:s A')) ?></td>
                    <td><?= e($log['full_name'] ?? 'Guest / system') ?><?= $log['role'] ? '<br><small class="muted">' . e(ucfirst($log['role'])) . '</small>' : '' ?></td>
                    <td><span class="badge <?= log_tone($log['action']) ?>"><?= e($log['action']) ?></span></td>
                    <td><?= e($log['description']) ?></td>
                    <td class="mono small"><?= e($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="5" class="table-empty">No activity found for these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($pager) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
