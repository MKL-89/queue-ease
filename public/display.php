<?php
/**
 * QueueEase - Public live queue display (for a TV / monitor in the waiting area)
 * File: public/display.php
 */
require_once __DIR__ . '/../includes/init.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Queue Display | <?= e(INSTITUTION_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('assets/css/display.css') ?>">
</head>
<body>
<div class="display" id="display"
     data-endpoint="<?= url('queue/api/display_data.php') ?>"
     data-interval="<?= (int) DISPLAY_REFRESH_SECONDS ?>">

    <header class="d-header">
        <div class="d-brand">
            <span class="d-logo"><?= icon('ticket') ?></span>
            <div>
                <h1><?= e(INSTITUTION_NAME) ?></h1>
                <p><?= e(APP_NAME) ?> &middot; Live Queue Display</p>
            </div>
        </div>
        <div class="d-clock">
            <div id="clockTime">--:--</div>
            <div id="clockDate"><?= e(date('l, d F Y')) ?></div>
        </div>
        <div class="d-controls">
            <button type="button" id="voiceToggle" class="d-btn" title="Voice announcements"><?= icon('volume-off') ?><span>Voice off</span></button>
            <button type="button" id="fullscreenBtn" class="d-btn" title="Full screen"><?= icon('maximize') ?></button>
        </div>
    </header>

    <main class="d-main">
        <section class="d-now">
            <div class="d-now-label">Now calling</div>
            <div class="d-now-number" id="nowNumber">----</div>
            <div class="d-now-counter" id="nowCounter">Please wait for your number</div>
            <div class="d-now-service" id="nowService"></div>
        </section>

        <section class="d-panel d-counters">
            <h2>Counters</h2>
            <div class="d-counter-grid" id="counterGrid"></div>
        </section>

        <section class="d-panel d-recent">
            <h2>Recently called</h2>
            <ul id="recentList" class="d-list"></ul>
        </section>

        <section class="d-panel d-waiting">
            <h2>Waiting</h2>
            <div id="waitingGroups"></div>
        </section>
    </main>

    <footer class="d-ticker">
        <span class="d-ticker-label"><?= icon('megaphone') ?> Notice</span>
        <div class="d-ticker-track"><div class="d-ticker-text" id="tickerText">Welcome to <?= e(INSTITUTION_NAME) ?>.</div></div>
        <span class="d-status" id="connStatus"><span class="dot"></span>Live</span>
    </footer>
</div>
<script src="<?= url('assets/js/display.js') ?>"></script>
</body>
</html>
