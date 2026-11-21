<?php
/**
 * QueueEase - Admin: manage announcements
 * File: admin/announcements.php
 */
require_once __DIR__ . '/../includes/init.php';
$user = require_role('admin');

$pdo    = db();
$errors = [];

if (is_post()) {
    require_csrf();
    $action = post('action');
    $id     = int_param('id', 'post');

    if ($action === 'save') {
        $title     = post('title');
        $message   = post('message');
        $startDate = post('start_date');
        $endDate   = post('end_date');
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (mb_strlen($title) < 3 || mb_strlen($title) > 150) {
            $errors['title'] = 'Title must be 3-150 characters.';
        }
        if (mb_strlen($message) < 5 || mb_strlen($message) > 1000) {
            $errors['message'] = 'Message must be 5-1000 characters.';
        }
        if ($startDate !== '' && !valid_date($startDate)) {
            $errors['start_date'] = 'Enter a valid date.';
        }
        if ($endDate !== '' && !valid_date($endDate)) {
            $errors['end_date'] = 'Enter a valid date.';
        }
        if (!$errors && $startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            $errors['end_date'] = 'End date cannot be before the start date.';
        }

        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE announcements SET title = ?, message = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?')
                    ->execute([$title, $message, $startDate ?: null, $endDate ?: null, $isActive, $id]);
                log_activity('announcement_updated', 'Updated announcement "' . $title . '"');
                set_flash('success', 'Announcement updated.');
            } else {
                $pdo->prepare('INSERT INTO announcements (title, message, start_date, end_date, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$title, $message, $startDate ?: null, $endDate ?: null, $isActive, $user['id']]);
                log_activity('announcement_created', 'Created announcement "' . $title . '"');
                set_flash('success', 'Announcement published.');
            }
            redirect('admin/announcements.php');
        }
    } elseif ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        log_activity('announcement_toggled', 'Changed status of announcement #' . $id);
        set_flash('success', 'Announcement status updated.');
        redirect('admin/announcements.php');
    } elseif ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        log_activity('announcement_deleted', 'Deleted announcement #' . $id);
        set_flash('success', 'Announcement deleted.');
        redirect('admin/announcements.php');
    }
}

$edit = null;
if ($editId = int_param('edit', 'get')) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
}
$form = is_post() ? $_POST : ($edit ?? ['is_active' => 1]);

$announcements = $pdo->query(
    'SELECT a.*, u.full_name AS author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC'
)->fetchAll();
$today = date('Y-m-d');

$pageTitle  = 'Announcements';
$activeMenu = 'announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-form">
    <div class="card">
        <h2><?= $edit ? 'Edit announcement' : 'New announcement' ?></h2>
        <p class="card-subtitle mb-2">Active announcements appear on the live display, the home page and customer dashboards.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group">
                <label for="title">Title <span class="req">*</span></label>
                <input type="text" id="title" name="title" class="form-control<?= invalid($errors, 'title') ?>" value="<?= e($form['title'] ?? '') ?>" maxlength="150" required>
                <?= field_error($errors, 'title') ?>
            </div>
            <div class="form-group">
                <label for="message">Message <span class="req">*</span></label>
                <textarea id="message" name="message" class="form-control<?= invalid($errors, 'message') ?>" maxlength="1000" required><?= e($form['message'] ?? '') ?></textarea>
                <?= field_error($errors, 'message') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Show from</label>
                    <input type="date" id="start_date" name="start_date" class="form-control<?= invalid($errors, 'start_date') ?>" value="<?= e($form['start_date'] ?? '') ?>">
                    <?= field_error($errors, 'start_date') ?>
                </div>
                <div class="form-group">
                    <label for="end_date">Show until</label>
                    <input type="date" id="end_date" name="end_date" class="form-control<?= invalid($errors, 'end_date') ?>" value="<?= e($form['end_date'] ?? '') ?>">
                    <?= field_error($errors, 'end_date') ?>
                </div>
            </div>
            <p class="form-hint mt-0">Leave dates empty to show the announcement until you deactivate it.</p>
            <div class="form-group">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($form['is_active']) ? 'checked' : '' ?>> Active</label>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><?= icon('megaphone') ?> <?= $edit ? 'Save changes' : 'Publish' ?></button>
                <?php if ($edit): ?><a href="<?= url('admin/announcements.php') ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div><h2>All announcements</h2><p class="card-subtitle"><?= count($announcements) ?> announcement(s)</p></div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Announcement</th><th>Period</th><th>By</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($announcements as $a):
                    $live = (int) $a['is_active'] === 1
                        && (!$a['start_date'] || $a['start_date'] <= $today)
                        && (!$a['end_date'] || $a['end_date'] >= $today); ?>
                    <tr>
                        <td><strong><?= e($a['title']) ?></strong><br><small class="muted"><?= e(mb_strimwidth($a['message'], 0, 110, '...')) ?></small></td>
                        <td class="small nowrap"><?= $a['start_date'] ? e(format_datetime($a['start_date'], 'd M Y')) : 'Any time' ?><br><?= $a['end_date'] ? 'to ' . e(format_datetime($a['end_date'], 'd M Y')) : '' ?></td>
                        <td class="small"><?= e($a['author'] ?? '-') ?></td>
                        <td><?= $live ? '<span class="badge badge-active">Showing</span>' : '<span class="badge badge-inactive">Hidden</span>' ?></td>
                        <td class="actions">
                            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $a['id'] ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="btn btn-outline btn-sm" name="action" value="toggle"><?= (int) $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                <button class="btn btn-ghost-danger btn-sm" name="action" value="delete" data-confirm="Delete this announcement?" title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$announcements): ?>
                    <tr><td colspan="5" class="table-empty">No announcements yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
