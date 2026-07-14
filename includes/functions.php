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
    header("Location: $url");
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
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
 * Display success message
 */
function success($message)
{
    return '<div class="alert alert-success">'.$message.'</div>';
}

/**
 * Display error message
 */
function error($message)
{
    return '<div class="alert alert-danger">'.$message.'</div>';
}