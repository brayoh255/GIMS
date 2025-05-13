<?php

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

// Check if specific debt ID requested
$debt_id = $_GET['id'] ?? null;

// Get debt details
$debt = null;
$payments = [];

if ($debt_id) {
    // Single debt report
    $stmt = $pdo->prepare("SELECT d.*, s.total_amount, u.username as recorded_by
                          FROM debts d
                          JOIN sales s ON d.sale_id = s.id
                          LEFT JOIN users u ON d.created_by = u.id
                          WHERE d.id = ?");
    $stmt->execute([$debt_id]);
    $debt = $stmt->fetch();
    
    if ($debt) {
        $stmt = $pdo->prepare("SELECT * FROM debt_payments WHERE debt_id = ? ORDER BY payment_date");
        $stmt->execute([$debt_id]);
        $payments = $stmt->fetchAll();
    }
} else {
    // Full debtors report
    $stmt = $pdo->query("SELECT d.customer_name, 
                        SUM(d.amount) as total_debt,
                        SUM(d.paid_amount) as total_paid,
                        SUM(d.balance) as total_balance
                        FROM debts d
                        GROUP BY d.customer_name
                        HAVING total_balance > 0
                        ORDER BY total_balance DESC");
    $debtors = $stmt->fetchAll();
}

// Generate PDF report if requested
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once '../../libs/tcpdf/tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('GIMS');
    $pdf->SetAuthor('GIMS');
    $pdf->SetTitle('Debtors Report');
    $pdf->SetSubject('Debtors Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Debtors Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    
    if ($debt_id && $debt) {
        // Single debt report
        $pdf->Cell(0, 10, 'Customer: ' . $debt['customer_name'], 0, 1);
        $pdf->Cell(0, 10, 'Sale ID: ' . $debt['sale_id'], 0, 1);
        $pdf->Cell(0, 10, 'Original Amount: ' . number_format($debt['amount'], 2), 0, 1);
        $pdf->Cell(0, 10, 'Amount Paid: ' . number_format($debt['paid_amount'], 2), 0, 1);
        $pdf->Cell(0, 10, 'Current Balance: ' . number_format($debt['balance'], 2), 0, 1);
        
        if (!empty($payments)) {
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Payment History:', 0, 1);
            
            foreach ($payments as $payment) {
                $pdf->Cell(0, 10, 
                    date('M j, Y', strtotime($payment['payment_date'])) . ' - ' . 
                    number_format($payment['amount'], 2), 0, 1);
            }
        }
    } else {
        // Full debtors report
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 10, 'Customer', 1, 0, 'L');
        $pdf->Cell(40, 10, 'Total Debt', 1, 0, 'R');
        $pdf->Cell(40, 10, 'Total Paid', 1, 0, 'R');
        $pdf->Cell(40, 10, 'Balance', 1, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);
        
        foreach ($debtors as $debtor) {
            $pdf->Cell(60, 10, $debtor['customer_name'], 1, 0, 'L');
            $pdf->Cell(40, 10, number_format($debtor['total_debt'], 2), 1, 0, 'R');
            $pdf->Cell(40, 10, number_format($debtor['total_paid'], 2), 1, 0, 'R');
            $pdf->Cell(40, 10, number_format($debtor['total_balance'], 2), 1, 1, 'R');
        }
    }
    
    // Output PDF
    $pdf->Output('debtors_report.pdf', 'I');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debtors Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Debtors Report</h2>
        
        <div class="card">
            <div class="card-body">
                <?php if ($debt_id && $debt): ?>
                    <h4>Customer: <?= htmlspecialchars($debt['customer_name']) ?></h4>
                    <p>Sale ID: <?= $debt['sale_id'] ?></p>
                    <p>Original Amount: <?= number_format($debt['amount'], 2) ?></p>
                    <p>Amount Paid: <?= number_format($debt['paid_amount'], 2) ?></p>
                    <p class="fw-bold">Current Balance: <?= number_format($debt['balance'], 2) ?></p>
                    
                    <?php if (!empty($payments)): ?>
                        <h5 class="mt-4">Payment History</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                    <td><?= number_format($payment['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($payment['recorded_by']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No payments recorded for this debt.</p>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="debtors_report.php?id=<?= $debt_id ?>&export=pdf" class="btn btn-primary">
                            Download PDF Report
                        </a>
                        <a href="view_debts.php" class="btn btn-secondary">Back to Debts</a>
                    </div>
                <?php else: ?>
                    <h4>Outstanding Debts Summary</h4>
                    
                    <div class="mb-3">
                        <a href="debtors_report.php?export=pdf" class="btn btn-primary">
                            Download Full PDF Report
                        </a>
                    </div>
                    
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Total Debt</th>
                                <th>Total Paid</th>
                                <th>Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debtors as $debtor): ?>
                            <tr>
                                <td><?= htmlspecialchars($debtor['customer_name']) ?></td>
                                <td><?= number_format($debtor['total_debt'], 2) ?></td>
                                <td><?= number_format($debtor['total_paid'], 2) ?></td>
                                <td class="fw-bold"><?= number_format($debtor['total_balance'], 2) ?></td>
                                <td>
                                    <a href="view_debts.php?customer=<?= urlencode($debtor['customer_name']) ?>&status=unpaid" 
                                       class="btn btn-sm btn-info">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>