<?php
require_once '../../config/db.php';

// Total Sales Paid
$salesStmt = $pdo->query("SELECT SUM(amount_paid) AS total_sales FROM sales");
$salesTotal = $salesStmt->fetch(PDO::FETCH_ASSOC)['total_sales'];

// Total Expenses
$expenseStmt = $pdo->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$expenseTotal = $expenseStmt->fetch(PDO::FETCH_ASSOC)['total_expenses'];

// Profit or Loss
$net = $salesTotal - $expenseTotal;
?>

<h2>Financial Report</h2>
<table border="1">
    <tr><th>Total Sales Received</th><td><?= number_format($salesTotal, 2) ?> TZS</td></tr>
    <tr><th>Total Expenses</th><td><?= number_format($expenseTotal, 2) ?> TZS</td></tr>
    <tr><th>Net Profit / Loss</th><td><?= number_format($net, 2) ?> TZS</td></tr>
</table>
