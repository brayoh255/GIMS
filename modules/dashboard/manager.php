<?php
// modules/dashboard/manager.php

// Load dependencies
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_MANAGER);

// Initialize variables with default values
$todaySales = $totalDebt = $totalStock = $emptyCylinders = $monthlySales = 0;

// Get dashboard metrics with error handling
try {
    // Today's sales
    $stmtSales = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(created_at) = CURDATE()");
    $stmtSales->execute();
    $todaySales = $stmtSales->fetchColumn();
    
    // Total debt
    $stmtDebt = $pdo->prepare("SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS total_debt FROM sales WHERE total_amount > amount_paid");
    $stmtDebt->execute();
    $totalDebt = $stmtDebt->fetchColumn();
    
    // Total stock (from inventory table where product_type is 'gas')
    $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_type = 'gas'");
    $stmtStock->execute();
    $totalStock = $stmtStock->fetchColumn();
    
    // Empty cylinders count (from cylinders table where status indicates empty)
    $stmtEmpty = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM cylinders WHERE status = 'empty'");
    $stmtEmpty->execute();
    $emptyCylinders = $stmtEmpty->fetchColumn();
    
    // Monthly sales
    $stmtMonthly = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmtMonthly->execute();
    $monthlySales = $stmtMonthly->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Manager Dashboard Error: " . $e->getMessage());
    // Values will use the defaults set above
}

