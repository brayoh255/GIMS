<?php
define('BASE_URL', '/gims/');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';

// Start session and check auth
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

// Check authentication
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debt_id = $_POST['debt_id'];
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    
    try {
        $pdo->beginTransaction();
        
        // 1. Get current debt details
        $stmt = $pdo->prepare("SELECT * FROM debts WHERE id = ? FOR UPDATE");
        $stmt->execute([$debt_id]);
        $debt = $stmt->fetch();
        
        if (!$debt) {
            throw new Exception("Debt record not found");
        }
        
        // 2. Validate payment amount
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be positive");
        }
        
        if ($payment_amount > $debt['balance']) {
            throw new Exception("Payment cannot exceed outstanding balance");
        }
        
        // 3. Update debt record
        $new_balance = $debt['balance'] - $payment_amount;
        $new_paid_amount = $debt['paid_amount'] + $payment_amount;
        
        $stmt = $pdo->prepare("UPDATE debts SET 
                              paid_amount = ?,
                              balance = ?,
                              updated_at = NOW()
                              WHERE id = ?");
        $stmt->execute([$new_paid_amount, $new_balance, $debt_id]);
        
        // 4. Record payment transaction
        $stmt = $pdo->prepare("INSERT INTO debt_payments 
                             (debt_id, amount, payment_date, recorded_by, created_at)
                             VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$debt_id, $payment_amount, $payment_date, $_SESSION['user_id']]);
        
        // 5. Update sale if fully paid
        if ($new_balance <= 0) {
            $stmt = $pdo->prepare("UPDATE sales SET amount_paid = total_amount WHERE id = ?");
            $stmt->execute([$debt['sale_id']]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Payment recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: view_debts.php");
    exit;
}

// Get debt details if ID provided
$debt = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT d.*, s.total_amount 
                          FROM debts d
                          JOIN sales s ON d.sale_id = s.id
                          WHERE d.id = ?");
    $stmt->execute([$_GET['id']]);
    $debt = $stmt->fetch();
    
    if (!$debt) {
        $_SESSION['error'] = "Debt record not found";
        header("Location: view_debts.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Record Payment</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <?php if ($debt): ?>
                <form method="post">
                    <input type="hidden" name="debt_id" value="<?= $debt['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($debt['customer_name']) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Original Amount</label>
                        <input type="text" class="form-control" value="<?= number_format($debt['amount'], 2) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount Paid</label>
                        <input type="text" class="form-control" value="<?= number_format($debt['paid_amount'], 2) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" value="<?= number_format($debt['balance'], 2) ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Amount *</label>
                        <input type="number" name="payment_amount" class="form-control" 
                               min="0.01" max="<?= $debt['balance'] ?>" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                    <a href="view_debts.php" class="btn btn-secondary">Cancel</a>
                </form>
                <?php else: ?>
                <div class="alert alert-warning">
                    No debt selected. Please select a debt from the <a href="view_debts.php">debts list</a>.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 