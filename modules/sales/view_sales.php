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

// Define constants if not already defined
defined('APP_NAME') or define('APP_NAME', 'GIMS');
defined('BASE_URL') or define('BASE_URL', 'http://localhost/gims/');

// Initialize filters
$filters = [
    'customer' => $_GET['customer'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'min_amount' => $_GET['min_amount'] ?? '',
    'max_amount' => $_GET['max_amount'] ?? '',
    'status' => $_GET['status'] ?? 'all'
];

// Pagination
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Initialize variables
$sales = [];
$summary = ['total_revenue' => 0, 'total_paid' => 0, 'total_balance' => 0];
$total_sales = 0;
$total_pages = 1;
$username = $_SESSION['username'] ?? 'Admin';

try {
    // Base query
    $query = "SELECT s.* FROM sales s WHERE 1=1";
    $count_query = "SELECT COUNT(*) FROM sales s WHERE 1=1";
    $params = [];
    $count_params = [];

    // Apply filters
    if (!empty($filters['customer'])) {
        $query .= " AND (s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
        $count_query .= " AND (s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
        $customer_search = "%{$filters['customer']}%";
        array_push($params, $customer_search, $customer_search);
        array_push($count_params, $customer_search, $customer_search);
    }

    if (!empty($filters['date_from'])) {
        $query .= " AND DATE(s.created_at) >= ?";
        $count_query .= " AND DATE(s.created_at) >= ?";
        $params[] = $filters['date_from'];
        $count_params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $query .= " AND DATE(s.created_at) <= ?";
        $count_query .= " AND DATE(s.created_at) <= ?";
        $params[] = $filters['date_to'];
        $count_params[] = $filters['date_to'];
    }

    if (!empty($filters['min_amount'])) {
        $query .= " AND s.total_amount >= ?";
        $count_query .= " AND s.total_amount >= ?";
        $params[] = $filters['min_amount'];
        $count_params[] = $filters['min_amount'];
    }

    if (!empty($filters['max_amount'])) {
        $query .= " AND s.total_amount <= ?";
        $count_query .= " AND s.total_amount <= ?";
        $params[] = $filters['max_amount'];
        $count_params[] = $filters['max_amount'];
    }

    if ($filters['status'] !== 'all') {
        if ($filters['status'] === 'paid') {
            $query .= " AND s.amount_paid >= s.total_amount";
            $count_query .= " AND s.amount_paid >= s.total_amount";
        } elseif ($filters['status'] === 'pending') {
            $query .= " AND s.amount_paid < s.total_amount";
            $count_query .= " AND s.amount_paid < s.total_amount";
        }
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_sales = $stmt->fetchColumn();
    $total_pages = ceil($total_sales / $per_page);

    // Get paginated results
    $query .= " ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);

    // Bind positional parameters
    $i = 1;
    foreach ($params as $value) {
        $stmt->bindValue($i++, $value);
    }
    $stmt->bindValue($i++, $per_page, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary stats with the same filters
    $summary_query = "SELECT 
        COALESCE(SUM(s.total_amount), 0) as total_revenue,
        COALESCE(SUM(s.amount_paid), 0) as total_paid,
        COALESCE(SUM(GREATEST(s.total_amount - s.amount_paid, 0)), 0) as total_balance
        FROM sales s WHERE 1=1";
    
    $summary_params = [];
    
    if (!empty($filters['customer'])) {
        $summary_query .= " AND (s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
        $summary_params[] = $customer_search;
        $summary_params[] = $customer_search;
    }
    
    if (!empty($filters['date_from'])) {
        $summary_query .= " AND DATE(s.created_at) >= ?";
        $summary_params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $summary_query .= " AND DATE(s.created_at) <= ?";
        $summary_params[] = $filters['date_to'];
    }
    
    if (!empty($filters['min_amount'])) {
        $summary_query .= " AND s.total_amount >= ?";
        $summary_params[] = $filters['min_amount'];
    }
    
    if (!empty($filters['max_amount'])) {
        $summary_query .= " AND s.total_amount <= ?";
        $summary_params[] = $filters['max_amount'];
    }
    
    if ($filters['status'] !== 'all') {
        if ($filters['status'] === 'paid') {
            $summary_query .= " AND s.amount_paid >= s.total_amount";
        } elseif ($filters['status'] === 'pending') {
            $summary_query .= " AND s.amount_paid < s.total_amount";
        }
    }
    
    $summary_stmt = $pdo->prepare($summary_query);
    $summary_stmt->execute($summary_params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Database error in admin.php: " . $e->getMessage());
}

// Handle PDF export
if (isset($_GET['export_pdf'])) {
    require_once __DIR__ . '/../../libs/tcpdf/tcpdf.php';

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GIMS');
    $pdf->SetAuthor('GIMS');
    $pdf->SetTitle('Sales Report');
    $pdf->SetSubject('Sales Report');
    $pdf->SetKeywords('Sales, Report, GIMS');

    $pdf->setHeaderData('', 0, 'Sales Report', date('Y-m-d H:i:s'));
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    $pdf->SetDefaultMonospacedFont('courier');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 25);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sales Report', 0, 1, 'C');
    $pdf->Ln(5);

    // Filters info
    $pdf->SetFont('helvetica', '', 10);
    $filter_info = 'Filters: ';
    $filter_items = [];
    if (!empty($filters['customer'])) $filter_items[] = 'Customer: ' . $filters['customer'];
    if (!empty($filters['date_from'])) $filter_items[] = 'From: ' . $filters['date_from'];
    if (!empty($filters['date_to'])) $filter_items[] = 'To: ' . $filters['date_to'];
    if (!empty($filters['min_amount'])) $filter_items[] = 'Min Amount: ' . $filters['min_amount'];
    if (!empty($filters['max_amount'])) $filter_items[] = 'Max Amount: ' . $filters['max_amount'];
    if ($filters['status'] !== 'all') $filter_items[] = 'Status: ' . ucfirst($filters['status']);

    $filter_info .= implode(', ', $filter_items);
    $pdf->Cell(0, 0, $filter_info, 0, 1);
    $pdf->Ln(10);

    // Summary based on the same filters
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Summary Statistics', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(60, 6, 'Total Revenue:', 0, 0);
    $pdf->Cell(30, 6, number_format($summary['total_revenue'], 2), 0, 1, 'R');
    
    $pdf->Cell(60, 6, 'Total Paid:', 0, 0);
    $pdf->Cell(30, 6, number_format($summary['total_paid'], 2), 0, 1, 'R');
    
    $pdf->Cell(60, 6, 'Total Balance:', 0, 0);
    $pdf->Cell(30, 6, number_format($summary['total_balance'], 2), 0, 1, 'R');
    
    $pdf->Ln(10);

    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(15, 7, 'ID', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Date', 1, 0, 'C', 1);
    $pdf->Cell(50, 7, 'Customer', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Total', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Paid', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Balance', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Status', 1, 1, 'C', 1);

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    foreach ($sales as $sale) {
        $balance = $sale['total_amount'] - $sale['amount_paid'];
        $status = ($sale['amount_paid'] >= $sale['total_amount']) ? 'Paid' : 'Pending';

        $pdf->Cell(15, 6, $sale['id'], 'LR', 0, 'L');
        $pdf->Cell(25, 6, date('M j, Y', strtotime($sale['created_at'])), 'LR', 0, 'L');
        $pdf->Cell(50, 6, $sale['customer_name'], 'LR', 0, 'L');
        $pdf->Cell(25, 6, number_format($sale['total_amount'], 2), 'LR', 0, 'R');
        $pdf->Cell(25, 6, number_format($sale['amount_paid'], 2), 'LR', 0, 'R');
        $pdf->Cell(25, 6, number_format(max(0, $balance), 2), 'LR', 0, 'R');
        $pdf->Cell(25, 6, $status, 'LR', 1, 'C');
    }

    // Footer
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(190, 6, 'Total Records: ' . count($sales), 'LTB', 1, 'R');

    $pdf->Output('sales_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// HTML template remains the same as in your original code
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales | <?= htmlspecialchars(APP_NAME) ?></title>
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
            background: rgba(255, 255, 255, 0.54);
        }

        /* Sales Table Styles */
        .table-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease 0.4s;
        }

        .table-section.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            font-weight: 600;
        }

        .table td, .table th {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: 1px solid #dee2e6;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 78, 137, 0.03);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 78, 137, 0.08);
        }

        .badge-paid {
            background-color: var(--success);
            color: white;
        }

        .badge-pending {
            background-color: var(--warning);
            color: var(--dark);
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease 0.2s;
        }

        .filter-section.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Summary Cards */
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .summary-card.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .summary-value {
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-export-excel {
            background-color: #1d6f42;
            color: white;
        }

        .btn-export-excel:hover {
            background-color: #165a34;
            color: white;
        }

        .btn-export-pdf {
            background-color: #d23333;
            color: white;
        }

        .btn-export-pdf:hover {
            background-color: #b32b2b;
            color: white;
        }

        /* Modal for New Sale */
        .modal-content {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .modal-header {
            background-color: var(--primary);
            color: white;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table td, .table th {
                padding: 8px 10px;
            }
            
            .form-control, .form-select {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar, top navigation, and main content structure remains the same -->
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
            
            <div class="dropdown-menu" id="dropdownMenu" style="display: none; position: absolute; right: 20px; background: white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; padding: 10px 0; z-index: 1000; min-width: 180px;">
                <a href="#" class="dropdown-item" style="display: block; padding: 8px 15px; color: var(--dark); text-decoration: none; transition: all 0.2s;">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item" style="display: block; padding: 8px 15px; color: var(--danger); text-decoration: none; transition: all 0.2s;">
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
                    <h2 class="mb-0">Sales Management</h2>
                    <p class="text-muted">View and manage all sales records</p>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger animated fadeIn"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section" id="filterSection">
                <h5 class="mb-4"><i class="fas fa-filter me-2"></i> Filter Sales</h5>
                <form method="get">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Customer</label>
                            <input type="text" name="customer" class="form-control" 
                                   value="<?= htmlspecialchars($filters['customer']) ?>" 
                                   placeholder="Name or phone">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Min Amount</label>
                            <input type="number" step="0.01" name="min_amount" class="form-control" 
                                   value="<?= htmlspecialchars($filters['min_amount']) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Max Amount</label>
                            <input type="number" step="0.01" name="max_amount" class="form-control" 
                                   value="<?= htmlspecialchars($filters['max_amount']) ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                            <a href="view_sales.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt me-2"></i> Reset
                            </a>
                        </div>
                        <div class="export-buttons">
                        <!--    <a href="export_sales.php?<?= http_build_query($_GET) ?>" class="btn btn-export-excel">
                                <i class="fas fa-file-excel me-2"></i> Export Excel
                            </a>-->
                            <a href="view_sales.php?<?= http_build_query($_GET) ?>&export_pdf=1" class="btn btn-export-pdf">
                                <i class="fas fa-file-pdf me-2"></i> Export PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
    
    <!-- Only change the summary cards section to use the filtered summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="summary-card" id="revenueCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted">TOTAL SALES</h6>
                            <h3 class="card-value text-primary"><?= number_format($summary['total_revenue'], 2) ?> Tsh</h3>
                        </div>
                        <div class="card-icon" style="background: var(--primary);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="summary-card" id="paidCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted">TOTAL PAID</h6>
                            <h3 class="card-value text-success"><?= number_format($summary['total_paid'], 2) ?> Tsh</h3>
                        </div>
                        <div class="card-icon" style="background: var(--success);">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="summary-card" id="balanceCard">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title text-muted">TOTAL BALANCE</h6>
                            <h3 class="card-value text-danger"><?= number_format($summary['total_balance'], 2) ?> Tsh</h3>
                        </div>
                        <div class="card-icon" style="background: var(--danger);">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rest of your HTML remains exactly the same -->
     
            <!-- Sales Table -->
            <div class="table-section" id="tableSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i> Sales Records</h5>
                   
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
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
                                <td><?= date('M j, Y', strtotime($sale['created_at'])) ?></td>
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
                                      
                                        <a href="print_receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">No sales found matching your criteria</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination mt-3 justify-content-center">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
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
            
            // Animate elements on load
            function animateElements() {
                // Animate sections
                const sections = [
                    document.getElementById('filterSection'),
                    document.getElementById('revenueCard'),
                    document.getElementById('paidCard'),
                    document.getElementById('balanceCard'),
                    document.getElementById('tableSection')
                ];
                
                sections.forEach((section, index) => {
                    if (section) {
                        setTimeout(() => {
                            section.classList.add('animated');
                        }, index * 150);
                    }
                });
            }
            
            // Initialize animations
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

            // Handle modal close event to refresh page if needed
            const newSaleModal = document.getElementById('newSaleModal');
            if (newSaleModal) {
                newSaleModal.addEventListener('hidden.bs.modal', function () {
                    // You can add logic here to refresh the sales data if needed
                    // window.location.reload();
                });
            }
        });

        // Add this to your existing JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // ... (your existing JavaScript code)
            
            // Add AJAX form submission for dynamic updates
            const filterForm = document.querySelector('form');
            
            filterForm.addEventListener('submit', function(e) {
                // Show loading state on summary cards
                const cards = document.querySelectorAll('.summary-card');
                cards.forEach(card => {
                    card.querySelector('.card-value').textContent = 'Loading...';
                    card.classList.remove('animated');
                });
                
                // Show loading state on table
                const tableSection = document.getElementById('tableSection');
                tableSection.classList.remove('animated');
                tableSection.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i> Loading data...</div>';
            });
        });
    </script>
</body>
</html>