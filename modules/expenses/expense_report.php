<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/session.php';

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

// Get filter parameters from GET request
$filter_category = $_GET['category'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_min_amount = $_GET['min_amount'] ?? '';
$filter_max_amount = $_GET['max_amount'] ?? '';

// Base query (modify if using user_id)
$query = "SELECT * FROM expenses WHERE 1=1";
$params = [];

// Add filters
if (!empty($filter_category)) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}
if (!empty($filter_date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $filter_date_to;
}
if (!empty($filter_min_amount)) {
    $query .= " AND amount >= ?";
    $params[] = $filter_min_amount;
}
if (!empty($filter_max_amount)) {
    $query .= " AND amount <= ?";
    $params[] = $filter_max_amount;
}

$query .= " ORDER BY created_at DESC";

// Execute query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="expenses_export_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output Excel content
echo "<table border='1'>";
echo "<tr><th>Date</th><th>Description</th><th>Category</th><th>Amount</th></tr>";

$total = 0;
foreach ($expenses as $expense) {
    $total += $expense['amount'];
    echo "<tr>";
    echo "<td>" . date('m/d/Y', strtotime($expense['created_at'])) . "</td>";
    echo "<td>" . htmlspecialchars($expense['description']) . "</td>";
    echo "<td>" . htmlspecialchars($expense['category']) . "</td>";
    echo "<td>" . number_format($expense['amount'], 2) . "</td>";
    echo "</tr>";
}

echo "<tr><td colspan='3'><strong>Total</strong></td><td><strong>" . number_format($total, 2) . "</strong></td></tr>";
echo "</table>";
exit;
?>