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

// Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// Dashboard logic with proper SQL syntax and error handling
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'] ?? 0;
    $totalStock = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) AS total FROM cylinder_types")->fetch()['total'] ?? 0;
    
    // Using prepared statements for security
    $salesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $salesStmt->execute([$currentMonth, $currentYear]);
    $totalSales = $salesStmt->fetch()['total'] ?? 0;
    
    $expensesStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE MONTH(expense_date) = ? AND YEAR(expense_date) = ?");
    $expensesStmt->execute([$currentMonth, $currentYear]);
    $totalExpenses = $expensesStmt->fetch()['total'] ?? 0;
    
    $debtsStmt = $pdo->prepare("SELECT COALESCE(SUM(balance), 0) AS total FROM debts WHERE balance > 0 AND MONTH(record_date) = ? AND YEAR(record_date) = ?");
    $debtsStmt->execute([$currentMonth, $currentYear]);
    $totalDebts = $debtsStmt->fetch()['total'] ?? 0;

    $profit = $totalSales - $totalExpenses;
    $profitMargin = $totalSales > 0 ? ($profit / $totalSales) * 100 : 0;
} catch (PDOException $e) {
    // Log error and set defaults
    error_log("Database error: " . $e->getMessage());
    $totalUsers = $totalStock = $totalSales = $totalExpenses = $totalDebts = $profit = $profitMargin = 0;
}

