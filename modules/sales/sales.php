<?php
// modules/dashboard/admin.php

// Load configuration and dependencies
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_ADMIN);

// Fetch all available products (quantity > 0) with their current stock
$products = [];
try {
    $stmt = $pdo->query("SELECT id, item_name, price, quantity, size FROM inventory WHERE quantity > 0 ORDER BY item_name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to fetch products: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = '+255' . trim($_POST['customer_phone_suffix']);

    $created_by = $_SESSION['user_id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // 1. Create sale record
        $stmt = $pdo->prepare("INSERT INTO sales (customer_name, customer_phone, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_name, $customer_phone, $created_by]);
        $sale_id = $pdo->lastInsertId();

        // 2. Process each product
        $total_sale_amount = 0;
        
        foreach ($_POST['products'] as $productData) {
            $product_id = (int)$productData['id'];
            $quantity = (int)$productData['quantity'];
            
            // Verify product exists and has sufficient stock
            $stmt = $pdo->prepare("SELECT item_name, price, quantity FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found or out of stock");
            }

            if ($product['quantity'] < $quantity) {
                throw new Exception("Insufficient stock for {$product['item_name']}. Available: {$product['quantity']}");
            }

            $price = (float)$product['price'];
            $total_amount = $quantity * $price;
            $total_sale_amount += $total_amount;

            // Add to sale items
            $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, total_amount)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sale_id,
                $product_id,
                $product['item_name'],
                $quantity,
                $price,
                $total_amount
            ]);

            // Update inventory
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
        }

        // 3. Update sale with total amount
        $amount_paid = (float)$_POST['amount_paid'];
        $stmt = $pdo->prepare("UPDATE sales SET total_amount = ?, amount_paid = ? WHERE id = ?");
        $stmt->execute([$total_sale_amount, $amount_paid, $sale_id]);

        // 4. Record debt if payment is less than total
        if ($amount_paid < $total_sale_amount) {
            $balance = $total_sale_amount - $amount_paid;
            $stmt = $pdo->prepare("INSERT INTO debts (customer_name, customer_phone, sale_id, amount, paid_amount, balance)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customer_name,
                $customer_phone,
                $sale_id,
                $total_sale_amount,
                $amount_paid,
                $balance
            ]);
        }

        $pdo->commit();
        
        $_SESSION['success'] = "Sale recorded successfully! Total: " . number_format($total_sale_amount, 2);
        if ($amount_paid < $total_sale_amount) {
            $_SESSION['success'] .= ". Outstanding balance: " . number_format($total_sale_amount - $amount_paid, 2);
        }
        header("Location: view_sales.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

$username = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record New Sale | GIMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #FF6B35;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1a1a1a;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ef233c;
            --info: #7209b7;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            overflow-x: hidden;
        }

        /* Sidebar Styles (same as admin.php) */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: #004E89;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 5px 0 25px rgba(0,0,0,0.1);
            transform: translateX(-100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }

        .logo {
            display: flex;
            align-items: center;
            color: white;
        }

        .logo-icon {
            font-size: 2rem;
            margin-right: 10px;
            color: var(--accent);
            animation: pulse 2s infinite;
        }

        .sidebar-menu {
            height: calc(100vh - 180px);
            overflow-y: auto;
            padding: 15px 0;
        }

        /* Custom scrollbar */
        .sidebar-menu::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.2);
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            margin: 5px 10px;
            border-radius: 8px;
        }

        .menu-item:hover, .menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .menu-item i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }

        .menu-item.active::after {
            content: '';
            position: absolute;
            right: 15px;
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        /* Main Content adjustments */
        .main-content {
            margin-left: 0;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            padding-top: 70px;
        }

        .sidebar-open .main-content {
            margin-left: 280px;
        }

        /* Top Navigation adjustments */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            box-shadow: 5px 2px 5px #FF6B35;
            z-index: 900;
            display: flex;
            align-items: center;
            padding: 0 20px;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-open .top-nav {
            left: 280px;
        }
        /* Top Navigation (same as admin.php) */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            box-shadow: 5px 2px 5px #FF6B35;
            z-index: 900;
            display: flex;
            align-items: center;
            padding: 0 20px;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-open .top-nav {
            left: 280px;
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            margin-right: 20px;
            transition: transform 0.3s ease;
        }

        .toggle-btn:hover {
            transform: rotate(90deg);
            color: var(--primary);
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px;
            animation: fadeIn 0.5s ease;
        }

        .form-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 53, 0.25);
        }

        /* Product Row Styles */
        .product-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
            padding: 15px;
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            transition: all 0.3s ease;
            animation: slideIn 0.3s ease;
        }

        .product-row:hover {
            background: rgba(0,0,0,0.05);
        }

        .product-select {
            flex: 2;
        }

        .quantity-input {
            flex: 1;
        }

        .stock-info {
            flex: 1;
            color: #666;
            font-size: 0.9rem;
        }

        .remove-btn {
            flex: 0 0 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--danger);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            transform: scale(1.1);
            background: #d32f2f;
        }

        /* Button Styles */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #e05a2a;
            border-color: #e05a2a;
            transform: translateY(-2px);
        }

        .btn-add {
            background-color: var(--success);
            border-color: var(--success);
            margin: 15px 0;
        }

        .btn-add:hover {
            background-color: #3ab7d8;
            border-color: #3ab7d8;
        }

        /* Summary Card */
        .summary-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid var(--primary);
            animation: fadeIn 0.5s ease;
        }

        .summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .balance-warning {
            color: var(--warning);
            font-weight: 600;
            margin-top: 5px;
            display: none;
        }

        /* Alert Styles */
        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .product-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .product-select, .quantity-input, .stock-info {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as admin.php) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div>
                    <h1 style="margin: 0; font-weight: 700;">GIMS</h1>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <a href="<?= BASE_URL ?>modules/dashboard/admin.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Stock Management -->
            <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="menu-item">
                <i class="fas fa-boxes"></i>
                <span>Stock</span>
            </a>
            <!-- Sales -->
            <a href="<?= BASE_URL ?>modules/sales/sales.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Record Sale</span>
            </a>
  
            <!-- Sales -->
            <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Sales Management</span>
            </a>

            
        
            
            <!-- Expenses -->
            <a href="<?= BASE_URL ?>modules/expenses/record_expense.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Expenses</span>
            </a>
            
            <!-- Debts -->
            <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="menu-item">
                <i class="fas fa-list"></i>
                <span>Debts</span>
            </a>
            
            <!-- Admin Management -->
            <a href="<?= BASE_URL ?>modules/add/manage.php" class="menu-item">
                <i class="fas fa-users-cog"></i>
                <span>User & Cylinder Management</span>
            </a>
            
            <div style="padding: 20px 10px;">
                <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 10px 0;"></div>
            </div>
        </div>
    </div>

    <!-- Top Navigation (same as admin.php) -->
    <div class="top-nav" id="topNav">
        <button class="toggle-btn" id="toggleBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <div style="flex: 1;"></div>
        
        <div class="user-dropdown">
            <button class="btn" id="userDropdown" style="display: flex; align-items: center;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>
                <?= htmlspecialchars($username) ?>
                <i class="fas fa-chevron-down ml-2" style="font-size: 0.8rem;"></i>
            </button>
            
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="#" class="dropdown-item">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item" style="color: var(--danger);">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid py-4">
            <div class="form-container">
                <h2 class="form-title">Record New Sale</h2>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <form method="post" id="sale-form">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Customer Name:</label>
                                <input type="text" class="form-control" name="customer_name" id="customer_name" required>
                            </div>
                        </div>

                    
                    </div><div class="col-md-6">
    <div class="mb-3">
        <label for="customer_phone" class="form-label">Phone Number:</label>
        <div class="input-group">
            <span class="input-group-text">+255</span>
            <input type="tel" class="form-control" name="customer_phone_suffix" id="customer_phone_suffix"
                   placeholder="712345678"
                   pattern="[0-9]{9}"
                   title="Enter 9 digit phone number (e.g., 712345678)" required>
        </div>
    </div>
