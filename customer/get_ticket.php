<?php
/**
 * QueueEase - Customer: view services and generate a ticket
 * File: customer/get_ticket.php
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
    $serviceId = int_param('service_id', 'post');
    if (!$serviceId) {
        set_flash('error', 'Please choose a service.');
    } else {
        try {
            $ticket = create_ticket($pdo, $serviceId, (int) $customer['id']);
            set_flash('success', 'Your ticket ' . $ticket['ticket_number'] . ' has been generated.');
            redirect('customer/my_ticket.php?id=' . (int) $ticket['id']);
        } catch (RuntimeException $ex) {
            set_flash('error', $ex->getMessage());
        }
    }
    redirect('customer/get_ticket.php');
}

$services = get_services_overview($pdo);

// Services where this customer already has an active ticket today
$stmt = $pdo->prepare(
    "SELECT service_id, id, ticket_number FROM queue_tickets
      WHERE customer_id = ? AND ticket_date = CURDATE() AND status IN ('waiting','called','serving')"
);
$stmt->execute([$customer['id']]);
$activeByService = [];
foreach ($stmt->fetchAll() as $row) {
    $activeByService[(int) $row['service_id']] = $row;
}

$pageTitle  = 'Get a Ticket';
$activeMenu = 'services';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2>Available services</h2>
            <p class="card-subtitle">Select the service you need. Waiting numbers and times update when you reload.</p>
        </div>
        <div class="search-box" style="min-width:220px">
            <?= icon('search') ?>
            <input type="search" class="form-control" placeholder="Search services..." id="serviceSearch">
        </div>
    </div>

    <?php if (!$services): ?>
        <div class="empty-state"><p>No services are available at the moment.</p></div>
    <?php endif; ?>

    <div class="service-grid" id="serviceGrid">
        <?php foreach ($services as $service): $active = $activeByService[(int) $service['id']] ?? null; ?>
            <div class="service-card" data-name="<?= e(strtolower($service['name'] . ' ' . $service['description'])) ?>">
                <span class="service-prefix"><?= e($service['prefix']) ?></span>
                <h3><?= e($service['name']) ?></h3>
                <p><?= e($service['description']) ?></p>
                <div class="service-meta">
                    <span><strong><?= (int) $service['waiting_count'] ?></strong> waiting</span>
                    <span>~<strong><?= e(format_minutes($service['estimated_wait'])) ?></strong> wait</span>
                    <span><strong><?= (int) $service['open_counters'] ?></strong> counter(s)</span>
                </div>
                <?php if ($active): ?>
                    <a href="<?= url('customer/my_ticket.php?id=' . (int) $active['id']) ?>" class="btn btn-outline btn-block">You have <?= e($active['ticket_number']) ?> &mdash; view</a>
                <?php elseif ((int) $service['open_counters'] === 0): ?>
                    <button class="btn btn-outline btn-block" disabled>No counter open</button>
                <?php else: ?>
                    <form method="post" data-confirm="Generate a ticket for <?= e($service['name']) ?>?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-block"><?= icon('ticket') ?> Get ticket</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
document.getElementById('serviceSearch').addEventListener('input', function () {
    var term = this.value.toLowerCase();
    document.querySelectorAll('#serviceGrid .service-card').forEach(function (card) {
        card.style.display = card.getAttribute('data-name').indexOf(term) > -1 ? '' : 'none';
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
