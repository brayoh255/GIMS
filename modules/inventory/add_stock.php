<?php
// Load configuration and dependencies
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_type = $_POST['product_type'];
    $brand = $_POST['brand'] ?? null;
    $size = $_POST['size'] ?? null;
    $product_name = $_POST['product_name'] ?? null;
    $quantity = (int)$_POST['quantity'];
    $unit_price = (float)$_POST['unit_price'];

    $name = $product_type === 'gas' ? "$brand $size" : $product_name;

    try {
        $stmt = $pdo->prepare("INSERT INTO inventory (item_name, quantity, price, product_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $quantity, $unit_price, $product_type]);
        $_SESSION['success_message'] = "Stock added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding stock: " . $e->getMessage();
    }
    header("Location: stock.php");
    exit();
}

// Fetch stock data
$stocks = $pdo->query("SELECT * FROM inventory ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management | GIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --dark: #1a1a1a;
            --light: #f8f9fa;
        }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center; z-index: 2000; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); width: 90%; max-width: 600px; transform: translateY(-20px); transition: all 0.3s ease; max-height: 90vh; overflow-y: auto; }
        .modal-header, .modal-body, .modal-footer { padding: 20px; }
        .modal-close { float: right; background: none; border: none; font-size: 1.5rem; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .btn-primary { background-color: var(--primary); border: none; }
        .btn-primary:hover { background-color: #e05a2b; }
        .table th, .table td { vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2 class="mb-4">Stock Management</h2>

        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success"> <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?> </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"> <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?> </div>
        <?php endif; ?>

        <button id="openAddStockModal" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Add New Stock</button>

        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Date Added</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stocks as $i => $stock): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($stock['item_name']) ?></td>
                        <td><?= ucfirst($stock['product_type']) ?></td>
                        <td><?= (int)$stock['quantity'] ?></td>
                        <td><?= number_format($stock['price'], 0) ?> TZS</td>
                        <td><?= date('Y-m-d', strtotime($stock['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="addStockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Stock</h5>
                <button class="modal-close" id="closeAddStockModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Product Type</label>
                        <select class="form-control" name="product_type" id="product_type" onchange="toggleFields()" required>
                            <option value="gas">Gas</option>
                            <option value="supply">Other Supply</option>
                        </select>
                    </div>
                    <div id="gasFields">
                        <div class="form-group">
                            <label>Gas Brand</label>
                            <select class="form-control" name="brand">
                                <option value="Oryx">Oryx</option>
                                <option value="Taifa Gas">Taifa Gas</option>
                                <option value="Lake Gas">Lake Gas</option>
                                <option value="Manjis Gas">Manjis Gas</option>
                                <option value="OGas">OGas</option>
                                <option value="Puma Gas">Puma Gas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Cylinder Size</label>
                            <select class="form-control" name="size">
                                <option value="6kg">6kg</option>
                                <option value="15kg">15kg</option>
                            </select>
                        </div>
                    </div>
                    <div id="supplyFields" style="display: none">
                        <div class="form-group">
                            <label>Supply Name</label>
                            <select class="form-control" name="product_name">
                                <option value="Trivet">Trivet</option>
                                <option value="Banner">Banner</option>
                                <option value="Regulator">Regulator</option>
                                <option value="Gas Pipe">Gas Pipe</option>
                                <option value="Clam">Clam</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" class="form-control" name="quantity" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price (TZS)</label>
                        <input type="number" class="form-control" name="unit_price" step="0.01" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="cancelAddStock">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('addStockModal');
        document.getElementById('openAddStockModal').addEventListener('click', () => modal.classList.add('active'));
        document.getElementById('closeAddStockModal').addEventListener('click', () => modal.classList.remove('active'));
        document.getElementById('cancelAddStock').addEventListener('click', () => modal.classList.remove('active'));
        window.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
        function toggleFields() {
            const type = document.getElementById('product_type').value;
            document.getElementById('gasFields').style.display = (type === 'gas') ? 'block' : 'none';
            document.getElementById('supplyFields').style.display = (type === 'supply') ? 'block' : 'none';
        }
    </script>
</body>
</html>