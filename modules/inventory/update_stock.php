<?php
// Include necessary files
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';

// Initialize variables
$stock = null;
$stock_id = $_GET['stock_id'] ?? null;

// Only proceed if we have a stock_id
if ($stock_id) {
    try {
        $query = "SELECT * FROM inventory WHERE id = :stock_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':stock_id', $stock_id, PDO::PARAM_INT);
        $stmt->execute();
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    }
}

// Handle form submission if we have valid stock data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stock) {
    try {
        $update_query = "UPDATE inventory SET 
                        item_name = :name,
                        brand = :brand,
                        size = :size,
                        quantity = :quantity,
                        price = :price,
                        description = :description
                        WHERE id = :stock_id";
        
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([
            ':name' => $_POST['name'],
            ':brand' => $_POST['brand'],
            ':size' => $_POST['size'],
            ':quantity' => (int)$_POST['quantity'],
            ':price' => (float)$_POST['price'],
            ':description' => $_POST['description'],
            ':stock_id' => $stock_id
        ]);

        $_SESSION['success'] = 'Stock updated successfully.';
        header('Location: view_stock.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to update stock: ' . $e->getMessage();
    }
}

// If no valid stock found, redirect
if (!$stock) {
    $_SESSION['error'] = $stock_id ? 'Stock not found.' : 'No stock selected.';
    header('Location: view_stock.php');
    exit;
}

// Selection options
$brands = ['Oryx', 'Taifagas', 'Lakegas', 'Manjis Gas', 'OGas', 'Puma Gas'];
$sizes = ['6kg', '15kg'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Stock</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { color: red; margin-bottom: 15px; padding: 10px; background: #ffebee; }
        form div { margin-bottom: 15px; }
        label { display: inline-block; width: 120px; }
        input, select, textarea {
            padding: 8px; width: 300px; border: 1px solid #ddd; border-radius: 4px;
        }
        button { 
            padding: 10px 20px; background: #4CAF50; 
            color: white; border: none; border-radius: 4px; cursor: pointer; 
        }
    </style>
</head>
<body>
    <h2>Update Stock</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <form method="POST">
        <div>
            <label for="name">Name:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($stock['item_name']) ?>" required>
        </div>

        <div>
            <label for="brand">Brand:</label>
            <select name="brand" required>
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= $brand ?>" <?= $stock['brand'] == $brand ? 'selected' : '' ?>>
                        <?= $brand ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="size">Size:</label>
            <select name="size" required>
                <?php foreach ($sizes as $size): ?>
                    <option value="<?= $size ?>" <?= $stock['size'] == $size ? 'selected' : '' ?>>
                        <?= $size ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="quantity">Quantity:</label>
            <input type="number" name="quantity" min="0" value="<?= $stock['quantity'] ?>" required>
        </div>

        <div>
            <label for="price">Price (TZS):</label>
            <input type="number" name="price" min="0" step="0.01" value="<?= $stock['price'] ?>" required>
        </div>

        <div>
            <label for="description">Description:</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($stock['description']) ?></textarea>
        </div>

        <button type="submit">Update Stock</button>
    </form>
</body>
</html>