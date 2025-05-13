<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}
checkRole(ROLE_ADMIN); // Only admin can access this page

$errors = [];
$success = '';
$username = $_SESSION['username'] ?? 'Admin';

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [Previous form handling code remains the same]
    // Add user
    if (isset($_POST['add_user'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];

        if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
            $errors[] = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        } elseif (!in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_SALES])) {
            $errors[] = "Invalid role selected.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email already registered.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$full_name, $email, $hashed_password, $role])) {
                    $_SESSION['success'] = "User added successfully.";
                    header("Location: manage.php");
                    exit;
                } else {
                    $errors[] = "Failed to add user.";
                }
            }
        }
    }

    // Remove user
    if (isset($_POST['remove_user'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['success'] = "User removed successfully.";
        header("Location: manage.php");
        exit;
    }

    // Add empty cylinder
    if (isset($_POST['add_cylinder'])) {
        $brand = $_POST['brand'];
        $size = $_POST['size'];
        $quantity = intval($_POST['quantity']);
        $status = 'empty';

        if ($brand && $size && $quantity > 0) {
            $stmt = $pdo->prepare("SELECT id, quantity FROM cylinders WHERE brand = ? AND size = ? AND status = ?");
            $stmt->execute([$brand, $size, $status]);
            $existing = $stmt->fetch();

            if ($existing) {
                $newQty = $existing['quantity'] + $quantity;
                $pdo->prepare("UPDATE cylinders SET quantity = ? WHERE id = ?")->execute([$newQty, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cylinders (brand, size, quantity, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$brand, $size, $quantity, $status]);
            }

            $_SESSION['success'] = "Cylinders added.";
            header("Location: manage.php");
            exit;
        } else {
            $errors[] = "All fields are required.";
        }
    }

    // Remove empty cylinder
    if (isset($_POST['remove_cylinder'])) {
        $brand = $_POST['brand'];
        $size = $_POST['size'];
        $quantity = intval($_POST['quantity']);
        $status = 'empty';

        $stmt = $pdo->prepare("SELECT id, quantity FROM cylinders WHERE brand = ? AND size = ? AND status = ?");
        $stmt->execute([$brand, $size, $status]);
        $existing = $stmt->fetch();

        if ($existing && $existing['quantity'] >= $quantity) {
            $newQty = $existing['quantity'] - $quantity;
            if ($newQty > 0) {
                $pdo->prepare("UPDATE cylinders SET quantity = ? WHERE id = ?")->execute([$newQty, $existing['id']]);
            } else {
                $pdo->prepare("DELETE FROM cylinders WHERE id = ?")->execute([$existing['id']]);
            }
            $_SESSION['success'] = "Cylinders removed.";
            header("Location: manage.php");
            exit;
        } else {
            $errors[] = "Not enough cylinders to remove.";
        }
    }
}

// Handle PDF export
if (isset($_GET['export_pdf'])) {
    require_once __DIR__ . '/../../libs/tcpdf/tcpdf.php';
    
    $cylinders = $pdo->query("SELECT brand, size, status, SUM(quantity) as quantity FROM cylinders WHERE status = 'empty' GROUP BY brand, size, status")->fetchAll();
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('GIMS');
    $pdf->SetAuthor('GIMS');
    $pdf->SetTitle('Empty Cylinders Report');
    $pdf->SetSubject('Empty Cylinders Report');
    $pdf->SetKeywords('Cylinders, Report, GIMS');
    
    $pdf->setHeaderData('', 0, 'Empty Cylinders Report', date('Y-m-d H:i:s'));
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
    $pdf->Cell(0, 10, 'Empty Cylinders Stock Report', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 7, 'Brand', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Size', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Status', 1, 0, 'C', 1);
    $pdf->Cell(40, 7, 'Quantity', 1, 1, 'C', 1);
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $total = 0;
    foreach ($cylinders as $c) {
        $pdf->Cell(60, 6, $c['brand'], 'LR', 0, 'L');
        $pdf->Cell(40, 6, $c['size'], 'LR', 0, 'L');
        $pdf->Cell(40, 6, $c['status'], 'LR', 0, 'C');
        $pdf->Cell(40, 6, $c['quantity'], 'LR', 1, 'R');
        $total += $c['quantity'];
    }
    
    // Footer
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total:', 'LTB', 0, 'R');
    $pdf->Cell(40, 6, $total, 'TRB', 1, 'R');
    
    $pdf->Output('empty_cylinders_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Fetch data
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$cylinders = $pdo->query("SELECT brand, size, status, SUM(quantity) as quantity FROM cylinders WHERE status = 'empty' GROUP BY brand, size, status")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users & Cylinders | GIMS</title>
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

        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .form-section.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Table Styles */
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

        /* Alert Styles */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-radius: 8px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-radius: 8px;
        }

        /* Button Styles */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .btn-warning {
            background-color: var(--warning);
            border-color: var(--warning);
        }

        /* Export Button */
        .btn-export-pdf {
            background-color: #d23333;
            color: white;
        }

        .btn-export-pdf:hover {
            background-color: #b32b2b;
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
            <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Sales</span>
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
               <a href="<?= BASE_URL ?>modules/add/manage.php" class="menu-item active">
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
                    <h4 class="mb-0">User & Cylinder Management</h4>
                    <p class="text-muted">Manage system users and cylinder inventory</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success animated fadeIn"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger animated fadeIn"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <!-- Add User Form -->
            <div class="form-section" id="userFormSection">
                <h5 class="mb-4"><i class="fas fa-user-plus me-2"></i> Add New User</h5>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="full_name" class="form-control" placeholder="Full Name" required />
                        </div>
                        <div class="col-md-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required />
                        </div>
                        <div class="col-md-2">
                            <input type="password" name="password" class="form-control" placeholder="Password" required />
                        </div>
                        <div class="col-md-2">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required />
                        </div>
                        <div class="col-md-2">
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="<?= ROLE_ADMIN ?>">Admin</option>
                                <option value="<?= ROLE_MANAGER ?>">Manager</option>
                                <option value="<?= ROLE_SALES ?>">Sales</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" name="add_user" class="btn btn-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i> Add User
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-section" id="usersTableSection">
                <h5 class="mb-4"><i class="fas fa-users me-2"></i> System Users</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['full_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['role']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button name="remove_user" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cylinder Management Forms -->
            <div class="row">
                <!-- Add Cylinder Form -->
                <div class="col-md-6">
                    <div class="form-section" id="addCylinderSection">
                        <h5 class="mb-4"><i class="fas fa-plus-circle me-2"></i> Add Empty Cylinders</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="brand" class="form-select" required>
                                        <option value="">Select Brand</option>
                                        <option value="O Gas">O Gas</option>
                                        <option value="Oryx">Oryx</option>
                                        <option value="Puma">Puma</option>
                                        <option value="TaifaGas">TaifaGas</option>
                                        <option value="Lake Gas">Lake Gas</option>
                                        <option value="Manjis">Manjis</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="size" class="form-control" placeholder="Size (e.g. 15kg)" required />
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="quantity" class="form-control" placeholder="Quantity" required />
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="add_cylinder" class="btn btn-primary w-100">
                                        <i class="fas fa-plus me-1"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Remove Cylinder Form -->
                <div class="col-md-6">
                    <div class="form-section" id="removeCylinderSection">
                        <h5 class="mb-4"><i class="fas fa-minus-circle me-2"></i> Remove Empty Cylinders</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select name="brand" class="form-select" required>
                                        <option value="">Select Brand</option>
                                        <option value="O Gas">O Gas</option>
                                        <option value="Oryx">Oryx</option>
                                        <option value="Puma">Puma</option>
                                        <option value="TaifaGas">TaifaGas</option>
                                        <option value="Lake Gas">Lake Gas</option>
                                        <option value="Manjis">Manjis</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="size" class="form-control" placeholder="Size (e.g. 15kg)" required />
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="quantity" class="form-control" placeholder="Quantity to remove" required />
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="remove_cylinder" class="btn btn-danger w-100">
                                        <i class="fas fa-minus me-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cylinders Table -->
            <div class="table-section" id="cylindersTableSection">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0"><i class="fas fa-gas-pump me-2"></i> Empty Cylinders Stock</h5>
                    <a href="manage.php?export_pdf=1" class="btn btn-export-pdf">
                        <i class="fas fa-file-pdf me-2"></i> Export PDF
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cylinders as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['brand']) ?></td>
                                <td><?= htmlspecialchars($c['size']) ?></td>
                                <td><?= htmlspecialchars($c['status']) ?></td>
                                <td><?= htmlspecialchars($c['quantity']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cylinders)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">No empty cylinders in stock</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
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
                // Animate sections
                const sections = [
                    document.getElementById('userFormSection'),
                    document.getElementById('usersTableSection'),
                    document.getElementById('addCylinderSection'),
                    document.getElementById('removeCylinderSection'),
                    document.getElementById('cylindersTableSection')
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
        });
    </script>
</body>
</html>