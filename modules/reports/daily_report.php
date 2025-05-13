<?php
require_once '../../config/database.php';

// Today's date
$today = date('Y-m-d');

// Fetch today's sales
$stmt = $pdo->prepare("SELECT * FROM sales WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sum today's sales
$totalStmt = $pdo->prepare("SELECT SUM(amount_paid) as total_sales FROM sales WHERE DATE(created_at) = ?");
$totalStmt->execute([$today]);
$total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total_sales'];
?>

<h2>Daily Sales Report (<?= $today ?>)</h2>
<table border="1">
    <tr>
        <th>Customer</th><th>Total</th><th>Paid</th><th>Date</th>
    </tr>
    <?php foreach ($sales as $sale): ?>
    <tr>
        <td><?= $sale['customer_name'] ?></td>
        <td><?= number_format($sale['total_amount'], 2) ?> TZS</td>
        <td><?= number_format($sale['amount_paid'], 2) ?> TZS</td>
        <td><?= $sale['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>


