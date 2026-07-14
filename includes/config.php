<?php
/**
 * ==========================================================
 * VNDTES
 * Virtual Network Diagnostic & Training Environment System
 * ==========================================================
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Application Information
|--------------------------------------------------------------------------
*/

define('APP_NAME', 'VNDTES');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/virtual_network_trainer');
define('TIMEZONE', 'Africa/Lagos');

date_default_timezone_set(TIMEZONE);

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'virtual_network_trainer_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/*
|--------------------------------------------------------------------------
| Upload Configuration
|--------------------------------------------------------------------------
*/

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('PROFILE_PHOTO', 'default.png');

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

define('SESSION_TIMEOUT', 1800); //30 minutes
define('PASSWORD_ALGO', PASSWORD_DEFAULT);