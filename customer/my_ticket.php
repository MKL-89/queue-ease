<?php
/**
 * QueueEase - Customer: view one ticket with live tracking, and cancel it
 * File: customer/my_ticket.php
 */
require_once __DIR__ . '/../includes/init.php';
require_role('customer');

$pdo      = db();
$customer = current_customer();
if (!$customer) {
    exit('Customer profile not found. Please contact the administrator.');
}

if (is_post()) {
    require_csrf();
    $ticketId = int_param('ticket_id', 'post');
    try {
        $ticket = cancel_ticket($pdo, $ticketId, (int) $customer['id'], 'Cancelled by customer');
        set_flash('success', 'Ticket ' . $ticket['ticket_number'] . ' has been cancelled.');
    } catch (RuntimeException $ex) {
        set_flash('error', $ex->getMessage());
    }
    redirect('customer/my_ticket.php?id=' . $ticketId);
}

$ticket = get_ticket($pdo, int_param('id', 'get'));

// Customers may only see their own tickets
if (!$ticket || (int) $ticket['customer_id'] !== (int) $customer['id']) {
    set_flash('error', 'Ticket not found.');
    redirect('customer/history.php');
}

$tracking     = get_ticket_tracking($pdo, $ticket);
$showCancel   = true;
$cancelAction = url('customer/my_ticket.php');

$pageTitle  = 'Ticket ' . $ticket['ticket_number'];
$pageSubtitle = 'Live status of your ticket. This page updates automatically - keep it open.';
$activeMenu = 'history';
require __DIR__ . '/../includes/header.php';
?>
<div class="no-print mb-2">
    <a href="<?= url('customer/dashboard.php') ?>" class="small">&larr; Back to dashboard</a>
</div>
<?php require __DIR__ . '/../includes/ticket_view.php'; ?>
<?php
$extraScripts = '<script src="' . url('assets/js/vendor/qrcode.js') . '"></script>'
              . '<script src="' . url('assets/js/ticket.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
?>
