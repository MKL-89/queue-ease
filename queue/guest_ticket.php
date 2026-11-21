<?php
/**
 * QueueEase - Get a ticket without registering (guest / walk-in)
 * File: queue/guest_ticket.php
 */
require_once __DIR__ . '/../includes/init.php';

$pdo = db();

// Logged-in customers use their own page (keeps their ticket history)
if (user_role() === 'customer') {
    redirect('customer/get_ticket.php');
}

$errors   = [];
$services = get_services_overview($pdo);
$selected = int_param('service_id', 'post') ?: int_param('service', 'get');

if (is_post()) {
    require_csrf();
    $fullName = post('full_name');
    $phone    = preg_replace('/\s+/', '', post('phone'));

    if (!valid_name($fullName)) {
        $errors['full_name'] = 'Enter your name (letters only, 2-100 characters).';
    }
    if (!valid_phone($phone)) {
        $errors['phone'] = 'Enter a valid phone number (9-15 digits).';
    }
    if (!$selected) {
        $errors['service_id'] = 'Please choose a service.';
    }

    if (!$errors) {
        try {
            // Re-use the guest profile for the same phone number so the
            // duplicate-ticket rule also applies to guests.
            $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = ? AND is_guest = 1 ORDER BY id LIMIT 1');
            $stmt->execute([$phone]);
            $customerId = (int) $stmt->fetchColumn();

            if ($customerId) {
                $pdo->prepare('UPDATE customers SET full_name = ? WHERE id = ?')->execute([$fullName, $customerId]);
            } else {
                $pdo->prepare('INSERT INTO customers (full_name, phone, is_guest) VALUES (?, ?, 1)')->execute([$fullName, $phone]);
                $customerId = (int) $pdo->lastInsertId();
            }

            $ticket = create_ticket($pdo, $selected, $customerId);
            $_SESSION['guest_tickets'][] = $ticket['reference_code'];
            set_flash('success', 'Your ticket ' . $ticket['ticket_number'] . ' has been created. Save your tracking code: ' . $ticket['reference_code']);
            redirect('track.php?ref=' . urlencode($ticket['reference_code']));
        } catch (RuntimeException $ex) {
            $errors['general'] = $ex->getMessage();
        }
    }
}

$pageTitle = 'Get a Ticket';
require __DIR__ . '/../includes/public_header.php';
?>
<div class="section-title mt-0">
    <div>
        <h2>Get a queue ticket</h2>
        <p class="muted mb-0">No account needed. Choose a service, enter your name and phone number, and you will get a ticket with a tracking code.</p>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-error"><span><?= e($errors['general']) ?></span></div>
<?php elseif ($errors): ?>
    <div class="alert alert-error"><span>Please correct the highlighted fields.</span></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="card">
        <h3 class="mb-2">1. Choose a service</h3>
        <?php if (!$services): ?>
            <p class="muted">No services are available right now.</p>
        <?php endif; ?>
        <div class="service-grid">
            <?php foreach ($services as $service): ?>
                <label class="service-option">
                    <input type="radio" name="service_id" value="<?= (int) $service['id'] ?>" <?= $selected === (int) $service['id'] ? 'checked' : '' ?>>
                    <div class="service-card">
                        <span class="service-prefix"><?= e($service['prefix']) ?></span>
                        <h3><?= e($service['name']) ?></h3>
                        <p><?= e($service['description']) ?></p>
                        <div class="service-meta">
                            <span><strong><?= (int) $service['waiting_count'] ?></strong> waiting</span>
                            <span>~<strong><?= e(format_minutes($service['estimated_wait'])) ?></strong></span>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'service_id') ?>
    </div>

    <div class="card">
        <h3 class="mb-2">2. Your details</h3>
        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Full name <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name" class="form-control<?= invalid($errors, 'full_name') ?>" value="<?= old('full_name') ?>" maxlength="100" required>
                <?= field_error($errors, 'full_name') ?>
            </div>
            <div class="form-group">
                <label for="phone">Phone number <span class="req">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= old('phone') ?>" maxlength="16" placeholder="0712345678" required>
                <?= field_error($errors, 'phone') ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('ticket') ?> Get my ticket</button>
        <p class="small muted mt-2 mb-0">Want to keep a history of your tickets? <a href="<?= url('register.php') ?>">Create a free account</a>.</p>
    </div>
</form>
<?php require __DIR__ . '/../includes/public_footer.php'; ?>
