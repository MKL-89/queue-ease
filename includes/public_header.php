<?php
/**
 * QueueEase - Layout header for public pages (home, login, register, guest ticket, tracking)
 * File: includes/public_header.php
 */
$pageTitle = $pageTitle ?? APP_NAME;
$user      = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
    <?php require __DIR__ . '/theme_head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
</head>
<body class="public-body">
<header class="public-nav">
    <a href="<?= url('index.php') ?>" class="public-brand">
        <span class="brand-logo"><?= icon('ticket') ?></span>
        <span><strong><?= e(APP_NAME) ?></strong><small><?= e(INSTITUTION_NAME) ?></small></span>
    </a>
    <nav class="public-links">
        <a href="<?= url('queue/guest_ticket.php') ?>">Get Ticket</a>
        <a href="<?= url('track.php') ?>">Track Ticket</a>
        <a href="<?= url('public/display.php') ?>" target="_blank" rel="noopener">Live Display</a>
        <button class="icon-btn theme-toggle public-theme" type="button" data-theme-toggle aria-label="Switch light or dark mode" title="Light / dark mode">
            <?= icon('sun', 'icon icon-sun') ?><?= icon('moon', 'icon icon-moon') ?>
        </button>
        <?php if ($user): ?>
            <a href="<?= url(dashboard_for_role($user['role'])) ?>" class="btn btn-primary btn-sm">My Dashboard</a>
        <?php else: ?>
            <a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm">Log in</a>
            <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="public-main">
    <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> public-alert" data-dismissible>
            <span><?= e($flash['message']) ?></span>
            <button type="button" class="alert-close" aria-label="Close">&times;</button>
        </div>
    <?php endforeach; ?>
