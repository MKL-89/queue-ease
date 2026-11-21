<?php
/**
 * QueueEase - Gradient welcome banner shown at the top of dashboards
 * File: includes/hero_banner.php
 *
 * Set $hero before including:
 *   $hero = [
 *     'eyebrow' => 'Administrator',              // small label (optional)
 *     'text'    => 'One sentence summary',       // under the greeting
 *     'metrics' => [['12', 'Waiting'], ...],     // big numbers (optional)
 *     'actions' => [['Label', 'path.php', 'icon', 'light|glass', newTab?], ...],
 *   ];
 */
$hour     = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first    = explode(' ', current_user()['name'] ?? '')[0];
?>
<section class="hero-banner">
    <div>
        <span class="hero-eyebrow"><?= icon('sparkle') ?> <?= e($hero['eyebrow'] ?? APP_NAME) ?></span>
        <h2><?= e($greeting . ($first ? ', ' . $first : '')) ?></h2>
        <p><?= e($hero['text'] ?? '') ?></p>
        <?php if (!empty($hero['metrics'])): ?>
            <div class="hero-metric">
                <?php foreach ($hero['metrics'] as [$value, $label]): ?>
                    <div><strong><?= e($value) ?></strong><span><?= e($label) ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($hero['actions'])): ?>
        <div class="hero-actions">
            <?php foreach ($hero['actions'] as $a): ?>
                <a class="btn btn-<?= e($a[3] ?? 'glass') ?>" href="<?= url($a[1]) ?>"<?= !empty($a[4]) ? ' target="_blank" rel="noopener"' : '' ?>><?= icon($a[2]) ?> <?= e($a[0]) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
