<?php
/**
 * QueueEase - Public home page
 * File: index.php
 */
require_once __DIR__ . '/includes/init.php';

$pdo           = db();
$services      = get_services_overview($pdo);
$announcements = get_active_announcements($pdo, 3);
$nowServing    = $pdo->query(
    "SELECT t.ticket_number, t.status, c.name AS counter_name
       FROM queue_tickets t JOIN counters c ON c.id = t.counter_id
      WHERE t.ticket_date = CURDATE() AND t.status IN ('called','serving')
      ORDER BY t.called_at DESC LIMIT 5"
)->fetchAll();
$waitingTotal = array_sum(array_column($services, 'waiting_count'));

$pageTitle = 'Welcome';
require __DIR__ . '/includes/public_header.php';
?>
<section class="hero">
    <div>
        <span class="badge badge-role">Welcome to <?= e(INSTITUTION_NAME) ?></span>
        <h1 class="mt-2">Skip the line. <span>Join the queue digitally.</span></h1>
        <p>Get a digital ticket in seconds, see your position in the queue in real time and arrive at the counter exactly when it is your turn.</p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= url('queue/guest_ticket.php') ?>"><?= icon('ticket') ?> Get a Ticket</a>
            <a class="btn btn-outline btn-lg" href="<?= url('track.php') ?>"><?= icon('search') ?> Track My Ticket</a>
        </div>
        <p class="small muted mt-2">Have an account? <a href="<?= url('login.php') ?>">Log in</a> to see your ticket history.</p>
    </div>
    <div class="hero-panel">
        <h3><span class="live-dot"></span> Now serving</h3>
        <?php if ($nowServing): ?>
            <ul class="now-serving-list">
                <?php foreach ($nowServing as $row): ?>
                    <li><span class="ticket-no"><?= e($row['ticket_number']) ?></span><span><?= e($row['counter_name']) ?></span><?= status_badge($row['status']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">No customer is being served at the moment.</p>
        <?php endif; ?>
        <p class="small muted mt-2 mb-0"><?= (int) $waitingTotal ?> customer(s) waiting right now &middot; <a href="<?= url('public/display.php') ?>" target="_blank" rel="noopener">Open live display</a></p>
    </div>
</section>

<div class="section-title">
    <div>
        <h2>Our services</h2>
        <p class="muted mb-0">Choose a service to get your ticket.</p>
    </div>
</div>
<div class="service-grid">
    <?php foreach ($services as $service): ?>
        <div class="service-card">
            <span class="service-prefix"><?= e($service['prefix']) ?></span>
            <h3><?= e($service['name']) ?></h3>
            <p><?= e($service['description']) ?></p>
            <div class="service-meta">
                <span><strong><?= (int) $service['waiting_count'] ?></strong> waiting</span>
                <span>~<strong><?= e(format_minutes($service['estimated_wait'])) ?></strong> wait</span>
            </div>
            <a class="btn btn-outline btn-sm" href="<?= url('queue/guest_ticket.php?service=' . (int) $service['id']) ?>">Get ticket <?= icon('arrow-right') ?></a>
        </div>
    <?php endforeach; ?>
    <?php if (!$services): ?>
        <p class="muted">No services are available at the moment.</p>
    <?php endif; ?>
</div>

<div class="section-title"><h2>How it works</h2></div>
<div class="feature-grid">
    <div class="feature"><span class="stat-icon tone-primary"><?= icon('layers') ?></span><h3>1. Choose a service</h3><p>Pick the service you need from the list above.</p></div>
    <div class="feature"><span class="stat-icon tone-info"><?= icon('ticket') ?></span><h3>2. Get your ticket</h3><p>Receive a ticket number such as MED004 and a tracking code.</p></div>
    <div class="feature"><span class="stat-icon tone-warning"><?= icon('clock') ?></span><h3>3. Track your position</h3><p>See how many people are ahead of you and your estimated waiting time.</p></div>
    <div class="feature"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><h3>4. Get served</h3><p>Go to the counter shown on screen when your number is called.</p></div>
</div>

<?php if ($announcements): ?>
    <div class="section-title"><h2>Announcements</h2></div>
    <div class="card">
        <?php foreach ($announcements as $a): ?>
            <div class="announcement">
                <span class="stat-icon tone-purple"><?= icon('megaphone') ?></span>
                <div><h4><?= e($a['title']) ?></h4><p><?= nl2br(e($a['message'])) ?></p></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
