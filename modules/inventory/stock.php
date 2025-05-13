<?php
// stock.php - Comprehensive Stock Management System
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication and role
checkRole(['admin', 'manager', 'staff']);

// Initialize variables
$success = '';
$error = '';
$stocks = [];
$totalValue = 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add Stock Operation
        if (isset($_POST['add_stock'])) {
            $product_type = isset($_POST['product_type']) && in_array($_POST['product_type'], ['gas', 'supply']) 
                ? $_POST['product_type'] 
                : null;
            
            $brand = isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : null;
            $size = isset($_POST['size']) ? htmlspecialchars($_POST['size']) : null;
            $product_name = isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : null;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
            $unit_price = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0.0;

            // Validate required fields
            if (!$product_type || $quantity <= 0 || $unit_price <= 0) {
                throw new Exception("Please fill all required fields with valid values.");
            }

            // Create item name based on product type
            $name = $product_type === 'gas' ? "$brand $size" : $product_name;

            // Prepare and execute the query
            $stmt = $pdo->prepare("INSERT INTO inventory (item_name, quantity, price, product_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $quantity, $unit_price, $product_type]);

            $success = "Stock added successfully!";
        }
        
        // Update Stock Operation
        elseif (isset($_POST['update_stock'])) {
            $stock_id = (int)$_POST['stock_id'];
            $quantity = (int)$_POST['quantity'];
            $unit_price = (float)$_POST['unit_price'];
            
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = ?, price = ? WHERE id = ?");
            $stmt->execute([$quantity, $unit_price, $stock_id]);
            
            $success = "Stock updated successfully!";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete operation
if (isset($_GET['delete'])) {
    try {
        $stock_id = (int)$_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$stock_id]);
        $success = "Stock item deleted successfully!";
    } catch (Exception $e) {
        $error = "Error deleting stock: " . $e->getMessage();
    }
}

// Fetch all stock records
$query = "SELECT 
            id AS stock_id,
            item_name,
            quantity,
            price,
            product_type,
            created_at,
            updated_at
          FROM inventory
          ORDER BY updated_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total inventory value
foreach ($stocks as $stock) {
    $totalValue += $stock['quantity'] * $stock['price'];
}

