<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/session.php';


// Define constants
defined('APP_NAME') or define('APP_NAME', 'GIMS');
define('CYLINDER_TYPES', ['6kg', '12.5kg', '25kg', '50kg']);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: ../../login.php");
    exit;


    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../../index.php');
        exit;
    }}
// Initialize variables
$action = $_GET['action'] ?? 'view';
$sale_id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Handle actions
try {
    // Record New Sale
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'record') {
        $pdo->beginTransaction();
        
        // Process sale data
        $customer_name = clean_input($_POST['customer_name']);
        $customer_phone = clean_input($_POST['customer_phone']);
        $created_by = $_SESSION['user_id'];
        $empty_cylinders = $_POST['empty_cylinders'] ?? [];
        
        // 1. Create sale record
        $stmt = $pdo->prepare("INSERT INTO sales (customer_name, customer_phone, created_by, created_at) 
                              VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_name, $customer_phone, $created_by]);
        $sale_id = $pdo->lastInsertId();
        
        // 2. Process products
        $total_amount = 0;
        foreach ($_POST['products'] as $product) {
            $product_id = (int)$product['id'];
            $quantity = (int)$product['quantity'];
            
            // Verify product
            $stmt = $pdo->prepare("SELECT item_name, price, quantity FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product_data || $product_data['quantity'] < $quantity) {
                throw new Exception("Invalid product or insufficient stock");
            }
            
            $price = (float)$product_data['price'];
            $product_total = $price * $quantity;
            $total_amount += $product_total;
            
            // Add to sale items
            $stmt = $pdo->prepare("INSERT INTO sale_items 
                                  (sale_id, product_id, product_name, quantity, price, total_amount)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sale_id, $product_id, $product_data['item_name'], 
                $quantity, $price, $product_total
            ]);
            
            // Update inventory
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
        }
        
        // 3. Process empty cylinders
        foreach ($empty_cylinders as $cylinder) {
            $brand = clean_input($cylinder['brand']);
            $size = clean_input($cylinder['size']);
            
            $stmt = $pdo->prepare("INSERT INTO empty_cylinders 
                                  (sale_id, brand, size, received_at) 
                                  VALUES (?, ?, ?, NOW())");
            $stmt->execute([$sale_id, $brand, $size]);
        }
        
        // 4. Update sale with payment info
        $amount_paid = (float)$_POST['amount_paid'];
        $stmt = $pdo->prepare("UPDATE sales SET total_amount = ?, amount_paid = ? WHERE id = ?");
        $stmt->execute([$total_amount, $amount_paid, $sale_id]);
        
        // 5. Record debt if partial payment
        if ($amount_paid < $total_amount) {
            $balance = $total_amount - $amount_paid;
            $stmt = $pdo->prepare("INSERT INTO debts 
                                  (customer_name, customer_phone, sale_id, amount, paid_amount, balance)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customer_name, $customer_phone, $sale_id,
                $total_amount, $amount_paid, $balance
            ]);
        }
        
        $pdo->commit();
        $message = "Sale recorded successfully! Receipt #$sale_id";
        
        // Reset form or redirect
        if (isset($_POST['save_and_new'])) {
            header("Location: sales.php?action=record");
            exit;
        } else {
            header("Location: sales.php?action=view&id=$sale_id");
            exit;
        }
    }
    
    // Add Empty Cylinder (standalone)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add_cylinder') {
        $brand = clean_input($_POST['brand']);
        $size = clean_input($_POST['size']);
        $notes = clean_input($_POST['notes']);
        
        $stmt = $pdo->prepare("INSERT INTO empty_cylinders 
                              (brand, size, notes, received_at) 
                              VALUES (?, ?, ?, NOW())");
        $stmt->execute([$brand, $size, $notes]);
        
        $message = "Empty cylinder recorded successfully";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    $error = "Error: " . $e->getMessage();
}

// Fetch data based on action
$products = [];
$sales = [];
$sale_details = [];
$empty_cylinders = [];
$cylinder_report = [];
$brands = [];

