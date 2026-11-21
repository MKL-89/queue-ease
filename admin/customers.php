<?php
/**
 * QueueEase - Admin: register and manage customers (registered and guest)
 * File: admin/customers.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo    = db();
$errors = [];

/** Load a customer with its login account (if registered). */
function load_customer(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT cu.*, u.is_active, u.email AS login_email FROM customers cu LEFT JOIN users u ON u.id = cu.user_id WHERE cu.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

if (is_post()) {
    require_csrf();
    $action = post('action');
    $id     = int_param('id', 'post');

    if ($action === 'save') {
        $existing = $id ? load_customer($pdo, $id) : null;
        $isGuest  = $existing && (int) $existing['is_guest'] === 1;
        $fullName = post('full_name');
        $email    = strtolower(post('email'));
        $phone    = preg_replace('/\s+/', '', post('phone'));
        $password = $_POST['password'] ?? '';

        if ($id && !$existing) {
            $errors['general'] = 'Customer not found.';
        }
        if (!valid_name($fullName)) {
            $errors['full_name'] = 'Enter a valid full name (2-100 letters).';
        }
        if (!valid_phone($phone)) {
            $errors['phone'] = 'Enter a valid phone number (9-15 digits).';
        }
        if (!$isGuest || $email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
                $errors['email'] = 'Enter a valid email address.';
            }
        }
        if (!$id || ($password !== '' && !$isGuest)) {
            if (!valid_password($password)) {
                $errors['password'] = password_rule_text();
            }
        }
        if (!$errors && !$isGuest) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, (int) ($existing['user_id'] ?? 0)]);
            if ($stmt->fetchColumn()) {
                $errors['email'] = 'This email is already used by another account.';
            }
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if (!$id) {
                    $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')")
                        ->execute([$fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
                    $userId = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO customers (user_id, full_name, phone, email, is_guest) VALUES (?, ?, ?, ?, 0)')
                        ->execute([$userId, $fullName, $phone, $email]);
                    $logText = 'Registered customer ' . $fullName;
                } else {
                    $pdo->prepare('UPDATE customers SET full_name = ?, phone = ?, email = ? WHERE id = ?')
                        ->execute([$fullName, $phone, $email ?: null, $id]);
                    if (!$isGuest) {
                        $sql    = 'UPDATE users SET full_name = ?, email = ?, phone = ?';
                        $params = [$fullName, $email, $phone];
                        if ($password !== '') {
                            $sql     .= ', password_hash = ?';
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                        }
                        $params[] = $existing['user_id'];
                        $pdo->prepare($sql . ' WHERE id = ?')->execute($params);
                    }
                    $logText = 'Updated customer ' . $fullName;
                }
                $pdo->commit();
                log_activity($id ? 'customer_updated' : 'customer_created', $logText);
                set_flash('success', $logText . '.');
                redirect('admin/customers.php');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Customer save failed: ' . $ex->getMessage());
                $errors['general'] = 'The customer could not be saved. Please try again.';
            }
        }
    } elseif (in_array($action, ['toggle', 'delete'], true) && $id) {
        $customer = load_customer($pdo, $id);
        if (!$customer) {
            set_flash('error', 'Customer not found.');
        } elseif ($action === 'toggle') {
            if ($customer['user_id']) {
                $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$customer['user_id']]);
                log_activity('customer_toggled', 'Changed account status of customer ' . $customer['full_name']);
                set_flash('success', 'Account status for ' . $customer['full_name'] . ' updated.');
            } else {
                set_flash('warning', 'Guest customers do not have a login account.');
            }
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_tickets WHERE customer_id = ? AND ticket_date = CURDATE() AND status IN ('waiting','called','serving')");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                set_flash('error', $customer['full_name'] . ' has an active ticket today. Cancel or complete it first.');
            } else {
                if ($customer['user_id']) {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$customer['user_id']]); // profile removed by ON DELETE CASCADE
                } else {
                    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
                }
                log_activity('customer_deleted', 'Deleted customer ' . $customer['full_name']);
                set_flash('success', 'Customer "' . $customer['full_name'] . '" deleted. Their past tickets remain in reports.');
            }
        }
        redirect('admin/customers.php');
    }
}

$edit = null;
if ($editId = int_param('edit', 'get')) {
    $edit = load_customer($pdo, $editId);
}
$form = is_post() ? $_POST : ($edit ?? []);

