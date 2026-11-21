<?php
/**
 * QueueEase - Admin: register and manage staff accounts
 * File: admin/staff.php
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
        $fullName = post('full_name');
        $email    = strtolower(post('email'));
        $phone    = preg_replace('/\s+/', '', post('phone'));
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!valid_name($fullName)) {
            $errors['full_name'] = 'Enter a valid full name (2-100 letters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($phone !== '' && !valid_phone($phone)) {
            $errors['phone'] = 'Enter a valid phone number (9-15 digits).';
        }
        // Password is required for new staff, optional when editing
        if (!$id || $password !== '') {
            if (!valid_password($password)) {
                $errors['password'] = password_rule_text();
            }
        }
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $id]);
            if ($stmt->fetchColumn()) {
                $errors['email'] = 'This email is already used by another account.';
            }
        }

        if (!$errors) {
            if ($id) {
                $sql    = 'UPDATE users SET full_name = ?, email = ?, phone = ?, is_active = ?';
                $params = [$fullName, $email, $phone ?: null, $isActive];
                if ($password !== '') {
                    $sql     .= ', password_hash = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql     .= " WHERE id = ? AND role = 'staff'";
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                log_activity('staff_updated', 'Updated staff account ' . $fullName . ($password !== '' ? ' (password reset)' : ''));
                set_flash('success', 'Staff member "' . $fullName . '" updated.');
            } else {
                $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, is_active) VALUES (?, ?, ?, ?, 'staff', ?)")
                    ->execute([$fullName, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $isActive]);
                log_activity('staff_created', 'Registered staff member ' . $fullName);
                set_flash('success', 'Staff member "' . $fullName . '" registered. Assign them to a counter next.');
            }
            redirect('admin/staff.php');
        }
    } elseif (in_array($action, ['toggle', 'delete'], true) && $id) {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'staff'");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT ticket_number FROM queue_tickets WHERE served_by = ? AND ticket_date = CURDATE() AND status IN ('called','serving') LIMIT 1");
        $stmt->execute([$id]);
        $busyWith = $stmt->fetchColumn();

        if (!$name) {
            set_flash('error', 'Staff member not found.');
        } elseif ($busyWith && $action === 'delete') {
            set_flash('error', $name . ' is currently handling ticket ' . $busyWith . '. Try again after it is completed.');
        } elseif ($action === 'toggle') {
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            log_activity('staff_toggled', 'Changed active status of staff ' . $name);
            set_flash('success', 'Account status for ' . $name . ' updated.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'")->execute([$id]);
            log_activity('staff_deleted', 'Deleted staff account ' . $name);
            set_flash('success', 'Staff account "' . $name . '" deleted. Their service history is kept.');
        }
        redirect('admin/staff.php');
    }
}

$edit = null;
if ($editId = int_param('edit', 'get')) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$form = is_post() ? $_POST : ($edit ?? ['is_active' => 1]);

$staff = $pdo->query(
    "SELECT u.*,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') FROM staff_counters sc JOIN counters c ON c.id = sc.counter_id WHERE sc.user_id = u.id) AS counters,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.served_by = u.id AND t.status = 'served' AND t.ticket_date = CURDATE()) AS served_today,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.served_by = u.id AND t.status = 'served') AS served_total
       FROM users u WHERE u.role = 'staff' ORDER BY u.full_name"
)->fetchAll();

$pageTitle  = 'Staff';
$activeMenu = 'staff';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2><?= $edit ? 'Edit staff member' : 'Register staff member' ?></h2>
        <p class="card-subtitle mb-2">Staff log in with their email and password to operate a counter.</p>
        <form method="post" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group">
                <label for="full_name">Full name <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control<?= invalid($errors, 'full_name') ?>" value="<?= e($form['full_name'] ?? '') ?>" maxlength="100" required>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="form-group">
                <label for="email">Email address <span class="req">*</span></label>
                <input type="email" id="email" name="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= e($form['email'] ?? '') ?>" maxlength="150" required>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="form-group">
                <label for="phone">Phone number</label>
                <input type="tel" id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= e($form['phone'] ?? '') ?>" maxlength="16">
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="form-group">
                <label for="password">Password <?= $edit ? '' : '<span class="req">*</span>' ?></label>
                <input type="password" id="password" name="password" class="form-control<?= invalid($errors, 'password') ?>" autocomplete="new-password" <?= $edit ? '' : 'required' ?>>
                <?= field_error($errors, 'password') ?: '<div class="form-hint">' . ($edit ? 'Leave blank to keep the current password. ' : '') . e(password_rule_text()) . '</div>' ?>
            </div>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>> Account active</label>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Register staff' ?></button>
                <?php if ($edit): ?><a href="<?= url('admin/staff.php') ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>Staff members</h2><p class="card-subtitle"><?= count($staff) ?> staff account(s)</p></div>
            <div class="search-box"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search..." data-table-search="#staffTable"></div>
        </div>
        <div class="table-responsive">
            <table class="table" id="staffTable">
                <thead><tr><th>Name</th><th>Contact</th><th>Counters</th><th>Served today</th><th>Total served</th><th>Last login</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($staff as $s): ?>
                    <tr>
                        <td><strong><?= e($s['full_name']) ?></strong></td>
                        <td><?= e($s['email']) ?><br><small class="muted"><?= e($s['phone'] ?? '') ?></small></td>
                        <td><?= $s['counters'] ? e($s['counters']) : '<a href="' . url('admin/assignments.php') . '">Assign</a>' ?></td>
                        <td><?= (int) $s['served_today'] ?></td>
                        <td><?= (int) $s['served_total'] ?></td>
                        <td class="nowrap small"><?= e(format_datetime($s['last_login_at'])) ?></td>
                        <td><?= active_badge($s['is_active']) ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $s['id'] ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button class="btn btn-outline btn-sm" name="action" value="toggle"><?= (int) $s['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                <button class="btn btn-ghost-danger btn-sm" name="action" value="delete" data-confirm="Delete the account of <?= e($s['full_name']) ?>? This cannot be undone." title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$staff): ?>
                    <tr><td colspan="8" class="table-empty">No staff registered yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
