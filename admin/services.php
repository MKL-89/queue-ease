<?php
/**
 * QueueEase - Admin: manage service categories
 * File: admin/services.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo    = db();
$errors = [];

if (is_post()) {
    require_csrf();
    $action = post('action');
    $id     = int_param('id', 'post');

    if ($action === 'save') {
        $name        = post('name');
        $prefix      = strtoupper(post('prefix'));
        $description = post('description');
        $avgMinutes  = filter_var(post('avg_service_minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 240]]);
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Service name must be 2-100 characters.';
        }
        if (!preg_match('/^[A-Z]{2,5}$/', $prefix)) {
            $errors['prefix'] = 'Prefix must be 2-5 capital letters, e.g. MED.';
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description must not exceed 255 characters.';
        }
        if ($avgMinutes === false) {
            $errors['avg_service_minutes'] = 'Enter minutes between 1 and 240.';
        }
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT name, prefix FROM services WHERE (name = ? OR prefix = ?) AND id <> ?');
            $stmt->execute([$name, $prefix, $id]);
            foreach ($stmt->fetchAll() as $dup) {
                if (strcasecmp($dup['name'], $name) === 0) {
                    $errors['name'] = 'A service with this name already exists.';
                }
                if ($dup['prefix'] === $prefix) {
                    $errors['prefix'] = 'This prefix is already used by another service.';
                }
            }
        }

        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE services SET name = ?, prefix = ?, description = ?, avg_service_minutes = ?, is_active = ? WHERE id = ?')
                    ->execute([$name, $prefix, $description ?: null, $avgMinutes, $isActive, $id]);
                log_activity('service_updated', 'Updated service ' . $name);
                set_flash('success', 'Service "' . $name . '" updated.');
            } else {
                $pdo->prepare('INSERT INTO services (name, prefix, description, avg_service_minutes, is_active) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$name, $prefix, $description ?: null, $avgMinutes, $isActive]);
                log_activity('service_created', 'Created service ' . $name . ' (' . $prefix . ')');
                set_flash('success', 'Service "' . $name . '" created.');
            }
            redirect('admin/services.php');
        }
    } elseif ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE services SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        log_activity('service_toggled', 'Changed active status of service #' . $id);
        set_flash('success', 'Service status updated.');
        redirect('admin/services.php');
    } elseif ($action === 'delete' && $id) {
        $stmt = $pdo->prepare(
            'SELECT s.name, (SELECT COUNT(*) FROM queue_tickets WHERE service_id = s.id) AS tickets,
                    (SELECT COUNT(*) FROM counters WHERE service_id = s.id) AS counters
               FROM services s WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        if (!$service) {
            set_flash('error', 'Service not found.');
        } elseif ($service['tickets'] > 0 || $service['counters'] > 0) {
            set_flash('error', 'Cannot delete "' . $service['name'] . '" because it has ' . $service['tickets'] . ' ticket(s) and ' . $service['counters'] . ' counter(s). Deactivate it instead.');
        } else {
            $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
            log_activity('service_deleted', 'Deleted service ' . $service['name']);
            set_flash('success', 'Service "' . $service['name'] . '" deleted.');
        }
        redirect('admin/services.php');
    }
}

// Editing an existing service?
$edit = null;
if ($editId = int_param('edit', 'get')) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$form = is_post() ? $_POST : ($edit ?? ['is_active' => 1, 'avg_service_minutes' => 5]);

$services = $pdo->query(
    "SELECT s.*,
            (SELECT COUNT(*) FROM counters c WHERE c.service_id = s.id) AS counter_count,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.service_id = s.id AND t.ticket_date = CURDATE()) AS today_count,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.service_id = s.id AND t.ticket_date = CURDATE() AND t.status = 'waiting') AS waiting_count
       FROM services s ORDER BY s.name"
)->fetchAll();

$pageTitle  = 'Services';
$activeMenu = 'services';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2><?= $edit ? 'Edit service' : 'Add new service' ?></h2>
        <p class="card-subtitle mb-2">Each service has its own ticket prefix and daily ticket sequence.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group">
                <label for="name">Service name <span class="req">*</span></label>
                <input type="text" id="name" name="name" class="form-control<?= invalid($errors, 'name') ?>" value="<?= e($form['name'] ?? '') ?>" maxlength="100" required>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="prefix">Ticket prefix <span class="req">*</span></label>
                    <input type="text" id="prefix" name="prefix" class="form-control mono<?= invalid($errors, 'prefix') ?>" value="<?= e($form['prefix'] ?? '') ?>" maxlength="5" placeholder="MED" style="text-transform:uppercase" required>
                    <?= field_error($errors, 'prefix') ?: '<div class="form-hint">Tickets look like PREFIX001</div>' ?>
                </div>
                <div class="form-group">
                    <label for="avg_service_minutes">Avg. minutes <span class="req">*</span></label>
                    <input type="number" id="avg_service_minutes" name="avg_service_minutes" class="form-control<?= invalid($errors, 'avg_service_minutes') ?>" value="<?= e($form['avg_service_minutes'] ?? 5) ?>" min="1" max="240" required>
                    <?= field_error($errors, 'avg_service_minutes') ?: '<div class="form-hint">Used for wait estimates</div>' ?>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control<?= invalid($errors, 'description') ?>" maxlength="255"><?= e($form['description'] ?? '') ?></textarea>
                <?= field_error($errors, 'description') ?>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>> Active (customers can take tickets)</label>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Add service' ?></button>
                <?php if ($edit): ?><a href="<?= url('admin/services.php') ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>All services</h2><p class="card-subtitle"><?= count($services) ?> service(s)</p></div>
            <div class="search-box"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search..." data-table-search="#servicesTable"></div>
        </div>
        <div class="table-responsive">
            <table class="table" id="servicesTable">
                <thead><tr><th>Prefix</th><th>Service</th><th>Avg. time</th><th>Counters</th><th>Today</th><th>Waiting</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><span class="ticket-no"><?= e($s['prefix']) ?></span></td>
                        <td><strong><?= e($s['name']) ?></strong><br><small class="muted"><?= e($s['description']) ?></small></td>
                        <td><?= (int) $s['avg_service_minutes'] ?> min</td>
                        <td><?= (int) $s['counter_count'] ?></td>
                        <td><?= (int) $s['today_count'] ?></td>
                        <td><?= (int) $s['waiting_count'] ?></td>
                        <td><?= active_badge($s['is_active']) ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $s['id'] ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button class="btn btn-outline btn-sm" name="action" value="toggle"><?= (int) $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                <button class="btn btn-ghost-danger btn-sm" name="action" value="delete" data-confirm="Delete service <?= e($s['name']) ?>? This cannot be undone." title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$services): ?>
                    <tr><td colspan="8" class="table-empty">No services yet. Add your first service.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
