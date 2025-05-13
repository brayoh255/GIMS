<?php
// Include necessary files
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';

// Fetch all stock records
$query = "SELECT 
            id AS stock_id,
            item_name,
            size,
            quantity,
            price,
            product_type
          FROM inventory";
$stmt = $pdo->prepare($query);
$stmt->execute();
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display messages
if (isset($_SESSION['success'])) {
    echo '<div class="success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div class="error">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Stock</title>
    <style>
        .success { color: green; padding: 10px; background: #e8f5e9; }
        .error { color: red; padding: 10px; background: #ffebee; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; position: sticky; top: 0; }
        tr:hover { background-color: #f5f5f5; }
        a { color: #2196F3; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h2>Stock List</h2>
    <a href="add_stock.php" style="display: inline-block; margin-bottom: 20px; padding: 8px 15px; background: #4CAF50; color: white; border-radius: 4px;">Add New Stock</a>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                
                <th>Qty</th>
                <th>Price (TZS)</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($stocks) > 0): ?>
                <?php foreach ($stocks as $stock): ?>
                    <tr>
                        <td><?= htmlspecialchars($stock['stock_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($stock['item_name'] ?? 'N/A') ?></td>
                       
                        <td><?= htmlspecialchars($stock['quantity'] ?? '0') ?></td>
                        <td><?= isset($stock['price']) ? number_format((float)$stock['price'], 2) : '0.00' ?></td>
                        <td><?= ($stock['product_type'] === 'gas') ? 'Gas' : 'Supply' ?></td>
                        <td>
                            <a href="update_stock.php?stock_id=<?= htmlspecialchars($stock['stock_id'] ?? '') ?>">Edit</a> |
                            <a href="delete_stock.php?stock_id=<?= htmlspecialchars($stock['stock_id'] ?? '') ?>" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">No stock records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>