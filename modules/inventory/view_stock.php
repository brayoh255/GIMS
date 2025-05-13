<?php
// Load configuration and dependencies
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Role check
if (!isset($_SESSION['role']) ){
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
        // Check if stock already exists
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_name = ? AND product_type = ?");
        $stmt->execute([$name, $product_type]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;

            if ($newQty <= 0) {
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $_SESSION['success_message'] = "Stock deleted because quantity became zero.";
            } else {
                $stmt = $pdo->prepare("UPDATE inventory SET quantity = ?, price = ? WHERE id = ?");
                $stmt->execute([$newQty, $unit_price, $existing['id']]);
                $_SESSION['success_message'] = "Stock quantity updated successfully!";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (item_name, quantity, price, product_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $quantity, $unit_price, $product_type]);
            $_SESSION['success_message'] = "Stock added successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding stock: " . $e->getMessage();
    }

    header("Location: view_stock.php");
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:#FF6B35;
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

        /* Sidebar Styles */
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
        .main-content{margin-left: 0;
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

        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            padding-top: 70px;
        }

        .sidebar-open .main-content {
            margin-left: 280px;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background:white;
            box-shadow: 5px 2px 5px  #FF6B35;
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

        /* Stats Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 20px;
            opacity: 0;
            transform: translateY(20px);
            background: white;
        }

        .stat-card.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }

        .card-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.54);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .table-container.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .table thead th {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(255, 107, 53, 0.05);
            transform: translateX(5px);
        }

        /* Modal Styles */
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0, 0, 0, 0.5); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            z-index: 2000; 
            opacity: 0; 
            visibility: hidden; 
            transition: all 0.3s ease; 
        }
        
        .modal-overlay.active { 
            opacity: 1; 
            visibility: visible; 
        }
        
        .modal-content { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); 
            width: 90%; 
            max-width: 600px; 
            transform: translateY(-20px); 
            transition: all 0.3s ease; 
            max-height: 90vh; 
            overflow-y: auto; 
        }
        
        .modal-header, .modal-body, .modal-footer { 
            padding: 20px; 
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .modal-close { 
            float: right; 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--dark);
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }
        
        .form-group { 
            margin-bottom: 15px; 
        }
        
        .btn-primary { 
            background-color: var(--primary); 
            border: none; 
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover { 
            background-color: #e05a2b; 
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-secondary {
            transition: all 0.2s ease;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Value Animation */
        .value-change {
            animation: valuePulse 0.6s ease;
        }

        @keyframes valuePulse {
            0% { transform: scale(1); color: inherit; }
            50% { transform: scale(1.1); color: var(--primary); }
            100% { transform: scale(1); color: inherit; }
        }
        
        /* Alert animations */
        .alert {
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .alert.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="menu-item active">
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
                <span> Expenses</span>
            </a>
            
            <!-- Debts -->
            <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="menu-item">
                <i class="fas fa-list"></i>
                <span> Debts</span>
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

    <!-- Top Navigation -->
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
            
            <div class="dropdown-menu" id="dropdownMenu" style="display: none; position: absolute; right: 0; background: white; box-shadow: 0 10px 20px rgba(0,0,0,0.1); border-radius: 8px; padding: 10px 0; min-width: 200px; z-index: 1000;">
                <a href="#" class="dropdown-item" style="display: block; padding: 8px 20px; color: var(--dark); text-decoration: none; transition: all 0.2s ease;">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item" style="display: block; padding: 8px 20px; color: var(--danger); text-decoration: none; transition: all 0.2s ease;">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-0">Stock Management</h2>
                    <p class="text-muted">Manage your inventory and stock levels</p>
                </div>
            </div>
            
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success show">
                    <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger show">
                    <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-12">
                    <button id="openAddStockModal" class="btn btn-primary mb-3"><i class="fas fa-plus"></i> Add New Stock</button>
                    <a href="generate_stock_pdf.php" target="_blank" class="btn btn-danger mb-3">
    <i class="fas fa-file-pdf"></i> Generate PDF
</a>

                    
                    <div class="table-container" id="stockTable">
                        <table class="table table-hover">
                            <thead>
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
                                        <td><?= ucwords(htmlspecialchars($stock['item_name'])) ?></td>
                                        <td><?= ucfirst($stock['product_type']) ?></td>
                                        <td><?= (int)$stock['quantity'] ?></td>
                                        <td><?= number_format($stock['price'], 0) ?> TZS</td>
                                        <td><?= date('Y-m-d', strtotime($stock['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Stock Modal -->
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
                dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                }
            });
            
            // Modal functionality
            const modal = document.getElementById('addStockModal');
            document.getElementById('openAddStockModal').addEventListener('click', () => modal.classList.add('active'));
            document.getElementById('closeAddStockModal').addEventListener('click', () => modal.classList.remove('active'));
            document.getElementById('cancelAddStock').addEventListener('click', () => modal.classList.remove('active'));
            window.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
            
            // Animate elements on load
            function animateElements() {
                // Animate table container
                setTimeout(() => {
                    document.getElementById('stockTable').classList.add('animated');
                }, 300);
                
                // Animate alerts if they exist
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach((alert, index) => {
                    setTimeout(() => {
                        alert.classList.add('show');
                    }, index * 150);
                });
            }
            
            // Initialize
            animateElements();
            
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
        
        function toggleFields() {
            const type = document.getElementById('product_type').value;
            document.getElementById('gasFields').style.display = (type === 'gas') ? 'block' : 'none';
            document.getElementById('supplyFields').style.display = (type === 'supply') ? 'block' : 'none';
        }
    </script>

    
</body>
</html>