$username = $_SESSION['username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | GIMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
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
            background: linear-gradient(135deg, var(--dark), #2a2a2a);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            background: rgba(255,255,255,0.2);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-gas-pump logo-icon"></i>
                <div>
                    <h3 style="margin: 0; font-weight: 700;">GIMS</h3>
                    <small style="opacity: 0.8;">Fuel Management</small>
                </div>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <!-- Dashboard -->
            <a href="<?= BASE_URL ?>modules/dashboard/admin.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Stock Management -->
            <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="menu-item">
                <i class="fas fa-boxes"></i>
                <span>View Stock</span>
            </a>
            <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add Stock</span>
            </a>
            <a href="<?= BASE_URL ?>modules/inventory/update_stock.php" class="menu-item">
                <i class="fas fa-edit"></i>
                <span>Update Stock</span>
            </a>
            
            <!-- Sales -->
            <a href="<?= BASE_URL ?>modules/sales/record_sale.php" class="menu-item">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Record Sale</span>
            </a>
            <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="menu-item">
                <i class="fas fa-list-ol"></i>
                <span>View Sales</span>
            </a>
            <a href="<?= BASE_URL ?>modules/sales/sales_report.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Sales Report</span>
            </a>
            
            <!-- Expenses -->
            <a href="<?= BASE_URL ?>modules/expenses/record_expense.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Add Expense</span>
            </a>
            <a href="<?= BASE_URL ?>modules/expenses/view_expenses.php" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>View Expenses</span>
            </a>
            <a href="<?= BASE_URL ?>modules/expenses/expense_report.php" class="menu-item">
                <i class="fas fa-chart-pie"></i>
                <span>Expense Report</span>
            </a>
            
            <!-- Debts -->
            <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="menu-item">
                <i class="fas fa-list"></i>
                <span>View Debts</span>
            </a>
            <a href="<?= BASE_URL ?>modules/debts/record_payment.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Record Payment</span>
            </a>
            <a href="<?= BASE_URL ?>modules/debts/debtors_report.php" class="menu-item">
                <i class="fas fa-file-invoice"></i>
                <span>Debt Report</span>
            </a>
            
            <!-- Reports -->
            <a href="<?= BASE_URL ?>modules/reports/daily_report.php" class="menu-item">
                <i class="fas fa-calendar-day"></i>
                <span>Daily Report</span>
            </a>
            <a href="<?= BASE_URL ?>modules/reports/inventory_report.php" class="menu-item">
                <i class="fas fa-boxes"></i>
                <span>Inventory Report</span>
            </a>
            <a href="<?= BASE_URL ?>modules/reports/financial_report.php" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Financial Report</span>
            </a>
            
            <!-- Customers -->
            <a href="<?= BASE_URL ?>modules/customers/add_customer.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Add Customer</span>
            </a>
            <a href="<?= BASE_URL ?>modules/customers/view_customers.php" class="menu-item">
                <i class="fas fa-address-book"></i>
                <span>View Customers</span>
            </a>
            <a href="<?= BASE_URL ?>modules/customers/customer_debts.php" class="menu-item">
                <i class="fas fa-file-invoice"></i>
                <span>Customer Debts</span>
            </a>
            
            <div style="padding: 20px 10px;">
                <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 10px 0;"></div>
            </div>
            
            <a href="<?= BASE_URL ?>modules/settings/index.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <a href="<?= BASE_URL ?>modules/auth/logout.php" class="menu-item" style="color: #ff6b6b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
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
            <button class="btn btn-outline-secondary" id="userDropdown" style="display: flex; align-items: center;">
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
                <a href="#" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    Settings
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
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-0">Dashboard Overview</h2>
                    <p class="text-muted">Welcome back, <?= htmlspecialchars($username) ?>! Here's what's happening with your business today.</p>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard1">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--primary);">
                                <i class="fas fa-users"></i>
                            </div>
                            <h6 class="card-title">TOTAL USERS</h6>
                            <h3 class="card-value" id="totalUsers"><?= $totalUsers ?></h3>
                            <div class="card-footer">
                                <i class="fas fa-calendar-day mr-2"></i>
                                <span>Updated today</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard2">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--info);">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <h6 class="card-title">CYLINDERS IN STOCK</h6>
                            <h3 class="card-value" id="totalStock"><?= $totalStock ?></h3>
                            <div class="card-footer">
                                <i class="fas fa-warehouse mr-2"></i>
                                <span>Current inventory</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard3">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--success);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h6 class="card-title">TOTAL SALES</h6>
                            <h3 class="card-value" id="totalSales"><?= number_format($totalSales, 2) ?> TZS</h3>
                            <div class="card-footer">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>This month</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card" id="statCard4">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--warning);">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h6 class="card-title">TOTAL DEBTS</h6>
                            <h3 class="card-value" id="totalDebts"><?= number_format($totalDebts, 2) ?> TZS</h3>
                            <div class="card-footer">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span>Pending payments</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Stats -->
            <div class="row">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--danger);">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <h6 class="card-title">TOTAL EXPENSES</h6>
                            <h3 class="card-value"><?= number_format($totalExpenses, 2) ?> TZS</h3>
                            <div class="card-footer">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>This month</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="card-body">
                            <div class="card-icon" style="background: var(--secondary);">
                                <i class="fas fa-coins"></i>
                            </div>
                            <h6 class="card-title">PROFIT MARGIN</h6>
                            <h3 class="card-value"><?= number_format($profitMargin, 2) ?>%</h3>
                            <div class="card-footer">
                                <i class="fas fa-percentage mr-2"></i>
                                <span>This month</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="stat-card">
                        <div class="card-body">
                            <h5 class="mb-4">Quick Actions</h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="<?= BASE_URL ?>modules/sales/record_sale.php" class="btn btn-primary btn-block py-3" style="border-radius: 10px;">
                                        <i class="fas fa-plus mr-2"></i>
                                        New Sale
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="btn btn-success btn-block py-3" style="border-radius: 10px;">
                                        <i class="fas fa-box-open mr-2"></i>
                                        Add Stock
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="<?= BASE_URL ?>modules/expenses/record_expense.php" class="btn btn-warning btn-block py-3" style="border-radius: 10px;">
                                        <i class="fas fa-receipt mr-2"></i>
                                        Record Expense
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="<?= BASE_URL ?>modules/debts/record_payment.php" class="btn btn-info btn-block py-3" style="border-radius: 10px;">
                                        <i class="fas fa-money-bill-wave mr-2"></i>
                                        Record Payment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                    document.getElementById('totalUsers'),
                    document.getElementById('totalStock'),
                    document.getElementById('totalSales'),
                    document.getElementById('totalDebts')
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
    </script>
</body>
</html>