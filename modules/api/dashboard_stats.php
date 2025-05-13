<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';

checkRole(ROLE_SALES);

header('Content-Type: application/json');

$userId = getCurrentUserId();
$response = ['success' => false];

try {
    // Today's Sales
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount), 0) as total FROM sales WHERE created_by = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$userId]);
    $salesToday = $stmt->fetchColumn();
    
    // Monthly Sales
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(total_amount), 0) as total FROM sales WHERE created_by = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute([$userId]);
    $salesMonth = $stmt->fetchColumn();
    
    // Pending Debts
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(balance), 0) as total FROM debts WHERE created_by = ? AND balance > 0");
    $stmt->execute([$userId]);
    $pendingDebts = $stmt->fetchColumn();
    
    $response = [
        'success' => true,
        'salesToday' => $salesToday,
        'salesMonth' => $salesMonth,
        'pendingDebts' => $pendingDebts
    ];
} catch (PDOException $e) {
    $response['error'] = "Database error: " . $e->getMessage();
}

echo json_encode($response);