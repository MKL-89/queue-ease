<?php
/**
 * QueueEase - Decorative side panel for the login and register pages
 * File: includes/auth_aside.php
 * Optional: $asideTitle, $asideText
 */
?>
<aside class="auth-aside">
    <div>
        <span class="hero-eyebrow"><?= icon('sparkle') ?> <?= e(INSTITUTION_NAME) ?></span>
        <h2><?= e($asideTitle ?? 'Your place in the queue, in your pocket.') ?></h2>
        <p><?= e($asideText ?? 'Take a digital ticket, follow your position live and walk in only when it is your turn.') ?></p>
    </div>
    <ul class="auth-features">
        <li><?= icon('ticket') ?> Digital tickets in seconds</li>
        <li><?= icon('clock') ?> Live position and waiting time</li>
        <li><?= icon('volume') ?> Voice calls on the live display</li>
        <li><?= icon('lock') ?> Secure, private and fair</li>
    </ul>
    <div class="auth-mock" aria-hidden="true">
        <div><small>Now calling</small><strong>MED004</strong></div>
        <span>Counter 2</span>
    </div>
</aside>
