<?php
/**
 * QueueEase - 403 Access denied page (shown by require_role())
 * File: includes/access_denied.php
 */
$pageTitle = 'Access Denied';
require __DIR__ . '/public_header.php';
?>
<section class="auth-wrap">
    <div class="card auth-card text-center">
        <div class="empty-icon danger"><?= icon('lock') ?></div>
        <h2>Access denied</h2>
        <p class="muted">You do not have permission to open this page.</p>
        <a class="btn btn-primary" href="<?= url(dashboard_for_role(user_role() ?? 'customer')) ?>">Go to my dashboard</a>
    </div>
</section>
<?php require __DIR__ . '/public_footer.php'; ?>
