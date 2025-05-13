<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/session.php';

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

// Get filter parameters with empty string defaults
$filter_category = $_GET['category'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_min_amount = $_GET['min_amount'] ?? '';
$filter_max_amount = $_GET['max_amount'] ?? '';

// Base query (without user_id for single-user system)
$query = "SELECT * FROM expenses WHERE 1=1";
$params = [];

// Add filters
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

// Complete query with sorting
$query .= " ORDER BY created_at DESC";

// Execute query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all unique categories for filter dropdown
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM expenses ORDER BY category");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate totals
$total_amount = 0;
foreach ($expenses as $expense) {
    $total_amount += $expense['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table-responsive { overflow-x: auto; }
        .filter-card { margin-bottom: 20px; }
        .summary-card { margin-bottom: 20px; background-color: #f8f9fa; }
        .amount-positive { color: #dc3545; }
        .action-btns { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="bi bi-cash-stack"></i> Expense Records</h2>
        
        <div class="card filter-card">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-funnel"></i> Filter Expenses</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                    <?= $filter_category === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= htmlspecialchars($filter_date_from) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= htmlspecialchars($filter_date_to) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Min Amount</label>
                        <input type="number" step="0.01" min="0" name="min_amount" class="form-control" 
                               value="<?= htmlspecialchars($filter_min_amount) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Max Amount</label>
                        <input type="number" step="0.01" min="0" name="max_amount" class="form-control" 
                               value="<?= htmlspecialchars($filter_max_amount) ?>">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Apply
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card summary-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Total Expenses:</span>
                            <span class="amount-positive"><?= number_format($total_amount, 2) ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Number of Records:</span>
                            <span><?= count($expenses) ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Average Expense:</span>
                            <span class="amount-positive">
                                <?= count($expenses) > 0 ? number_format($total_amount / count($expenses), 2) : '0.00' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="expensesTable" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($expense['created_at'])) ?></td>
                                <td><?= htmlspecialchars($expense['description']) ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($expense['category']) ?>
                                    </span>
                                </td>
                                <td class="amount-positive"><?= number_format($expense['amount'], 2) ?></td>
                                <td class="action-btns">
                                    <a href="edit_expense.php?id=<?= $expense['id'] ?>" 
                                       class="btn btn-sm btn-warning"
                                       title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete_expense.php?id=<?= $expense['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this expense?')"
                                       title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No expenses found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-3 d-flex justify-content-between">
            <div>
                <a href="record_expense.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Add New Expense
                </a>
                <a href="export_expenses.php?<?= http_build_query($_GET) ?>" 
                   class="btn btn-secondary">
                    <i class="bi bi-download"></i> Export to Excel
                </a>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-speedometer2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#expensesTable').DataTable({
                responsive: true,
                order: [[0, 'desc']],
                dom: '<"top"<"d-flex justify-content-between align-items-center"lfB>>rt<"bottom"ip>',
                pageLength: 25,
                buttons: [
                    {
                        extend: 'copy',
                        className: 'btn btn-sm btn-outline-secondary'
                    },
                    {
                        extend: 'csv',
                        className: 'btn btn-sm btn-outline-primary'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-sm btn-outline-dark'
                    }
                ],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search expenses...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                }
            });
        });
    </script>
</body>
</html>