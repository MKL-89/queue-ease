<?php
/**
 * QueueEase - Staff counter console
 * File: staff/dashboard.php
 */
require_once __DIR__ . '/../includes/init.php';
$user = require_role('staff');

$pdo      = db();
$counters = get_staff_counters($pdo, (int) $user['id']);

// Pick the counter: ?counter=ID, then the last one used, then the first assigned
$selectedId = int_param('counter', 'get') ?: (int) ($_SESSION['staff_counter_id'] ?? 0);
$selected   = null;
foreach ($counters as $c) {
    if ((int) $c['id'] === $selectedId) {
        $selected = $c;
    }
}
if (!$selected && $counters) {
    $selected = $counters[0];
}
if ($selected) {
    $_SESSION['staff_counter_id'] = (int) $selected['id'];
}

$pageTitle  = 'Counter Console';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';

$hero = [
    'eyebrow' => $selected ? $selected['name'] . ' · ' . $selected['service_name'] : 'Staff console',
    'text'    => $selected
        ? 'Call customers in order, start service when they arrive and complete it when done. Shortcuts: N, R, S, C, K.'
        : 'You are not assigned to a counter yet.',
    'actions' => [
        ['Waiting list', 'staff/waiting.php', 'list', 'light'],
        ['Display screen', 'public/display.php', 'tv', 'glass', true],
    ],
];
require __DIR__ . '/../includes/hero_banner.php';
?>
<?php if (!$counters): ?>
    <div class="card empty-state">
        <div class="empty-icon"><?= icon('monitor') ?></div>
        <h2>No counter assigned</h2>
        <p>You have not been assigned to an active service counter yet. Please ask the administrator to assign you.</p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2>My counters</h2>
                <p class="card-subtitle">Select the counter you are working at today.</p>
            </div>
            <a href="<?= url('public/display.php') ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><?= icon('tv') ?> Open display screen</a>
        </div>
        <div class="counter-picker">
            <?php foreach ($counters as $c): ?>
                <a class="counter-chip<?= (int) $c['id'] === (int) $selected['id'] ? ' active' : '' ?>" href="?counter=<?= (int) $c['id'] ?>">
                    <?= e($c['name']) ?> &middot; <?= e($c['service_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="console" data-counter="<?= (int) $selected['id'] ?>" data-endpoint="<?= url('queue/api/staff_action.php') ?>">
        <div class="stats-grid">
            <div class="stat-card"><span class="stat-icon tone-warning"><?= icon('hourglass') ?></span><div><div class="stat-value" data-s="waiting_count">-</div><div class="stat-label">Waiting for <?= e($selected['service_name']) ?></div></div></div>
            <div class="stat-card"><span class="stat-icon tone-success"><?= icon('check-circle') ?></span><div><div class="stat-value" data-s="served_today">-</div><div class="stat-label">Served by me today</div></div></div>
            <div class="stat-card"><span class="stat-icon tone-danger"><?= icon('skip') ?></span><div><div class="stat-value" data-s="skipped_today">-</div><div class="stat-label">Skipped today</div></div></div>
            <div class="stat-card"><span class="stat-icon tone-info"><?= icon('clock') ?></span><div><div class="stat-value" data-s="avg_service">-</div><div class="stat-label">My avg. service time</div></div></div>
        </div>

        <div class="grid grid-wide-left">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2><?= e($selected['name']) ?></h2>
                        <p class="card-subtitle"><?= e($selected['service_name']) ?><?= $selected['location'] ? ' &middot; ' . e($selected['location']) : '' ?></p>
                    </div>
                    <span class="small muted"><span class="live-dot"></span>Live</span>
                </div>
                <div id="consoleMessage"></div>
                <div class="console-current">
                    <div class="label" id="currentLabel">Current ticket</div>
                    <span class="ticket-no" id="currentNumber">----</span>
                    <div class="meta" id="currentMeta">No customer at this counter.</div>
                </div>
                <div class="console-actions">
                    <button class="btn btn-primary btn-xl btn-call" data-action="call"><?= icon('arrow-right') ?> Call Next <kbd>N</kbd></button>
                    <button class="btn btn-info btn-lg" data-action="recall"><?= icon('repeat') ?> Recall <kbd>R</kbd></button>
                    <button class="btn btn-warning btn-lg" data-action="skip" data-confirm="Skip this ticket? The customer did not respond."><?= icon('skip') ?> Skip <kbd>K</kbd></button>
                    <button class="btn btn-outline btn-lg" data-action="start"><?= icon('play') ?> Start Service <kbd>S</kbd></button>
                    <button class="btn btn-success btn-lg" data-action="complete"><?= icon('check') ?> Complete Service <kbd>C</kbd></button>
                </div>
                <p class="shortcut-hint mb-0"><span>Keyboard shortcuts:</span><span><kbd>N</kbd> call next</span><span><kbd>R</kbd> recall</span><span><kbd>S</kbd> start</span><span><kbd>C</kbd> complete</span><span><kbd>K</kbd> skip</span></p>
                <p class="small muted mt-1 mb-0">Order: <strong>Call Next</strong> &rarr; <strong>Start Service</strong> when the customer arrives &rarr; <strong>Complete Service</strong>. Use <strong>Recall</strong> to announce again or <strong>Skip</strong> if the customer does not come.</p>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Next in line</h3>
                    <a href="<?= url('staff/waiting.php') ?>" class="small">View all</a>
                </div>
                <ul class="waiting-list" id="waitingList"><li class="muted">Loading...</li></ul>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php
$extraScripts = '<script src="' . url('assets/js/staff.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
?>