try {
    // For recording sales - get available products
    if ($action == 'record') {
        $stmt = $pdo->query("SELECT id, item_name, price, quantity, size 
                            FROM inventory WHERE quantity > 0 
                            ORDER BY item_name");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For viewing sales - get sales list or single sale
    if ($action == 'view') {
        if ($sale_id) {
            // Single sale view
            $stmt = $pdo->prepare("SELECT s.*, u.username as created_by_name 
                                  FROM sales s
                                  JOIN users u ON s.created_by = u.id
                                  WHERE s.id = ?");
            $stmt->execute([$sale_id]);
            $sale_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sale_details) {
                $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
                $stmt->execute([$sale_id]);
                $sale_details['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SELECT * FROM empty_cylinders WHERE sale_id = ?");
                $stmt->execute([$sale_id]);
                $sale_details['cylinders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Sales list view
            $page = max(1, intval($_GET['page'] ?? 1));
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            
            $query = "SELECT s.*, u.username as created_by_name 
                     FROM sales s
                     JOIN users u ON s.created_by = u.id
                     ORDER BY s.created_at DESC
                     LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Count total sales for pagination
            $stmt = $pdo->query("SELECT COUNT(*) FROM sales");
            $total_sales = $stmt->fetchColumn();
            $total_pages = ceil($total_sales / $per_page);
        }
    }
    
    // For reports - get cylinder data
    if ($action == 'reports' && isset($_GET['report_type']) && $_GET['report_type'] == 'cylinders') {
        $date_from = $_GET['date_from'] ?? date('Y-m-01');
        $date_to = $_GET['date_to'] ?? date('Y-m-d');
        $size_filter = $_GET['size'] ?? '';
        
        $query = "SELECT brand, size, COUNT(*) as count 
                 FROM empty_cylinders 
                 WHERE DATE(received_at) BETWEEN :date_from AND :date_to";
        
        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        
        if (!empty($size_filter)) {
            $query .= " AND size = :size";
            $params[':size'] = $size_filter;
        }
        
        $query .= " GROUP BY brand, size ORDER BY count DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $cylinder_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get unique brands for filter
        $stmt = $pdo->query("SELECT DISTINCT brand FROM empty_cylinders ORDER BY brand");
        $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?> - Sales Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card { margin-bottom: 20px; }
        .table-responsive { overflow-x: auto; }
        .product-row { margin-bottom: 10px; }
        .cylinder-row { margin-bottom: 10px; background-color: #f8f9fa; padding: 10px; border-radius: 5px; }
        .modal-dialog-scrollable .modal-body { max-height: 70vh; }
        .badge-paid { background-color: #28a745; }
        .badge-pending { background-color: #ffc107; color: #000; }
        .nav-tabs .nav-link.active { font-weight: bold; }
        .summary-card { background-color: #f8f9fa; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Error/Success Messages -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- Main Navigation Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $action == 'view' ? 'active' : '' ?>" 
                   href="sales.php?action=view">
                    <i class="bi bi-list-ul"></i> View Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $action == 'record' ? 'active' : '' ?>" 
                   href="sales.php?action=record">
                    <i class="bi bi-plus-circle"></i> Record Sale
                </a>
            </li>
         
            <li class="nav-item">
                <a class="nav-link <?= $action == 'reports' ? 'active' : '' ?>" 
                   href="sales.php?action=reports">
                    <i class="bi bi-graph-up"></i> Reports
                </a>
            </li>
            
        </ul>
        
        <!-- Content Area -->
        <?php if ($action == 'record'): ?>
            <!-- Record New Sale Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="bi bi-cart-plus"></i> Record New Sale</h4>
                </div>
                <div class="card-body">
                    <form method="post" id="sale-form">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Customer Information</h5>
                                <div class="mb-3">
                                    <label for="customer_name" class="form-label">Customer Name</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="customer_phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="customer_phone" name="customer_phone" 
                                           pattern="[0-9]{10}" title="10 digit phone number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Products</h5>
                                <div id="product-container">
                                    <!-- Product rows will be added here by JavaScript -->
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="add-product">
                                    <i class="bi bi-plus"></i> Add Product
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Empty Cylinders Returned</h5>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="has-empty-cylinders">
                                    <label class="form-check-label" for="has-empty-cylinders">
                                        Customer returned empty cylinders
                                    </label>
                                </div>
                                
                                <div id="cylinder-container" style="display: none;">
                                    <!-- Cylinder rows will be added here -->
                                </div>
                                <button type="button" class="btn btn-outline-secondary mt-2" id="add-cylinder">
                                    <i class="bi bi-plus"></i> Add Empty Cylinder
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Payment Information</h5>
                                <div class="mb-3">
                                    <label for="total-amount" class="form-label">Total Amount</label>
                                    <input type="text" class="form-control" id="total-amount" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid</label>
                                    <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" required>
                                </div>
                                <div class="mb-3">
                                    <label for="balance-amount" class="form-label">Balance</label>
                                    <input type="text" class="form-control" id="balance-amount" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-success" name="save_and_new">
                                <i class="bi bi-save"></i> Save & New
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save & View
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Add Cylinder Modal (for standalone cylinder addition) -->
            <div class="modal fade" id="addCylinderModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Empty Cylinder</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="sales.php?action=add_cylinder">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand" required>
                                </div>
                                <div class="mb-3">
                                    <label for="size" class="form-label">Size</label>
                                    <select class="form-select" id="size" name="size" required>
                                        <option value="">Select Size</option>
                                        <?php foreach (CYLINDER_TYPES as $size): ?>
                                            <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($action == 'view' && $sale_id): ?>
            <!-- View Single Sale -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h4><i class="bi bi-receipt"></i> Sale Receipt #<?= htmlspecialchars($sale_id) ?></h4>
                    <div>
                        <a href="print_receipt.php?id=<?= $sale_id ?>" target="_blank" class="btn btn-light btn-sm">
                            <i class="bi bi-printer"></i> Print
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Sale Information</h5>
                            <p><strong>Date:</strong> <?= htmlspecialchars(date('M j, Y H:i', strtotime($sale_details['created_at']))) ?></p>
                            <p><strong>Recorded by:</strong> <?= htmlspecialchars($sale_details['created_by_name']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Customer Information</h5>
                            <p><strong>Name:</strong> <?= htmlspecialchars($sale_details['customer_name']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($sale_details['customer_phone']) ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Products Sold</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Qty</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sale_details['items'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                            <td class="text-end"><?= htmlspecialchars($item['quantity']) ?></td>
                                            <td class="text-end"><?= number_format($item['total_amount'], 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Total:</th>
                                            <th class="text-end"><?= number_format($sale_details['total_amount'], 2) ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Amount Paid:</th>
                                            <th class="text-end"><?= number_format($sale_details['amount_paid'], 2) ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Balance:</th>
                                            <th class="text-end">
                                                <?= number_format(max($sale_details['total_amount'] - $sale_details['amount_paid'], 0), 2) ?>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($sale_details['cylinders'])): ?>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Empty Cylinders Returned</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Brand</th>
                                            <th>Size</th>
                                            <th>Received At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sale_details['cylinders'] as $cylinder): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cylinder['brand']) ?></td>
                                            <td><?= htmlspecialchars($cylinder['size']) ?></td>
                                            <td><?= htmlspecialchars(date('M j, Y H:i', strtotime($cylinder['received_at']))) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="sales.php?action=view" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Sales
                        </a>
                        <div>
                            <a href="sales.php?action=edit&id=<?= $sale_id ?>" class="btn btn-warning">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <a href="sales.php?action=record" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> New Sale
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($action == 'view'): ?>
            <!-- View Sales List -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between">
                    <h4><i class="bi bi-list-ul"></i> Sales Records</h4>
                    <a href="sales.php?action=record" class="btn btn-light btn-sm">
                        <i class="bi bi-plus-circle"></i> New Sale
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sale['id']) ?></td>
                                    <td><?= htmlspecialchars(date('M j, Y', strtotime($sale['created_at']))) ?></td>
                                    <td>
                                        <?= htmlspecialchars($sale['customer_name']) ?>
                                        <?php if (!empty($sale['customer_phone'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($sale['customer_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= number_format($sale['total_amount'], 2) ?></td>
                                    <td class="text-end"><?= number_format($sale['amount_paid'], 2) ?></td>
                                    <td class="text-end">
                                        <?php $balance = $sale['total_amount'] - $sale['amount_paid']; ?>
                                        <?= $balance > 0 ? number_format($balance, 2) : '0.00' ?>
                                    </td>
                                    <td>
                                        <?php if ($sale['amount_paid'] >= $sale['total_amount']): ?>
                                            <span class="badge badge-paid">Paid</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="sales.php?action=view&id=<?= $sale['id'] ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="sales.php?action=edit&id=<?= $sale['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="print_receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Print">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sales)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No sales records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-3">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?action=view&page=<?= $page - 1 ?>">
                                    Previous
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=view&page=<?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?action=view&page=<?= $page + 1 ?>">
                                    Next
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($action == 'reports' && $is_admin): ?>
            <!-- Reports Section -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="bi bi-graph-up"></i> Sales Reports</h4>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" 
                                    data-bs-target="#summary-tab-pane" type="button" role="tab">
                                Sales Summary
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cylinders-tab" data-bs-toggle="tab" 
                                    data-bs-target="#cylinders-tab-pane" type="button" role="tab">
                                Empty Cylinders
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="reportTabsContent">
                        <div class="tab-pane fade show active" id="summary-tab-pane" role="tabpanel">
                            <form method="get" class="row g-3 mb-4">
                                <input type="hidden" name="action" value="reports">
                                <input type="hidden" name="report_type" value="summary">
                                
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?= htmlspecialchars($_GET['date_from'] ?? date('Y-m-01')) ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?= htmlspecialchars($_GET['date_to'] ?? date('Y-m-d')) ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">Group By</label>
                                    <select name="group_by" class="form-select">
                                        <option value="day" <?= ($_GET['group_by'] ?? 'day') == 'day' ? 'selected' : '' ?>>Day</option>
                                        <option value="week" <?= ($_GET['group_by'] ?? '') == 'week' ? 'selected' : '' ?>>Week</option>
                                        <option value="month" <?= ($_GET['group_by'] ?? '') == 'month' ? 'selected' : '' ?>>Month</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-filter"></i> Generate
                                    </button>
                                    <a href="export_report.php?<?= http_build_query(array_merge($_GET, ['report_type' => 'summary'])) ?>" 
                                       class="btn btn-success ms-2">
                                        <i class="bi bi-download"></i> Export
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Summary report content would go here -->
                            <div class="alert alert-info">
                                Sales summary report would be displayed here with charts and data tables.
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="cylinders-tab-pane" role="tabpanel">
                            <form method="get" class="row g-3 mb-4">
                                <input type="hidden" name="action" value="reports">
                                <input type="hidden" name="report_type" value="cylinders">
                                
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="date_from" class="form-control" 
                                           value="<?= htmlspecialchars($_GET['date_from'] ?? date('Y-m-01')) ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="date_to" class="form-control" 
                                           value="<?= htmlspecialchars($_GET['date_to'] ?? date('Y-m-d')) ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Brand</label>
                                    <select name="brand" class="form-select">
                                        <option value="">All Brands</option>
                                        <?php foreach ($brands as $brand): ?>
                                            <option value="<?= htmlspecialchars($brand) ?>" 
                                                <?= ($_GET['brand'] ?? '') == $brand ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($brand) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Size</label>
                                    <select name="size" class="form-select">
                                        <option value="">All Sizes</option>
                                        <?php foreach (CYLINDER_TYPES as $size): ?>
                                            <option value="<?= htmlspecialchars($size) ?>" 
                                                <?= ($_GET['size'] ?? '') == $size ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($size) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-filter"></i> Generate
                                    </button>
                                    <a href="export_report.php?<?= http_build_query(array_merge($_GET, ['report_type' => 'cylinders'])) ?>" 
                                       class="btn btn-success ms-2">
                                        <i class="bi bi-download"></i> Export
                                    </a>
                                </div>
                            </form>
                            
                            <?php if (!empty($cylinder_report)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Brand</th>
                                            <th>Size</th>
                                            <th class="text-end">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cylinder_report as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['brand']) ?></td>
                                            <td><?= htmlspecialchars($item['size']) ?></td>
                                            <td class="text-end"><?= number_format($item['count']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                No empty cylinder records found for the selected criteria.
                            </div>
                            <?php endif; ?>
                            
                            <!-- Button to trigger add cylinder modal -->
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCylinderModal">
                                <i class="bi bi-plus"></i> Add Empty Cylinder
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Product data from PHP
        const products = <?= json_encode($products ?? []) ?>;
        let productCounter = 0;
        let cylinderCounter = 0;
        
        // Initialize form when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add first product row
            addProductRow();
            
            // Toggle empty cylinders section
            document.getElementById('has-empty-cylinders').addEventListener('change', function() {
                document.getElementById('cylinder-container').style.display = 
                    this.checked ? 'block' : 'none';
            });
            
            // Add product button
            document.getElementById('add-product').addEventListener('click', addProductRow);
            
            // Add cylinder button
            document.getElementById('add-cylinder').addEventListener('click', addCylinderRow);
            
            // Calculate totals when payment amount changes
            document.getElementById('amount_paid').addEventListener('input', updateTotals);
        });
        
        // Add product row to form
        function addProductRow(selectedProductId = '', quantity = 1) {
            const container = document.getElementById('product-container');
            const rowId = `product-${productCounter++}`;
            
            const row = document.createElement('div');
            row.className = 'row product-row';
            row.id = rowId;
            
            row.innerHTML = `
                <div class="col-md-6">
                    <select name="products[${rowId}][id]" class="form-select product-select" required>
                        <option value="">-- Select Product --</option>
                        ${products.map(p => 
                            `<option value="${p.id}" data-price="${p.price}" data-stock="${p.quantity}"
                             ${selectedProductId == p.id ? 'selected' : ''}>
                                ${p.item_name} ${p.size ? '('+p.size+')' : ''}
                            </option>`
                        ).join('')}
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="products[${rowId}][quantity]" class="form-control quantity-input" 
                           min="1" value="${quantity}" required>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <span class="stock-info">Stock: <span class="stock-value">-</span></span>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger remove-btn" onclick="removeRow('${rowId}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(row);
            
            // Add event listeners to new row
            const select = row.querySelector('.product-select');
            const quantityInput = row.querySelector('.quantity-input');
            
            select.addEventListener('change', function() {
                updateProductStock(this);
                updateTotals();
            });
            
            quantityInput.addEventListener('input', function() {
                validateQuantity(this);
                updateTotals();
            });
            
            // Initialize if product is selected
            if (selectedProductId) {
                updateProductStock(select);
            }
        }
        
        // Add empty cylinder row to form
        function addCylinderRow(brand = '', size = '') {
            const container = document.getElementById('cylinder-container');
            const rowId = `cylinder-${cylinderCounter++}`;
            
            const row = document.createElement('div');
            row.className = 'row cylinder-row';
            row.id = rowId;
            
            row.innerHTML = `
                <div class="col-md-4">
                    <input type="text" name="empty_cylinders[${rowId}][brand]" 
                           class="form-control" placeholder="Brand" value="${brand}" required>
                </div>
                <div class="col-md-3">
                    <select name="empty_cylinders[${rowId}][size]" class="form-select" required>
                        <option value="">Select Size</option>
                        ${CYLINDER_TYPES.map(t => 
                            `<option value="${t}" ${size == t ? 'selected' : ''}>${t}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="empty_cylinders[${rowId}][notes]" 
                           class="form-control" placeholder="Notes">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger remove-btn" onclick="removeRow('${rowId}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(row);
        }
        
        // Update product stock display
        function updateProductStock(select) {
            const row = select.closest('.product-row');
            const stockValue = row.querySelector('.stock-value');
            const quantityInput = row.querySelector('.quantity-input');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const availableStock = parseInt(selectedOption.dataset.stock);
                
                stockValue.textContent = availableStock;
                quantityInput.max = availableStock;
                
                if (parseInt(quantityInput.value) > availableStock) {
                    quantityInput.value = availableStock;
                }
            } else {
                stockValue.textContent = '-';
                quantityInput.removeAttribute('max');
            }
        }
        
        // Validate quantity doesn't exceed stock
        function validateQuantity(input) {
            const row = input.closest('.product-row');
            const select = row.querySelector('.product-select');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const availableStock = parseInt(selectedOption.dataset.stock);
                const requestedQty = parseInt(input.value) || 0;
                
                if (requestedQty > availableStock) {
                    input.value = availableStock;
                }
            }
        }
        
        // Calculate and update totals
        function updateTotals() {
            let total = 0;
            
            // Calculate product totals
            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('.product-select');
                const quantityInput = row.querySelector('.quantity-input');
                
                if (select.value && quantityInput.value) {
                    const price = parseFloat(select.options[select.selectedIndex].dataset.price);
                    const quantity = parseInt(quantityInput.value);
                    total += price * quantity;
                }
            });
            
            // Update display
            const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
            const balance = total - amountPaid;
            
            document.getElementById('total-amount').value = total.toFixed(2);
            document.getElementById('balance-amount').value = balance.toFixed(2);
        }
        
        // Remove a row
        function removeRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) {
                row.remove();
                updateTotals();
            }
        }
    </script>
</body>
</html>