<?php

// modules/dashboard/sales.php

// Load configuration and dependencies
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_SALES);

$debtors = [];
$debts = [];
$debt = null;
$payments = [];

try {
    // Only show debts with balance > 0
    $stmt = $pdo->query("SELECT d.*, s.total_amount, c.phone 
                        FROM debts d
                        JOIN sales s ON d.sale_id = s.id
                        LEFT JOIN customers c ON d.customer_name = c.name
                        WHERE d.balance > 0
                        ORDER BY d.created_at DESC");
    $debts = $stmt->fetchAll();

    // Debtors Summary
    $stmt = $pdo->query("SELECT d.customer_name, 
                        SUM(d.amount) as total_debt,
                        SUM(d.paid_amount) as total_paid,
                        SUM(d.balance) as total_balance
                        FROM debts d
                        GROUP BY d.customer_name
                        HAVING total_balance > 0
                        ORDER BY total_balance DESC");
    $debtors = $stmt->fetchAll();

    // If a specific debt is selected
    if (isset($_GET['record_payment_id'])) {
        $stmt = $pdo->prepare("SELECT d.*, s.total_amount 
                              FROM debts d
                              JOIN sales s ON d.sale_id = s.id
                              WHERE d.id = ?");
        $stmt->execute([$_GET['record_payment_id']]);
        $debt = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT dp.*, u.username AS recorded_by 
                              FROM debt_payments dp
                              LEFT JOIN users u ON dp.recorded_by = u.id
                              WHERE dp.debt_id = ? ORDER BY dp.payment_date");
        $stmt->execute([$_GET['record_payment_id']]);
        $payments = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

$username = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debts Management | GIMS</title>
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

        /* Debt specific styles */
        .debt-card {
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }

        .debt-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .debt-high {
            border-right-color: var(--danger);
        }

        .debt-medium {
            border-left-color: var(--warning);
        }

        .debt-low {
            border-left-color: var(--success);
        }

        .table-responsive {
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255, 107, 53, 0.1);
        }

        .badge-debt {
            background-color: var(--danger);
            color: white;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 20px;
        }

        /* Modal styles */
        .payment-modal {
            background-color: rgba(0,0,0,0.5);
            z-index: 1050;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .modal-header {
            background-color: var(--primary);
            color: white;
            border-radius: 12px 12px 0 0 !important;
        }

        .btn-close {
            filter: invert(1);
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

        /* Tab styles */
        .nav-tabs .nav-link {
            color: var(--dark);
            font-weight: 500;
            border: none;
            padding: 12px 20px;
        }

        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: transparent;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            color: var(--primary);
        }

        .tab-content {
            animation: fadeIn 0.5s ease;
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
            <a href="<?= BASE_URL ?>modules/dashboard/sales.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="<?= BASE_URL ?>modules/sales/sales_record.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Record Sale</span>
            </a>
            
            <a href="<?= BASE_URL ?>modules/sales/sales_view.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Sales History</span>
            </a>
            
            <a href="<?= BASE_URL ?>modules/debts/sales.php" class="menu-item active">
                <i class="fas fa-hand-holding-usd"></i>
                <span>Customer Debts</span>
            </a>
            
            <div style="padding: 20px 10px;">
                <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 10px 0;"></div>
            </div>
            
            <a href="<?= BASE_URL ?>modules/auth/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
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
       
                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                  
                </div>
                
        
            
            <div class="dropdown-menu" id="dropdownMenu" style="display: none; position: absolute; right: 0; background: white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; padding: 10px; min-width: 200px; z-index: 1000;">
                <a href="#" class="dropdown-item" style="display: block; padding: 8px 15px; color: var(--dark); text-decoration: none; border-radius: 5px;">
                    <i class="fas fa-user-circle mr-2"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item" style="display: block; padding: 8px 15px; color: var(--danger); text-decoration: none; border-radius: 5px;">
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
                    <h4 class="mb-0">Debts </h4>
                    <p class="text-muted">Track and manage customer debts and payments</p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px;">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px;">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card animated" id="statCard1">
                        <div class="card-body">
                            <div class="card-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <h6 class="card-title">ACTIVE DEBTS</h6>
                            <h3 class="card-value" id="totalDebts"><?= count($debts) ?></h3>
                            <div class="card-footer">
                                <i class="fas fa-calendar-day mr-2"></i>
                                <span>Current open debts</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card animated" id="statCard2">
                        <div class="card-body">
                            <div class="card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h6 class="card-title">DEBTORS</h6>
                            <h3 class="card-value"><?= count($debtors) ?></h3>
                            <div class="card-footer">
                          
                                <span>Customers with debts</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card animated" id="statCard3">
                        <div class="card-body">
                            <div class="card-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <h6 class="card-title">TOTAL OWED</h6>
                            <h3 class="card-value">
                                <?= number_format(array_sum(array_column($debtors, 'total_balance')), 2) ?> Tsh
                            </h3>
                            <div class="card-footer">
                             
                                <span>Outstanding</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card animated" id="statCard4">
                        <div class="card-body">
                            <div class="card-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h6 class="card-title">TOTAL RECOVERED</h6>
                            <h3 class="card-value">
                                <?= number_format(array_sum(array_column($debtors, 'total_paid')), 2) ?> Tsh
                            </h3>
                            <div class="card-footer">
                              
                                <span>Amount paid</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs" id="debtTabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#debts">Debts List</a></li>
               
            </ul>

            <div class="tab-content mt-3">
                <!-- Tab 1: Debts List -->
                <div class="tab-pane fade show active" id="debts">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    
                                    <th>Original Amount</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debts as $row): 
                                    $debtPercentage = ($row['balance'] / $row['amount']) * 100;
                                    $debtClass = '';
                                    if ($debtPercentage > 75) $debtClass = 'debt-high';
                                    elseif ($debtPercentage > 50) $debtClass = 'debt-medium';
                                    else $debtClass = 'debt-low';
                                ?>
                                    <tr class="<?= $debtClass ?>">
                                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                        <td><?= number_format($row['amount'], 2) ?></td>
                                        <td><?= number_format($row['paid_amount'], 2) ?></td>
                                        <td class="fw-bold"><?= number_format($row['balance'], 2) ?></td>
                                      
                                        <td>
                                            <a href="?record_payment_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-money-bill-wave mr-1"></i> Record Payment
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Debtors Summary -->
                <div class="tab-pane fade" id="summary">
                    <div class="mb-3">
                        <a href="debtors_report.php?export=pdf" class="btn btn-outline-primary">
                            <i class="fas fa-file-pdf mr-1"></i> Download PDF Report
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Debt</th>
                                    <th>Total Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debtors as $debtor): 
                                    $debtPercentage = ($debtor['total_balance'] / $debtor['total_debt']) * 100;
                                    $statusClass = $debtPercentage > 75 ? 'bg-danger' : ($debtPercentage > 50 ? 'bg-warning' : 'bg-success');
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($debtor['customer_name']) ?></td>
                                        <td><?= number_format($debtor['total_debt'], 2) ?></td>
                                        <td><?= number_format($debtor['total_paid'], 2) ?></td>
                                        <td class="fw-bold"><?= number_format($debtor['total_balance'], 2) ?></td>
                                        <td>
                                            <span class="badge <?= $statusClass ?> rounded-pill">
                                                <?= number_format($debtPercentage, 1) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div> 
                </div>
            </div>

            <!-- Payment Modal (Dynamic from GET) -->
        <?php if ($debt): ?>
        <div class="modal fade show d-block payment-modal" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="sales_record.php" class="modal-content border-0 ">
            <input type="hidden" name="debt_id" value="<?= $debt['id'] ?>">
            <div class="modal-header" style="background-color: var(--primary);">
                <h5 class="modal-title">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Record Payment for <?= htmlspecialchars($debt['customer_name']) ?>
                </h5>
                <a href="sales.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <div class="row text-center mb-4">
                    <div class="col-md-4">
                        <div class="card bg-light border-primary">
                            <div class="card-body">
                                <h6 class="text-muted">Original Amount</h6>
                                <h4 class="text-primary fw-bold">
                                    <?= number_format($debt['amount'], 2) ?> Tsh
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-success">
                            <div class="card-body">
                                <h6 class="text-muted">Amount Paid</h6>
                                <h4 class="text-success fw-bold">
                                    <?= number_format($debt['paid_amount'], 2) ?> Tsh
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light border-danger">
                            <div class="card-body">
                                <h6 class="text-muted">Balance</h6>
                                <h4 class="text-danger fw-bold">
                                    <?= number_format($debt['balance'], 2) ?> Tsh
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
           

                            <div class="mb-3">
                                <label class="form-label">Payment Amount (Tsh)</label>
                                <input type="number" name="payment_amount" class="form-control" 
                                       min="1" max="<?= $debt['balance'] ?>" required 
                                       placeholder="Enter payment amount">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                    
                       
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check-circle mr-1"></i> Submit Payment
                            </button>
                            <a href="sales.php" class="btn btn-secondary">
                                <i class="fas fa-times-circle mr-1"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
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
                
                // Animate table rows
                const tableRows = document.querySelectorAll('tbody tr');
                tableRows.forEach((row, index) => {
                    setTimeout(() => {
                        row.style.opacity = '0';
                        row.style.transform = 'translateY(20px)';
                        row.style.animation = `slideIn 0.5s ease forwards ${index * 0.1}s`;
                    }, 0);
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
            
            // Tab functionality
            const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('href');
                    
                    // Remove active class from all tabs and links
                    document.querySelectorAll('.nav-tabs .nav-link').forEach(el => {
                        el.classList.remove('active');
                    });
                    document.querySelectorAll('.tab-pane').forEach(el => {
                        el.classList.remove('show', 'active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    document.querySelector(tabId).classList.add('show', 'active');
                });
            });
        });
    </script>
</body>
</html>