// Prepare data for reports
$reportData = [
    'total_items' => count($stocks),
    'total_value' => $totalValue,
    'gas_count' => array_reduce($stocks, function($carry, $item) {
        return $carry + ($item['product_type'] === 'gas' ? 1 : 0);
    }, 0),
    'supply_count' => array_reduce($stocks, function($carry, $item) {
        return $carry + ($item['product_type'] === 'supply' ? 1 : 0);
    }, 0),
    'low_stock' => array_filter($stocks, function($item) {
        return $item['quantity'] < 10;
    })
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .summary-card {
            border-left: 4px solid #4CAF50;
            margin-bottom: 20px;
        }
        .badge-gas {
            background-color: #17a2b8;
        }
        .badge-supply {
            background-color: #6c757d;
        }
        .low-stock {
            background-color: #fff3cd;
        }
        .critical-stock {
            background-color: #f8d7da;
        }
        #searchInput {
            max-width: 300px;
        }
        .chart-container {
            height: 300px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="mb-4">Stock Management System</h1>
        
        <!-- Display messages -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="stockTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="view-tab" data-bs-toggle="tab" data-bs-target="#view" type="button" role="tab">
                    <i class="fas fa-list"></i> View Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="add-tab" data-bs-toggle="tab" data-bs-target="#add" type="button" role="tab">
                    <i class="fas fa-plus"></i> Add Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab">
                    <i class="fas fa-chart-bar"></i> Stock Reports
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content" id="stockTabsContent">
            <!-- View Stock Tab -->
            <div class="tab-pane fade show active" id="view" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <p class="card-text display-6"><?= $reportData['total_items'] ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Inventory Value</h5>
                                <p class="card-text display-6"><?= number_format($reportData['total_value'], 2) ?> TZS</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card summary-card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Items</h5>
                                <p class="card-text display-6"><?= count($reportData['low_stock']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Current Stock</h5>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search items...">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Quantity</th>
                                        <th>Price (TZS)</th>
                                        <th>Type</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($stocks) > 0): ?>
                                        <?php foreach ($stocks as $stock): ?>
                                            <tr class="<?= $stock['quantity'] < 5 ? 'critical-stock' : ($stock['quantity'] < 10 ? 'low-stock' : '') ?>">
                                                <td><?= $stock['stock_id'] ?></td>
                                                <td><?= htmlspecialchars($stock['item_name']) ?></td>
                                                <td><?= $stock['quantity'] ?></td>
                                                <td><?= number_format($stock['price'], 2) ?></td>
                                                <td>
                                                    <span class="badge rounded-pill <?= $stock['product_type'] === 'gas' ? 'bg-info' : 'bg-secondary' ?>">
                                                        <?= ucfirst($stock['product_type']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($stock['updated_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                            data-bs-target="#editModal" 
                                                            data-id="<?= $stock['stock_id'] ?>"
                                                            data-name="<?= htmlspecialchars($stock['item_name']) ?>"
                                                            data-qty="<?= $stock['quantity'] ?>"
                                                            data-price="<?= $stock['price'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <a href="stock.php?delete=<?= $stock['stock_id'] ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to delete this item?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No stock records found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Stock Tab -->
            <div class="tab-pane fade" id="add" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Add New Stock Item</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Product Type:</label>
                                    <select name="product_type" id="product_type" class="form-select" onchange="toggleFields()" required>
                                        <option value="gas">Gas Cylinder</option>
                                        <option value="supply">Other Supply</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="gasFields">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Gas Brand:</label>
                                        <select name="brand" class="form-select">
                                            <option value="Oryx">Oryx</option>
                                            <option value="Taifa Gas">Taifa Gas</option>
                                            <option value="Lake Gas">Lake Gas</option>
                                            <option value="Manjis Gas">Manjis Gas</option>
                                            <option value="OGas">OGas</option>
                                            <option value="Puma Gas">Puma Gas</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cylinder Size:</label>
                                        <select name="size" class="form-select">
                                            <option value="6kg">6kg</option>
                                            <option value="15kg">15kg</option>
                                            <option value="50kg">50kg</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="supplyFields" class="d-none">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Supply Name:</label>
                                        <select name="product_name" class="form-select">
                                            <option value="Trivet">Trivet</option>
                                            <option value="Banner">Banner</option>
                                            <option value="Regulator">Regulator</option>
                                            <option value="Gas Pipe">Gas Pipe</option>
                                            <option value="Clam">Clam</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Quantity:</label>
                                    <input type="number" name="quantity" class="form-control" min="1" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Unit Price (TZS):</label>
                                    <input type="number" name="unit_price" class="form-control" min="0.01" step="0.01" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_stock" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Stock
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Reports Tab -->
            <div class="tab-pane fade" id="report" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Inventory Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="inventoryChart"></canvas>
                                </div>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total Gas Cylinders
                                        <span class="badge bg-info rounded-pill"><?= $reportData['gas_count'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total Supplies
                                        <span class="badge bg-secondary rounded-pill"><?= $reportData['supply_count'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total Inventory Value
                                        <span class="badge bg-success rounded-pill"><?= number_format($reportData['total_value'], 2) ?> TZS</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Low Stock Alerts</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($reportData['low_stock']) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Qty</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData['low_stock'] as $item): ?>
                                                    <tr class="<?= $item['quantity'] < 5 ? 'table-danger' : 'table-warning' ?>">
                                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                        <td><?= $item['quantity'] ?></td>
                                                        <td><?= number_format($item['quantity'] * $item['price'], 2) ?> TZS</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> All stock levels are adequate
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Export Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="generate_report.php?type=pdf" class="btn btn-danger btn-block">
                                    <i class="fas fa-file-pdf"></i> Export as PDF
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="generate_report.php?type=excel" class="btn btn-success btn-block">
                                    <i class="fas fa-file-excel"></i> Export as Excel
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="generate_report.php?type=csv" class="btn btn-primary btn-block">
                                    <i class="fas fa-file-csv"></i> Export as CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Stock Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="stock_id" id="editStockId">
                        <div class="mb-3">
                            <label class="form-label">Item Name:</label>
                            <input type="text" class="form-control" id="editItemName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity:</label>
                            <input type="number" name="quantity" id="editQuantity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Price (TZS):</label>
                            <input type="number" name="unit_price" id="editPrice" class="form-control" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_stock" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Toggle fields based on product type
        function toggleFields() {
            const type = document.getElementById('product_type').value;
            document.getElementById('gasFields').classList.toggle('d-none', type !== 'gas');
            document.getElementById('supplyFields').classList.toggle('d-none', type !== 'supply');
        }
        
        // Initialize fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFields();
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('keyup', function() {
                const input = this.value.toLowerCase();
                const rows = document.querySelectorAll('#view tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(input) ? '' : 'none';
                });
            });
            
            // Edit modal setup
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    document.getElementById('editStockId').value = button.getAttribute('data-id');
                    document.getElementById('editItemName').value = button.getAttribute('data-name');
                    document.getElementById('editQuantity').value = button.getAttribute('data-qty');
                    document.getElementById('editPrice').value = button.getAttribute('data-price');
                });
            }
            
            // Initialize chart
            const ctx = document.getElementById('inventoryChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Gas Cylinders', 'Supplies'],
                    datasets: [{
                        data: [<?= $reportData['gas_count'] ?>, <?= $reportData['supply_count'] ?>],
                        backgroundColor: ['#17a2b8', '#6c757d'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>