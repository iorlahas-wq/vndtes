<?php
/**
 * ==========================================================
 * VNDTES
 * Virtual Network Diagnostic & Training Environment System
 * ==========================================================
 * Application Bootstrap File
 *
 * Loads all core system files required by the application.
 * Every page in the system should include this file.
 * ==========================================================
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Core System Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';