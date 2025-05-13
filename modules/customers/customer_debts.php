<?php
require_once '../../config/db.php';

$query = "SELECT customer_name, SUM(total_amount) AS total, SUM(amount_paid) AS paid, (SUM(total_amount) - SUM(amount_paid)) AS debt
          FROM sales
          GROUP BY customer_name
          HAVING debt > 0";

$stmt = $pdo->query($query);
$debts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Customer Debts</h2>
<table border="1">
    <tr>
        <th>Customer</th><th>Total Sales</th><th>Paid</th><th>Debt</th>
    </tr>
    <?php foreach ($debts as $row): ?>
    <tr>
        <td><?= $row['customer_name'] ?></td>
        <td><?= number_format($row['total'], 2) ?> TZS</td>
        <td><?= number_format($row['paid'], 2) ?> TZS</td>
        <td><?= number_format($row['debt'], 2) ?> TZS</td>
    </tr>
    <?php endforeach; ?>
</table>
