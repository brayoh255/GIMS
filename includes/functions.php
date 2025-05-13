<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Check if user is logged in
function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect to the login page if not logged in
function redirectToLogin() {
    header('Location: login.php');
    exit();
}

// Function to check role and redirect to appropriate dashboard
 


// Display flash messages (success, error, warning)
function displayFlashMessages() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['warning'])) {
        echo '<div class="alert alert-warning">' . $_SESSION['warning'] . '</div>';
        unset($_SESSION['warning']);
    }
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Generate random string (e.g., for password reset token)
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Format currency to TZS
function formatCurrency($amount) {
    return number_format($amount, 2) . ' TZS';
}

// Get dashboard statistics for different user roles (Sales, Admin, Manager)
function getDashboardStats($userId, $role) {
    global $pdo;

    $stats = [];

    // Common stats for all roles
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_sales FROM sales WHERE DATE(sale_date) = CURDATE()");
    $stmt->execute();
    $stats['today_sales'] = $stmt->fetchColumn();

    if ($role === ROLE_SALES) {
        $stmt = $pdo->prepare("SELECT SUM(total_amount) as amount FROM sales WHERE created_by = ? AND DATE(sale_date) = CURDATE()");
        $stmt->execute([$userId]);
        $stats['today_sales_amount'] = $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT SUM(total_amount) as amount FROM sales WHERE DATE(sale_date) = CURDATE()");
        $stmt->execute();
        $stats['today_sales_amount'] = $stmt->fetchColumn();
    }

    if ($role === ROLE_ADMIN || $role === ROLE_MANAGER) {
        // Admin/Manager specific stats
        $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM cylinder_types WHERE current_stock <= min_stock_level")->fetchColumn();
        $stats['total_debt'] = $pdo->query("SELECT SUM(balance) FROM sales WHERE balance > 0")->fetchColumn();
    }

    return $stats;
}

?>
