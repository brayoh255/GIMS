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

// Initialize variables

// Initialize variables
$error = '';
$success = '';
$expenses = [];
$categories = ['Utilities', 'Salaries', 'Rent', 'Inventory', 'Maintenance', 'Other'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['description'])) {
    try {
        $description = trim($_POST['description']);
        $amount = (float)$_POST['amount'];
        $category = trim($_POST['category']);
        
        // Validation
        if (empty($description) || empty($category)) {
            throw new Exception("Description and category are required");
        }
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }

        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, category, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$description, $amount, $category, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            $success = "Expense recorded successfully!";
            $_POST = []; // Clear form
        } else {
            throw new Exception("Failed to record expense");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle filtering
$filter_category = $_GET['category'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_min_amount = $_GET['min_amount'] ?? '';
$filter_max_amount = $_GET['max_amount'] ?? '';

// Build query with filters
$query = "SELECT * FROM expenses WHERE 1=1";
$params = [];

if (!empty($filter_category)) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}
if (!empty($filter_date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $filter_date_to;
}
if (!empty($filter_min_amount)) {
    $query .= " AND amount >= ?";
    $params[] = $filter_min_amount;
}
if (!empty($filter_max_amount)) {
    $query .= " AND amount <= ?";
    $params[] = $filter_max_amount;
}
$query .= " ORDER BY created_at DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$username = $_SESSION['username'] ?? 'Admin';

// Handle export to Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="expenses_' . date('Y-m-d') . '.xls"');
    
    $output = "<table border='1'>";
    $output .= "<tr><th>Date</th><th>Description</th><th>Category</th><th>Amount (TZS)</th></tr>";
    
    foreach ($expenses as $e) {
        $output .= "<tr>";
        $output .= "<td>" . htmlspecialchars(date('Y-m-d', strtotime($e['created_at']))) . "</td>";
        $output .= "<td>" . htmlspecialchars($e['description']) . "</td>";
        $output .= "<td>" . htmlspecialchars($e['category']) . "</td>";
        $output .= "<td>" . number_format($e['amount'], 2) . "</td>";
        $output .= "</tr>";
    }
    
    $total = array_sum(array_column($expenses, 'amount'));
    $output .= "<tr><td colspan='3'><strong>Total</strong></td><td><strong>" . number_format($total, 2) . "</strong></td></tr>";
    $output .= "</table>";
    
    echo $output;
    exit;
}

// Handle export to PDF
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    $tcpdf_path = __DIR__ . '/../../libs/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        die("TCPDF library not found at: $tcpdf_path");
    }
    require_once $tcpdf_path;
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('GIMS');
    $pdf->SetAuthor('GIMS Admin');
    $pdf->SetTitle('Expenses Report');
    $pdf->SetSubject('Expenses Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Expenses Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    
    // Add filters info
    $filter_info = '';
    if (!empty($filter_date_from) || !empty($filter_date_to)) {
        $filter_info .= "Date Range: ";
        $filter_info .= !empty($filter_date_from) ? "From $filter_date_from " : "";
        $filter_info .= !empty($filter_date_to) ? "To $filter_date_to" : "";
        $filter_info .= "\n";
    }
    if (!empty($filter_category)) {
        $filter_info .= "Category: $filter_category\n";
    }
    if (!empty($filter_min_amount) || !empty($filter_max_amount)) {
        $filter_info .= "Amount Range: ";
        $filter_info .= !empty($filter_min_amount) ? "Min $filter_min_amount " : "";
        $filter_info .= !empty($filter_max_amount) ? "Max $filter_max_amount" : "";
    }
    
    if (!empty($filter_info)) {
        $pdf->MultiCell(0, 10, $filter_info, 0, 'L');
    }
    
    // Create table
    $pdf->SetFont('helvetica', '', 9);
    
    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.1);
    $pdf->SetFont('', 'B');
    
    $header = ['Date', 'Description', 'Category', 'Amount (TZS)'];
    $w = [30, 80, 40, 40];
    
    for ($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Table content
    $pdf->SetFont('');
    $fill = false;
    $total = 0;
    
    foreach ($expenses as $row) {
        $pdf->Cell($w[0], 6, date('Y-m-d', strtotime($row['created_at'])), 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $row['description'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $row['category'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[3], 6, number_format($row['amount'], 2), 'LR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
        $total += $row['amount'];
    }
    
    // Closing line
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln();
    
    // Total row
    $pdf->SetFont('', 'B');
    $pdf->Cell($w[0] + $w[1] + $w[2], 7, 'Total:', 1, 0, 'R');
    $pdf->Cell($w[3], 7, number_format($total, 2), 1, 0, 'R');
    
    // Footer
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
    $pdf->Ln();
    $pdf->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    
    // Output PDF
    $pdf->Output('expenses_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses | GIMS</title>
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
            transition: all 0.3s ease;
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        /* Content Sections */
        .form-section, .filter-section, .table-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .form-section.animated, 
        .filter-section.animated, 
        .table-section.animated {
            opacity: 1;
            transform: translateY(0);
        }

        .filter-section {
            transition-delay: 0.2s;
        }

        .table-section {
            transition-delay: 0.4s;
        }

        /* Table Styles */
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

        /* Button Styles */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: #e05a2a;
            border-color: #e05a2a;
        }

        .btn-outline-secondary:hover {
            color: white;
            background-color: var(--primary);
            border-color: var(--primary);
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

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 10px 0;
            z-index: 1000;
            min-width: 180px;
            animation: fadeIn 0.3s ease;
        }

        .dropdown-item {
            display: block;
            padding: 8px 15px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-open .main-content {
                margin-left: 280px;
            }
            .top-nav {
                left: 0;
            }
            .sidebar-open .top-nav {
                left: 280px;
            }
        }

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
            <a href="<?= BASE_URL ?>modules/dashboard/admin.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="menu-item">
                <i class="fas fa-boxes"></i>
                <span>Stock</span>
            </a>

            <a href="<?= BASE_URL ?>modules/sales/sales.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Record Sale</span>
            </a>

            <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Sales Management</span>
            </a>

            <a href="<?= BASE_URL ?>modules/expenses/expense.php" class="menu-item active">
                <i class="fas fa-plus-circle"></i>
                <span>Expenses</span>
            </a>
            
            <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="menu-item">
                <i class="fas fa-list"></i>
                <span>Debts</span>
            </a>

            <a href="<?= BASE_URL ?>modules/admin/manage.php" class="menu-item">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
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
            
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="#" class="dropdown-item">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <div style="height: 1px; background: #eee; margin: 5px 0;"></div>
                <a href="<?= BASE_URL ?>modules/auth/logout.php" class="dropdown-item">
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
                    <h4 class="mb-0">Expense Management</h4>
                    <p class="text-muted">Record and manage your business expenses</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger animated fadeIn"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success animated fadeIn"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Expense Form -->
            <div class="form-section" id="formSection">
                <h5 class="mb-4">Record New Expense</h5>
                <form method="post">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" name="description" class="form-control" placeholder="Description" 
                                   value="<?= htmlspecialchars($_POST['description'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" 
                                   value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                                        <?= $cat ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="fas fa-plus-circle me-2"></i>Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Filters -->
            <div class="filter-section" id="filterSection">
                <h5 class="mb-4">Filter Expenses</h5>
                <form method="get">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="form-control" placeholder="From Date">
                        </div>
                        <div class="col-md-3">
                            <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="form-control" placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.01" name="min_amount" value="<?= htmlspecialchars($filter_min_amount) ?>" class="form-control" placeholder="Min Amount">
                        </div>
                        <div class="col-md-2">
                            <input type="number" step="0.01" name="max_amount" value="<?= htmlspecialchars($filter_max_amount) ?>" class="form-control" placeholder="Max Amount">
                        </div>
                        <div class="col-md-2">
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= ($filter_category === $cat) ? 'selected' : '' ?>>
                                        <?= $cat ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-secondary me-2">
                                <i class="fas fa-filter me-2"></i>Apply Filter
                            </button>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-sync-alt me-2"></i>Reset
                            </a>
                        </div>
                        <div>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?export=excel&<?= http_build_query($_GET) ?>" 
                               class="btn btn-success me-2">
                                <i class="fas fa-file-excel me-2"></i>Export Excel
                            </a>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?export=pdf&<?= http_build_query($_GET) ?>" 
                               class="btn btn-danger">
                                <i class="fas fa-file-pdf me-2"></i>Export PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Expenses Table -->
            <div class="table-section" id="tableSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Expenses List</h5>
                    <div class="text-muted">
                        Total Expenses: <span class="fw-bold"><?= number_format(array_sum(array_column($expenses, 'amount')), 2) ?> TZS</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th class="text-end">Amount (TZS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No expenses found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($exp['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($exp['description']) ?></td>
                                        <td><?= htmlspecialchars($exp['category']) ?></td>
                                        <td class="text-end"><?= number_format($exp['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold" style="background-color: rgba(0, 78, 137, 0.05);">
                                <td colspan="3">Total</td>
                                <td class="text-end"><?= number_format(array_sum(array_column($expenses, 'amount')), 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
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
                const sections = [
                    document.getElementById('formSection'),
                    document.getElementById('filterSection'),
                    document.getElementById('tableSection')
                ];
                
                sections.forEach((section, index) => {
                    setTimeout(() => {
                        section.classList.add('animated');
                    }, index * 200);
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
            handleResize();
        });
    </script>
</body>
</html>