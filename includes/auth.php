<?php

require_once __DIR__ . '/functions.php';

/*
|--------------------------------------------------------------------------
| Session Timeout
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['LAST_ACTIVITY'])) {

    if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT) {

        session_unset();
        session_destroy();

        redirect(APP_URL . '/auth/login.php?expired=1');
    }
}

$_SESSION['LAST_ACTIVITY'] = time();

/*
|--------------------------------------------------------------------------
| User Authentication
|--------------------------------------------------------------------------
*/

requireLogin();