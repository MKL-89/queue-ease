<?php
/**
 * QueueEase - Shared <head> items for the theme
 * File: includes/theme_head.php
 *
 * Preloads the local fonts and applies the saved light/dark theme BEFORE
 * the page is drawn, so the screen never flashes the wrong colours.
 */
?>
    <link rel="preload" href="<?= url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= url('assets/fonts/outfit-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <meta name="theme-color" content="#0c0e1d">
    <script>
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('qe-theme'); } catch (e) { /* storage blocked */ }
            if (!theme) {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