</div>

                    
                    <h4 class="mb-3">Products</h4>
                    <div id="product-container">
                        <!-- Product rows will be added here -->
                    </div>
                    
                    <button type="button" id="add-product" class="btn btn-add">
                        <i class="fas fa-plus-circle"></i> Add Product
                    </button>
                    
                    <div class="summary-card">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Total Amount:</label>
                                    <div class="summary-value" id="total-amount">0.00 Tsh</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="amount_paid" class="form-label">Amount Paid:</label>
                                    <input type="number" class="form-control" name="amount_paid" id="amount_paid" min="0" step="0.01" required>
                                    <div id="balance-warning" class="balance-warning"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Balance:</label>
                                    <div class="summary-value" id="balance-amount">0.00 Tsh</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle Sidebar
            const toggleBtn = document.getElementById('toggleBtn');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const topNav = document.getElementById('topNav');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                document.body.classList.toggle('sidebar-open');
                
                // Change icon
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('open')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
            
            // User Dropdown
            const userDropdown = document.getElementById('userDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            userDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                dropdownMenu.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                }
            });

            // Product data from PHP
            const products = <?= json_encode($products) ?>;
            let productCounter = 0;

            // Add new product row
            function addProductRow(selectedProductId = '', quantity = 1) {
                const container = document.getElementById('product-container');
                const rowId = `product-${productCounter++}`;
                
                const row = document.createElement('div');
                row.className = 'product-row';
                row.id = rowId;
                
                // Product select dropdown
                const selectDiv = document.createElement('div');
                selectDiv.className = 'product-select';
                
                const select = document.createElement('select');
                select.className = 'form-select';
                select.name = `products[${rowId}][id]`;
                select.required = true;
                select.addEventListener('change', updateProductSelection);
                
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '-- Select Product --';
                select.appendChild(defaultOption);
                
                products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = `${product.item_name} ${product.size ? '('+product.size+')' : ''}`;
                    option.dataset.price = product.price;
                    option.dataset.stock = product.quantity;
                    if (product.id == selectedProductId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                
                selectDiv.appendChild(select);
                row.appendChild(selectDiv);
                
                // Quantity input
                const qtyDiv = document.createElement('div');
                qtyDiv.className = 'quantity-input';
                
                const qtyInput = document.createElement('input');
                qtyInput.type = 'number';
                qtyInput.className = 'form-control';
                qtyInput.name = `products[${rowId}][quantity]`;
                qtyInput.min = 1;
                qtyInput.value = quantity;
                qtyInput.required = true;
                qtyInput.addEventListener('input', updateTotalAndStock);
                
                qtyDiv.appendChild(qtyInput);
                row.appendChild(qtyDiv);
                
                // Stock information
                const stockDiv = document.createElement('div');
                stockDiv.className = 'stock-info';
                stockDiv.innerHTML = '<span class="stock-label">Stock:</span> <span class="stock-value">-</span>';
                row.appendChild(stockDiv);
                
                // Remove button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-btn';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.addEventListener('click', () => {
                    row.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        container.removeChild(row);
                        updateTotal();
                    }, 300);
                });
                
                row.appendChild(removeBtn);
                container.appendChild(row);
                
                // Trigger change event to set initial values if product is selected
                if (selectedProductId) {
                    select.dispatchEvent(new Event('change'));
                    qtyInput.value = quantity;
                    qtyInput.dispatchEvent(new Event('input'));
                }
            }
            
            // Update product selection and stock info
            function updateProductSelection(event) {
                const select = event.target;
                const row = select.closest('.product-row');
                const stockValue = row.querySelector('.stock-value');
                const quantityInput = row.querySelector('input[type="number"]');
                
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
                
                updateTotal();
            }
            
            // Update stock display when quantity changes
            function updateTotalAndStock(event) {
                const quantityInput = event.target;
                const row = quantityInput.closest('.product-row');
                const select = row.querySelector('select');
                const stockValue = row.querySelector('.stock-value');
                
                if (select.value) {
                    const selectedOption = select.options[select.selectedIndex];
                    const availableStock = parseInt(selectedOption.dataset.stock);
                    const requestedQty = parseInt(quantityInput.value) || 0;
                    
                    if (requestedQty > availableStock) {
                        quantityInput.value = availableStock;
                    }
                    
                    stockValue.textContent = availableStock - (parseInt(quantityInput.value) || 0);
                }
                
                updateTotal();
            }
            
            // Calculate and update total amount and balance
            function updateTotal() {
                let total = 0;
                
                document.querySelectorAll('.product-row').forEach(row => {
                    const select = row.querySelector('select');
                    const quantityInput = row.querySelector('input[type="number"]');
                    
                    if (select.value && quantityInput.value) {
                        const price = parseFloat(select.options[select.selectedIndex].dataset.price);
                        const quantity = parseInt(quantityInput.value);
                        total += price * quantity;
                    }
                });
                
                const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
                const balance = total - amountPaid;
                
                document.getElementById('total-amount').textContent = total.toFixed(2) + ' Tsh';
                document.getElementById('balance-amount').textContent = balance.toFixed(2) + ' Tsh';
                
                // Show warning if balance exists
                const balanceWarning = document.getElementById('balance-warning');
                if (balance > 0) {
                    balanceWarning.style.display = 'block';
                    balanceWarning.textContent = `Customer will owe: ${balance.toFixed(2)} Tsh`;
                } else {
                    balanceWarning.style.display = 'none';
                }
            }
            
            // Add amount paid listener
            document.getElementById('amount_paid').addEventListener('input', updateTotal);
            
            // Add initial product row
            document.getElementById('add-product').addEventListener('click', () => addProductRow());
            
            // Add one product row by default when page loads
            addProductRow();

            // Responsive adjustments
            function handleResize() {
                if (window.innerWidth < 992) {
                    sidebar.classList.remove('open');
                    document.body.classList.remove('sidebar-open');
                    toggleBtn.querySelector('i').classList.remove('fa-times');
                    toggleBtn.querySelector('i').classList.add('fa-bars');
                }
            }
            
            window.addEventListener('resize', handleResize);
            handleResize(); // Run on initial load
        });
    </script>
</body>
</html>