$username = $_SESSION['username'] ?? 'Manager';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- [Previous head content remains exactly the same] -->
    <!-- ... -->
     <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager | GIMS</title>
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

        /* Dashboard Cards */
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
            height: 100%;
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

        .card-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
        }

        .card-footer {
            font-size: 0.8rem;
            color: #6c757d;
            display: flex;
            align-items: center;
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

        /* Unique elements for manager dashboard */
        .highlight-card {
            background: linear-gradient(135deg, #004E89 0%, #0066b2 100%);
            color: white;
        }
        
        .highlight-card .card-title,
        .highlight-card .card-value,
        .highlight-card .card-footer {
            color: white !important;
        }
        
        .progress-thin {
            height: 6px;
            border-radius: 3px;
        }
        
        .cylinder-visual {
            width: 100%;
            height: 120px;
            position: relative;
            margin-bottom: 15px;
        }
        
        .cylinder {
            position: absolute;
            bottom: 0;
            width: 40px;
            background: var(--primary);
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
        }
        
        .cylinder-label {
            position: absolute;
            bottom: -25px;
            width: 100%;
            text-align: center;
            font-size: 0.8rem;
            color: var(--dark);
        }
        
        .cylinder:nth-child(1) {
            left: 10%;
            height: <?= min(100, ($totalStock/($totalStock+$emptyCylinders)*100)) ?>%;
            background: var(--success);
        }
        
        .cylinder:nth-child(2) {
            left: 40%;
            height: <?= min(100, ($emptyCylinders/($totalStock+$emptyCylinders)*100)) ?>%;
            background: var(--warning);
        }
        
        .cylinder:nth-child(3) {
            left: 70%;
            height: <?= min(100, ($todaySales/max(1,$monthlySales)*100)) ?>%;
            background: var(--info);
        }
    </style>
</head>
<body>
    <!-- Sidebar and top navigation remain exactly the same -->
    <!-- ... -->
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
            <a href="<?= BASE_URL ?>modules/dashboard/manager.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Stock Management -->
            <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="menu-item">
                <i class="fas fa-boxes"></i>
                <span>Stock Management</span>
            </a>
            
            <!-- Sales -->
            <a href="<?= BASE_URL ?>modules/sales/sales.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Record Sale</span>
            </a>
            
            <!-- Sales Management -->
            <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Sales Management</span>
            </a>

            <!-- Debts -->
            <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="menu-item">
                <i class="fas fa-list"></i>
                <span>Debt Management</span>
            </a>
            
            <!-- Empty Cylinders -->
            <a href="<?= BASE_URL ?>modules/inventory/empty_cylinders.php" class="menu-item">
                <i class="fas fa-gas-pump"></i>
                <span>Empty Cylinders</span>
            </a>
            
            <div style="padding: 20px 10px;">
                <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 10px 0;"></div>
            </div>
            
            <!-- Reports -->
            <a href="<?= BASE_URL ?>modules/reports/sales_report.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Sales Reports</span>
            </a>
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
            
            <div class="dropdown-menu" id="dropdownMenu" style="display: none; position: absolute; right: 20px; background: white; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); padding: 10px 0; min-width: 200px; z-index: 1000;">
                <a href="#" class="dropdown-item" style="display: block; padding: 8px 20px; color: var(--dark); text-decoration: none;">
                    <i class="fas fa-user-circle mr-2"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item" style="display: block; padding: 8px 20px; color: var(--danger); text-decoration: none;">
                    <i class="fas fa-sign-out-alt mr-2"></i>
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
                    <h2 class="mb-0">Manager Dashboard</h2>
                    <p class="text-muted">Welcome back, <?= htmlspecialchars($username) ?>! Here's your business overview.</p>
                </div>
            </div>
            
            <!-- Stats Cards - Updated to use correct tables -->
            <div class="row mb-4">
                <!-- Today's Sales -->
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card highlight-card" id="statCard1">
                        <div class="card-body">
                            <div class="card-icon" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h6 class="card-title">TODAY'S SALES</h6>
                            <h3 class="card-value"><?= number_format((float)$todaySales, 2) ?> Tsh</h3>
                            <div class="card-footer">
                                <i class="fas fa-calendar-day mr-2"></i>
                                <span><?= date('F j, Y') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Debt -->
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard2">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--danger);">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h6 class="card-title">TOTAL DEBT</h6>
                            <h3 class="card-value"><?= number_format((float)$totalDebt, 2) ?> Tsh</h3>
                            <div class="progress progress-thin mt-2">
                                <div class="progress-bar bg-danger" style="width: <?= min(100, ($monthlySales > 0 ? ($totalDebt/$monthlySales)*100 : 0)) ?>%"></div>
                            </div>
                            <div class="card-footer">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span>Pending payments</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Total Stock (Gas Cylinders) -->
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard3">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--success);">
                                <i class="fas fa-box-open"></i>
                            </div>
                            <h6 class="card-title">GAS CYLINDERS IN STOCK</h6>
                            <h3 class="card-value"><?= number_format((int)$totalStock) ?></h3>
                            <div class="card-footer">
                                <i class="fas fa-warehouse mr-2"></i>
                                <span>Available for sale</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Empty Cylinders -->
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard4">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--warning);">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <h6 class="card-title">EMPTY CYLINDERS</h6>
                            <h3 class="card-value"><?= number_format((int)$emptyCylinders) ?></h3>
                            <div class="card-footer">
                                <i class="fas fa-exchange-alt mr-2"></i>
                                <span>Need refilling</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visualization Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Inventory Overview</h5>
                            <div class="cylinder-visual">
                                <div class="cylinder" title="Gas Cylinders: <?= $totalStock ?>"></div>
                                <div class="cylinder-label">In Stock</div>
                                
                                <div class="cylinder" title="Empty Cylinders: <?= $emptyCylinders ?>"></div>
                                <div class="cylinder-label">Empty</div>
                                
                                <div class="cylinder" title="Today's Sales: <?= number_format($todaySales, 2) ?> Tsh"></div>
                                <div class="cylinder-label">Sales</div>
                            </div>
                            <div class="row text-center">
                                <div class="col-4">
                                    <small class="text-muted">Gas Cylinders</small>
                                    <h5><?= $totalStock ?></h5>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Empty Cylinders</small>
                                    <h5><?= $emptyCylinders ?></h5>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Today's Sales</small>
                                    <h5><?= number_format($todaySales, 2) ?> Tsh</h5>
                                </div>
                            </div>
                            
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="card-body">
                            <h5 class="card-title">Quick Actions</h5>
                            <div class="d-grid gap-2">
                                <a href="<?= BASE_URL ?>modules/sales/sales.php" class="btn btn-primary btn-block">
                                    <i class="fas fa-money-bill-wave mr-2"></i> Record Sale
                                </a>
                                <a href="<?= BASE_URL ?>modules/cylinders/manage.php" class="btn btn-warning btn-block">
                                    <i class="fas fa-gas-pump mr-2"></i> Manage Empty Cylinders
                                </a>
                                <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="btn btn-success btn-block">
                                    <i class="fas fa-plus-circle mr-2"></i> Add New Stock
                                </a>
                                <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="btn btn-danger btn-block">
                                    <i class="fas fa-hand-holding-usd mr-2"></i> View Debts
                                </a>
                                <a href="<?= BASE_URL ?>modules/reports/sales_report.php" class="btn btn-info btn-block">
                                    <i class="fas fa-file-alt mr-2"></i> Generate Report
                                </a>
                            </div>
                            
                            <!-- Recent Empty Cylinders -->
                            <div class="mt-4">
                                <h6>Recent Empty Cylinders</h6>
                                <ul class="list-group list-group-flush">
                                    <?php
                                    $stmtRecentEmpty = $pdo->prepare("SELECT brand, size, created_at FROM cylinders WHERE status = 'empty' ORDER BY created_at DESC LIMIT 5");
                                    $stmtRecentEmpty->execute();
                                    $recentEmpty = $stmtRecentEmpty->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($recentEmpty) > 0) {
                                        foreach ($recentEmpty as $empty) {
                                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                                            echo htmlspecialchars($empty['brand']).' '.htmlspecialchars($empty['size']);
                                            echo '<span class="badge bg-light text-dark">'.date('M j', strtotime($empty['created_at'])).'</span>';
                                            echo '</li>';
                                        }
                                    } else {
                                        echo '<li class="list-group-item text-center">No empty cylinders recorded</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            // Animate elements on load
            function animateElements() {
                // Animate stat cards
                const statCards = [
                    document.getElementById('statCard1'),
                    document.getElementById('statCard2'),
                    document.getElementById('statCard3'),
                    document.getElementById('statCard4')
                ];
                
                statCards.forEach((card, index) => {
                    setTimeout(() => {
                        card.classList.add('animated');
                    }, index * 150);
                });
            }
            
            // Simulate data changes (in a real app, this would come from AJAX)
            function simulateDataChanges() {
                const values = [
                    document.getElementById('todaySales'),
                    document.getElementById('totalDebt'),
                    document.getElementById('totalStock'),
                    document.getElementById('emptyCylinders')
                ];
                
                setInterval(() => {
                    values.forEach(value => {
                        // Add animation class
                        value.classList.add('value-change');
                        
                        // Remove animation class after animation completes
                        setTimeout(() => {
                            value.classList.remove('value-change');
                        }, 600);
                    });
                }, 5000);
            }
            
            // Initialize
            animateElements();
            simulateDataChanges();
            
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
    </script><!-- JavaScript remains exactly the same -->
    <!-- ... -->
</body>
</html>