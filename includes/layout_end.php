        </div>

    </div>

<?php

if (isLoggedIn()) {

    require_once __DIR__ . '/footer.php';

}

?>

</div>

<!-- Bootstrap JS -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">
</script>

<!-- App JS -->

<script
src="<?= APP_URL ?>/assets/js/app.js">
</script>

</body>

</html>