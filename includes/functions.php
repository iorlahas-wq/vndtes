<?php

require_once __DIR__ . '/db.php';

/**
 * Return PDO instance
 */
function db()
{
    global $pdo;

    return $pdo;
}

/**
 * Clean user input
 */
function sanitize($value)
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to another page
 */
function redirect($url)
{
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href='{$url}';</script>";
    }

    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
   return !empty($_SESSION['user_id']);
}

/**
 * Require authentication
 */
function requireLogin()
{
    if (!isLoggedIn()) {

        redirect(APP_URL . '/auth/login.php');

    }
}

/**
 * Get logged-in user ID
 */
function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get logged-in user's role
 */
function currentUserRole()
{
    return $_SESSION['role_name'] ?? null;
}

/**
 * Check user role
 */
function hasRole($role)
{
    return currentUserRole() === $role;
}

/**
 * Display Bootstrap alert
 *
 * @param string $message
 * @param string $type
 * @return string
 */
function alert($message, $type = 'primary')
{
    return '
    <div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">

        '.$message.'

        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close">
        </button>

    </div>';
}

/**
 * Get logged in user's full name
 */
function currentUserName()
{
    return $_SESSION['full_name'] ?? '';
}

/**
 * Get profile photo
 */
function currentUserPhoto()
{
    return $_SESSION['profile_photo'] ?? 'default.png';
}

/**
 * Require a specific role
 */
function requireRole($role)
{
    requireLogin();

    if (!hasRole($role)) {

        redirect(APP_URL);

    }
}

function getDepartments()
{
    return db()->query("
        SELECT *
        FROM departments
        ORDER BY department_name
    ")->fetchAll();
}

function getProgrammeTypes()
{
    return db()->query("
        SELECT *
        FROM programme_types
        ORDER BY programme_name
    ")->fetchAll();
}

function getProgrammeOptions()
{
    return db()->query("
        SELECT *
        FROM programme_options
        ORDER BY option_name
    ")->fetchAll();
}

function getLevels()
{
    return db()->query("
        SELECT *
        FROM levels
        ORDER BY level_id
    ")->fetchAll();
}

function getAcademicSessions()
{
    return db()->query("
        SELECT *
        FROM academic_sessions
        ORDER BY session_id DESC
    ")->fetchAll();
}