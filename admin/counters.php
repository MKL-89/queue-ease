<?php
/**
 * QueueEase - Admin: manage service counters
 * File: admin/counters.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo      = db();
$errors   = [];
$services = $pdo->query('SELECT id, name, prefix, is_active FROM services ORDER BY name')->fetchAll();

/** Is a ticket currently being handled at this counter? */
function counter_is_busy(PDO $pdo, int $counterId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM queue_tickets WHERE counter_id = ? AND ticket_date = CURDATE() AND status IN ('called','serving') LIMIT 1");
    $stmt->execute([$counterId]);
    return (bool) $stmt->fetchColumn();
}

if (is_post()) {
    require_csrf();
    $action = post('action');
    $id     = int_param('id', 'post');

    if ($action === 'save') {
        $name      = post('name');
        $serviceId = int_param('service_id', 'post');
        $location  = post('location');
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            $errors['name'] = 'Counter name must be 2-50 characters.';
        }
        if (!in_array($serviceId, array_map('intval', array_column($services, 'id')), true)) {
            $errors['service_id'] = 'Choose a valid service.';
        }
        if (mb_strlen($location) > 100) {
            $errors['location'] = 'Location must not exceed 100 characters.';
        }
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT 1 FROM counters WHERE name = ? AND id <> ?');
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn()) {
                $errors['name'] = 'A counter with this name already exists.';
            }
        }
        if (!$errors && $id && counter_is_busy($pdo, $id)) {
            $stmt = $pdo->prepare('SELECT service_id, is_active FROM counters WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if ($old && ((int) $old['service_id'] !== $serviceId || $isActive === 0)) {
                $errors['general'] = 'This counter is serving a customer. Finish the current ticket before changing its service or deactivating it.';
            }
        }

        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE counters SET name = ?, service_id = ?, location = ?, is_active = ? WHERE id = ?')
                    ->execute([$name, $serviceId, $location ?: null, $isActive, $id]);
                log_activity('counter_updated', 'Updated counter ' . $name);
                set_flash('success', 'Counter "' . $name . '" updated.');
            } else {
                $pdo->prepare('INSERT INTO counters (name, service_id, location, is_active) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $serviceId, $location ?: null, $isActive]);
                log_activity('counter_created', 'Created counter ' . $name);
                set_flash('success', 'Counter "' . $name . '" created. Remember to assign staff to it.');
            }
            redirect('admin/counters.php');
        }
    } elseif (in_array($action, ['toggle', 'delete'], true) && $id) {
        $stmt = $pdo->prepare('SELECT name, is_active FROM counters WHERE id = ?');
        $stmt->execute([$id]);
        $counter = $stmt->fetch();
        if (!$counter) {
            set_flash('error', 'Counter not found.');
        } elseif (counter_is_busy($pdo, $id) && ($action === 'delete' || (int) $counter['is_active'] === 1)) {
            set_flash('error', 'Counter "' . $counter['name'] . '" is serving a customer right now. Try again after the ticket is completed.');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE counters SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            log_activity('counter_toggled', 'Changed active status of counter ' . $counter['name']);
            set_flash('success', 'Counter status updated.');
        } else {
            $pdo->prepare('DELETE FROM counters WHERE id = ?')->execute([$id]);
            log_activity('counter_deleted', 'Deleted counter ' . $counter['name']);
            set_flash('success', 'Counter "' . $counter['name'] . '" deleted. Its past tickets are kept.');
        }
        redirect('admin/counters.php');
    }
}

$edit = null;
if ($editId = int_param('edit', 'get')) {
    $stmt = $pdo->prepare('SELECT * FROM counters WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$form = is_post() ? $_POST : ($edit ?? ['is_active' => 1]);

$counters = $pdo->query(
    "SELECT c.*, s.name AS service_name, s.prefix,
            (SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
               FROM staff_counters sc JOIN users u ON u.id = sc.user_id WHERE sc.counter_id = c.id) AS staff_names,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.counter_id = c.id AND t.ticket_date = CURDATE() AND t.status = 'served') AS served_today
       FROM counters c JOIN services s ON s.id = c.service_id
      ORDER BY c.name"
)->fetchAll();

$pageTitle  = 'Service Counters';
$activeMenu = 'counters';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2><?= $edit ? 'Edit counter' : 'Add new counter' ?></h2>
        <p class="card-subtitle mb-2">A counter serves one service. Staff are assigned on the Counter Assignments page.</p>
        <?php if (!empty($errors['general'])): ?><div class="alert alert-error"><span><?= e($errors['general']) ?></span></div><?php endif; ?>
        <?php if (!$services): ?>
            <div class="alert alert-warning"><span>Create a service first. <a href="<?= url('admin/services.php') ?>">Go to services</a></span></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group">
                <label for="name">Counter name <span class="req">*</span></label>
                <input type="text" id="name" name="name" class="form-control<?= invalid($errors, 'name') ?>" value="<?= e($form['name'] ?? '') ?>" placeholder="Counter 6" maxlength="50" required>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="form-group">
                <label for="service_id">Service <span class="req">*</span></label>
                <select id="service_id" name="service_id" class="form-control<?= invalid($errors, 'service_id') ?>" required>
                    <option value="">-- Select service --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) ($form['service_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= e($s['prefix']) ?>)<?= (int) $s['is_active'] ? '' : ' - inactive' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'service_id') ?>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" class="form-control<?= invalid($errors, 'location') ?>" value="<?= e($form['location'] ?? '') ?>" placeholder="Ground Floor - Hall A" maxlength="100">
                <?= field_error($errors, 'location') ?>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>> Active (open for service)</label>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Add counter' ?></button>
                <?php if ($edit): ?><a href="<?= url('admin/counters.php') ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>All counters</h2><p class="card-subtitle"><?= count($counters) ?> counter(s)</p></div>
            <div class="search-box"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search..." data-table-search="#countersTable"></div>
        </div>
        <div class="table-responsive">
            <table class="table" id="countersTable">
                <thead><tr><th>Counter</th><th>Service</th><th>Location</th><th>Assigned staff</th><th>Served today</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($counters as $c): ?>
                    <tr>
                        <td><strong><?= e($c['name']) ?></strong></td>
                        <td><?= e($c['service_name']) ?> <small class="muted">(<?= e($c['prefix']) ?>)</small></td>
                        <td><?= e($c['location'] ?? '-') ?></td>
                        <td><?= $c['staff_names'] ? e($c['staff_names']) : '<a href="' . url('admin/assignments.php') . '">Assign staff</a>' ?></td>
                        <td><?= (int) $c['served_today'] ?></td>
                        <td><?= active_badge($c['is_active']) ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $c['id'] ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-outline btn-sm" name="action" value="toggle"><?= (int) $c['is_active'] ? 'Close' : 'Open' ?></button>
                                <button class="btn btn-ghost-danger btn-sm" name="action" value="delete" data-confirm="Delete <?= e($c['name']) ?>? Staff assignments to it will be removed." title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$counters): ?>
                    <tr><td colspan="7" class="table-empty">No counters yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
