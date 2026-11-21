<?php
/**
 * QueueEase - Dashboard layout footer
 * File: includes/footer.php
 * A page may set $extraScripts (HTML) before including this file.
 */
?>
        </main>
        <footer class="app-footer">
            &copy; <?= date('Y') ?> <?= e(INSTITUTION_NAME) ?> &middot; <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?>
        </footer>
    </div>
</div>
<script>window.APP = { baseUrl: <?= json_encode(BASE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?> };</script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
