<?php
/**
 * QueueEase - Layout footer for public pages
 * File: includes/public_footer.php
 */
?>
</main>
<footer class="public-footer">
    &copy; <?= date('Y') ?> <?= e(INSTITUTION_NAME) ?> &middot; Powered by <?= e(APP_NAME) ?>
</footer>
<script>window.APP = { baseUrl: <?= json_encode(BASE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
