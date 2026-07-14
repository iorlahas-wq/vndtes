<?php

require_once '../includes/init.php';

session_unset();

session_destroy();

redirect(APP_URL);

?>