<?php
/**
 * QueueEase - Admin: assign staff to service counters
 * File: admin/assignments.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = db();

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'assign') {
        $userId    = int_param('user_id', 'post');
        $counterId = int_param('counter_id', 'post');

        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'staff'");
        $stmt->execute([$userId]);
        $staffName = $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT name FROM counters WHERE id = ?');
        $stmt->execute([$counterId]);
        $counterName = $stmt->fetchColumn();

        if (!$staffName || !$counterName) {
            set_flash('error', 'Choose a valid staff member and counter.');
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM staff_counters WHERE user_id = ? AND counter_id = ?');
            $stmt->execute([$userId, $counterId]);
            if ($stmt->fetchColumn()) {
                set_flash('warning', $staffName . ' is already assigned to ' . $counterName . '.');
            } else {
                $pdo->prepare('INSERT INTO staff_counters (user_id, counter_id) VALUES (?, ?)')->execute([$userId, $counterId]);
                log_activity('staff_assigned', 'Assigned ' . $staffName . ' to ' . $counterName);
                set_flash('success', $staffName . ' assigned to ' . $counterName . '.');
            }
        }
    } elseif ($action === 'remove') {
        $id   = int_param('id', 'post');
        $stmt = $pdo->prepare(
            'SELECT sc.user_id, sc.counter_id, u.full_name, c.name AS counter_name FROM staff_counters sc
               JOIN users u ON u.id = sc.user_id JOIN counters c ON c.id = sc.counter_id WHERE sc.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $busy = $pdo->prepare("SELECT 1 FROM queue_tickets WHERE counter_id = ? AND served_by = ? AND ticket_date = CURDATE() AND status IN ('called','serving')");
            $busy->execute([$row['counter_id'], $row['user_id']]);
            if ($busy->fetchColumn()) {
                set_flash('error', $row['full_name'] . ' is serving a customer at ' . $row['counter_name'] . '. Remove the assignment after the ticket is completed.');
            } else {
                $pdo->prepare('DELETE FROM staff_counters WHERE id = ?')->execute([$id]);
                log_activity('staff_unassigned', 'Removed ' . $row['full_name'] . ' from ' . $row['counter_name']);
                set_flash('success', $row['full_name'] . ' removed from ' . $row['counter_name'] . '.');
            }
        }
    }
    redirect('admin/assignments.php');
}

$staff    = $pdo->query("SELECT id, full_name, is_active FROM users WHERE role = 'staff' ORDER BY full_name")->fetchAll();
$counters = $pdo->query('SELECT c.id, c.name, c.is_active, s.name AS service_name FROM counters c JOIN services s ON s.id = c.service_id ORDER BY c.name')->fetchAll();
$assignments = $pdo->query(
    'SELECT sc.id, sc.assigned_at, u.full_name, u.is_active AS staff_active, c.name AS counter_name, c.is_active AS counter_active, s.name AS service_name
       FROM staff_counters sc
       JOIN users u ON u.id = sc.user_id
       JOIN counters c ON c.id = sc.counter_id
       JOIN services s ON s.id = c.service_id
      ORDER BY c.name, u.full_name'
)->fetchAll();

$pageTitle  = 'Counter Assignments';
$activeMenu = 'assignments';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2>Assign staff to counter</h2>
        <p class="card-subtitle mb-2">A staff member can work at several counters, and a counter can have several staff (e.g. shifts).</p>
        <?php if (!$staff || !$counters): ?>
            <div class="alert alert-warning"><span>You need at least one staff member and one counter. <a href="<?= url($staff ? 'admin/counters.php' : 'admin/staff.php') ?>">Create one now</a>.</span></div>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign">
            <div class="form-group">
                <label for="user_id">Staff member <span class="req">*</span></label>
                <select id="user_id" name="user_id" class="form-control" required>
                    <option value="">-- Select staff --</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['full_name']) ?><?= (int) $s['is_active'] ? '' : ' (inactive)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="counter_id">Counter <span class="req">*</span></label>
                <select id="counter_id" name="counter_id" class="form-control" required>
                    <option value="">-- Select counter --</option>
                    <?php foreach ($counters as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> &middot; <?= e($c['service_name']) ?><?= (int) $c['is_active'] ? '' : ' (closed)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= icon('link') ?> Assign</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>Current assignments</h2><p class="card-subtitle"><?= count($assignments) ?> assignment(s)</p></div>
            <div class="search-box"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search..." data-table-search="#assignTable"></div>
        </div>
        <div class="table-responsive">
            <table class="table" id="assignTable">
                <thead><tr><th>Counter</th><th>Service</th><th>Staff member</th><th>Assigned on</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><strong><?= e($a['counter_name']) ?></strong> <?= (int) $a['counter_active'] ? '' : '<span class="badge badge-inactive">Closed</span>' ?></td>
                        <td><?= e($a['service_name']) ?></td>
                        <td><?= e($a['full_name']) ?> <?= (int) $a['staff_active'] ? '' : '<span class="badge badge-inactive">Inactive</span>' ?></td>
                        <td class="small"><?= e(format_datetime($a['assigned_at'], 'd M Y')) ?></td>
                        <td class="actions">
                            <form method="post" class="inline-form" data-confirm="Remove <?= e($a['full_name']) ?> from <?= e($a['counter_name']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="btn btn-ghost-danger btn-sm"><?= icon('x') ?> Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assignments): ?>
                    <tr><td colspan="5" class="table-empty">No staff assigned yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
