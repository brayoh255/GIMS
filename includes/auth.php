<?php


require_once __DIR__ . '/../config/constants.php';

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN;
    }
}


// auth.php - Single source for authentication functions

/**
 * Check if user is logged in
 */

 
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Require user to be logged in
 */
if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: ../login.php');
            exit();
        }
    }
}


/**
 * Check user role
 */
if (!function_exists('checkRole')) {
    function checkRole($requiredRole) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
            header("Location: " . BASE_URL . "unauthorized.php");
            exit();
        }
    }
}

/**
 * Check for multiple possible roles
 */
if (!function_exists('checkAnyRole')) {
    function checkAnyRole($allowedRoles) {
        if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
            header("Location: " . BASE_URL . "unauthorized.php");
            exit();
        }
    }
}
?>