// ---- Filters and list ---------------------------------------------------
$q     = get('q');
$type  = get('type');
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[]  = '(cu.full_name LIKE ? OR cu.phone LIKE ? OR cu.email LIKE ?)';
    $like     = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($type === 'registered') {
    $where[] = 'cu.is_guest = 0';
} elseif ($type === 'guest') {
    $where[] = 'cu.is_guest = 1';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM customers cu WHERE $whereSql");
$stmt->execute($params);
$pager = paginate((int) $stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT cu.*, u.is_active, u.last_login_at,
            (SELECT COUNT(*) FROM queue_tickets t WHERE t.customer_id = cu.id) AS ticket_count,
            (SELECT MAX(t.created_at) FROM queue_tickets t WHERE t.customer_id = cu.id) AS last_visit
       FROM customers cu LEFT JOIN users u ON u.id = cu.user_id
      WHERE $whereSql
      ORDER BY cu.created_at DESC
      LIMIT {$pager['limit']} OFFSET {$pager['offset']}"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$editingGuest = $edit && (int) $edit['is_guest'] === 1;

$pageTitle  = 'Customers';
$activeMenu = 'customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2><?= $edit ? 'Edit customer' : 'Register customer' ?></h2>
        <p class="card-subtitle mb-2"><?= $editingGuest ? 'This is a guest (walk-in) customer without a login account.' : 'Creates a customer login account.' ?></p>
        <?php if (!empty($errors['general'])): ?><div class="alert alert-error"><span><?= e($errors['general']) ?></span></div><?php endif; ?>
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
                <label for="phone">Phone number <span class="req">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= e($form['phone'] ?? '') ?>" maxlength="16" required>
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="form-group">
                <label for="email">Email address <?= $editingGuest ? '' : '<span class="req">*</span>' ?></label>
                <input type="email" id="email" name="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= e($form['email'] ?? '') ?>" maxlength="150">
                <?= field_error($errors, 'email') ?>
            </div>
            <?php if (!$editingGuest): ?>
                <div class="form-group">
                    <label for="password">Password <?= $edit ? '' : '<span class="req">*</span>' ?></label>
                    <input type="password" id="password" name="password" class="form-control<?= invalid($errors, 'password') ?>" autocomplete="new-password">
                    <?= field_error($errors, 'password') ?: '<div class="form-hint">' . ($edit ? 'Leave blank to keep the current password. ' : '') . e(password_rule_text()) . '</div>' ?>
                </div>
            <?php endif; ?>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> <?= $edit ? 'Save changes' : 'Register customer' ?></button>
                <?php if ($edit): ?><a href="<?= url('admin/customers.php') ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>Customers</h2><p class="card-subtitle"><?= (int) $pager['total'] ?> customer(s) found</p></div>
        </div>
        <form class="filter-bar" method="get">
            <div class="form-group grow"><label for="q">Search</label><input type="search" id="q" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Name, phone or email"></div>
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control">
                    <option value="">All</option>
                    <option value="registered" <?= $type === 'registered' ? 'selected' : '' ?>>Registered</option>
                    <option value="guest" <?= $type === 'guest' ? 'selected' : '' ?>>Guest</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><?= icon('search') ?> Search</button>
        </form>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Customer</th><th>Contact</th><th>Type</th><th>Tickets</th><th>Last visit</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><strong><?= e($c['full_name']) ?></strong><br><small class="muted">Since <?= e(format_datetime($c['created_at'], 'd M Y')) ?></small></td>
                        <td><?= e($c['phone']) ?><br><small class="muted"><?= e($c['email'] ?? '') ?></small></td>
                        <td><?= (int) $c['is_guest'] ? '<span class="badge badge-guest">Guest</span>' : '<span class="badge badge-role">Registered</span>' ?></td>
                        <td><?= (int) $c['ticket_count'] ?></td>
                        <td class="small nowrap"><?= e(format_datetime($c['last_visit'])) ?></td>
                        <td><?= (int) $c['is_guest'] ? '<span class="muted">-</span>' : active_badge($c['is_active']) ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $c['id'] ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <?php if (!(int) $c['is_guest']): ?>
                                    <button class="btn btn-outline btn-sm" name="action" value="toggle"><?= (int) $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                <?php endif; ?>
                                <button class="btn btn-ghost-danger btn-sm" name="action" value="delete" data-confirm="Delete customer <?= e($c['full_name']) ?>? This cannot be undone." title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$customers): ?>
                    <tr><td colspan="7" class="table-empty">No customers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
