<?php
require_once '../../config/db.php';

// Fetch all stock items
$stmt = $pdo->query("SELECT product_name, quantity, unit_price FROM inventory");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total value
$totalValue = 0;
foreach ($items as $item) {
    $totalValue += $item['quantity'] * $item['unit_price'];
}
?>

<h2>Inventory Report</h2>
<table border="1">
    <tr>
        <th>Product</th><th>Quantity</th><th>Unit Price</th><th>Total Value</th>
    </tr>
    <?php foreach ($items as $item): ?>
    <tr>
        <td><?= $item['product_name'] ?></td>
        <td><?= $item['quantity'] ?></td>
        <td><?= number_format($item['unit_price'], 2) ?> TZS</td>
        <td><?= number_format($item['quantity'] * $item['unit_price'], 2) ?> TZS</td>
    </tr>
    <?php endforeach; ?>
</table>

<p><strong>Total Inventory Value:</strong> <?= number_format($totalValue, 2) ?> TZS</p>
