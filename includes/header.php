<?php
/**
 * QueueEase - Dashboard layout header (sidebar + top bar)
 * File: includes/header.php
 *
 * Before including, a page may set:
 *   $pageTitle   - text in the browser tab and the top bar
 *   $activeMenu  - key of the sidebar item to highlight
 *   $extraHead   - extra HTML for <head> (optional)
 */
$pageTitle  = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$user       = current_user();
$role       = $user['role'] ?? '';

$menus = [
    'admin' => [
        ['dashboard',     'Dashboard',       'admin/dashboard.php',      'dashboard'],
        ['tickets',       'All Tickets',     'admin/tickets.php',        'ticket'],
        ['section',       'Management'],
        ['services',      'Services',        'admin/services.php',       'layers'],
        ['counters',      'Counters',        'admin/counters.php',       'monitor'],
        ['staff',         'Staff',           'admin/staff.php',          'users'],
        ['assignments',   'Counter Assignments', 'admin/assignments.php', 'link'],
        ['customers',     'Customers',       'admin/customers.php',      'user'],
        ['announcements', 'Announcements',   'admin/announcements.php',  'megaphone'],
        ['section',       'Insights'],
        ['reports',       'Reports',         'reports/index.php',        'chart'],
        ['logs',          'Activity Logs',   'admin/activity_logs.php',  'activity'],
        ['section',       'Public'],
        ['display',       'Live Display',    'public/display.php',       'tv', true],
    ],
    'staff' => [
        ['dashboard', 'Counter Console',   'staff/dashboard.php', 'counter'],
        ['waiting',   'Waiting Customers', 'staff/waiting.php',   'list'],
        ['history',   'Service History',   'staff/history.php',   'history'],
        ['section',   'Public'],
        ['display',   'Live Display',      'public/display.php',  'tv', true],
    ],
    'customer' => [
        ['dashboard', 'My Dashboard',     'customer/dashboard.php',  'dashboard'],
        ['services',  'Get a Ticket',     'customer/get_ticket.php', 'ticket'],
        ['history',   'My Ticket History', 'customer/history.php',   'history'],
        ['section',   'Public'],
        ['display',   'Live Display',     'public/display.php',      'tv', true],
    ],
];
$roleLabels = ['admin' => 'Administrator', 'staff' => 'Staff', 'customer' => 'Customer'];

// Icon of the current page (taken from the menu) for the page banner
$pageIcon = 'dashboard';
foreach ($menus[$role] ?? [] as $item) {
    if ($item[0] === $activeMenu && isset($item[3])) {
        $pageIcon = $item[3];
    }
}
// Short description under the page title (a page may set $pageSubtitle itself)
$subtitles = [
    'admin' => [
        'tickets'       => 'Search, filter, export and manage every ticket in the system.',
        'services'      => 'Create the services customers can queue for and set their ticket prefixes.',
        'counters'      => 'Open, close and organise the service points that call customers.',
        'staff'         => 'Register staff accounts and control who can serve customers.',
        'assignments'   => 'Decide which staff member works at which counter.',
        'customers'     => 'Manage registered and walk-in customers.',
        'announcements' => 'Publish notices for the live display and customer pages.',
        'reports'       => 'Measure waiting times, service performance and customer satisfaction.',
        'logs'          => 'A complete audit trail of logins, changes and queue actions.',
    ],
    'staff' => [
        'waiting' => 'Everyone waiting for your services today, in order.',
        'history' => 'Every customer you have called, served or skipped.',
    ],
    'customer' => [
        'services' => 'Choose a service and join the queue in one click.',
        'history'  => 'All your tickets, past and present.',
    ],
];
$pageSubtitle = $pageSubtitle ?? ($subtitles[$role][$activeMenu] ?? '');
$showPageHero = $activeMenu !== 'dashboard' && empty($hidePageHero);
$initials   = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(explode(' ', $user['name'] ?? 'U'), 0, 2))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/images/favicon.svg') ?>" type="image/svg+xml">
    <?php require __DIR__ . '/theme_head.php'; ?>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
    <?= $extraHead ?? '' ?>
</head>
<body class="app-body role-<?= e($role) ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-logo"><?= icon('ticket') ?></span>
            <div>
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= e($roleLabels[$role] ?? '') ?> Panel</small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($menus[$role] ?? [] as $item): ?>
                <?php if ($item[0] === 'section'): ?>
                    <div class="nav-section"><?= e($item[1]) ?></div>
                <?php else: ?>
                    <a href="<?= url($item[2]) ?>" class="nav-link<?= $activeMenu === $item[0] ? ' active' : '' ?>"<?= !empty($item[4]) ? ' target="_blank" rel="noopener"' : '' ?>>
                        <?= icon($item[3]) ?><span><?= e($item[1]) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-profile">
            <span class="avatar"><?= e($initials) ?></span>
            <div><strong><?= e($user['name'] ?? '') ?></strong><small><?= e($roleLabels[$role] ?? '') ?></small></div>
            <span class="status-dot" title="Online"></span>
        </div>
        <form method="post" action="<?= url('logout.php') ?>" class="sidebar-logout">
            <?= csrf_field() ?>
            <button type="submit" class="nav-link nav-button"><?= icon('logout') ?><span>Log out</span></button>
        </form>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main-area">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Open menu"><?= icon('menu') ?></button>
            <div class="topbar-title">
                <div class="crumbs"><?= icon('home', 'icon crumb-icon') ?><span><?= e($roleLabels[$role] ?? '') ?></span><span class="crumb-sep">/</span><h1><?= e($pageTitle) ?></h1></div>
                <span class="topbar-date"><span data-greeting data-name="<?= e(explode(' ', $user['name'] ?? '')[0]) ?>"></span><span data-live-clock><?= e(date('l, d F Y')) ?></span></span>
            </div>
            <div class="topbar-user">
                <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Switch light or dark mode" title="Light / dark mode">
                    <?= icon('sun', 'icon icon-sun') ?><?= icon('moon', 'icon icon-moon') ?>
                </button>
                <div class="user-meta">
                    <strong><?= e($user['name'] ?? '') ?></strong>
                    <small><?= e($roleLabels[$role] ?? '') ?></small>
                </div>
                <span class="avatar"><?= e($initials) ?></span>
            </div>
        </header>

        <main class="content">
            <?php if ($showPageHero): ?>
                <section class="page-hero">
                    <span class="page-hero-icon"><?= icon($pageIcon) ?></span>
                    <div class="page-hero-text">
                        <span class="page-hero-eyebrow"><?= e($roleLabels[$role] ?? '') ?> &middot; <?= e(APP_NAME) ?></span>
                        <h2><?= e($pageTitle) ?></h2>
                        <?php if ($pageSubtitle !== ''): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" data-dismissible>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert-close" aria-label="Close">&times;</button>
                </div>
            <?php endforeach; ?>
