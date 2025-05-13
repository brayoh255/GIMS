<?php
// Required includes
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Ensure only salespeople can access
checkRole(ROLE_SALES);

$userId = getCurrentUserId();

// Query today's sales for this user
$stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE created_by = ? AND DATE(sale_date) = CURDATE()");
$stmt->execute([$userId]);
$salesToday = $stmt->fetchColumn() ?? 0;

// Output number formatted total
echo number_format($salesToday, 2);
