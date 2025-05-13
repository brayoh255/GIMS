<?php

// modules/sales/record_sale.php

// Load configuration and dependencies
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_SALES);
// Get sale ID from URL
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    die("Invalid sale ID");
}

try {
    // Get sale information
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as cashier 
        FROM sales s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        die("Sale not found");
    }

    // Get sale items
    $stmt = $pdo->prepare("
        SELECT si.* 
        FROM sale_items si
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate balance
    $balance = $sale['total_amount'] - $sale['amount_paid'];

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Set company information
$company_name = "Gas Inventory Management System";
$company_address = "Tabata, Dar es Salaam, Tanzania";
$company_phone = "+255 618292702";
$company_email = "info@gims.com";

// Generate WhatsApp message
$whatsapp_message = "Receipt #$sale_id\n";
$whatsapp_message .= "Date: " . date('M j, Y H:i', strtotime($sale['created_at'])) . "\n";
$whatsapp_message .= "Customer: " . $sale['customer_name'] . "\n";
if (!empty($sale['customer_phone'])) {
    $whatsapp_message .= "Phone: " . $sale['customer_phone'] . "\n";
}
$whatsapp_message .= "Status: " . ($balance <= 0 ? 'PAID' : 'PENDING') . "\n\n";

$whatsapp_message .= "ITEMS:\n";
foreach ($items as $item) {
    $whatsapp_message .= "- " . $item['product_name'] . " (" . $item['quantity'] . " x " . number_format($item['price'], 2) . ") = " . number_format($item['total_amount'], 2) . "\n";
}

$whatsapp_message .= "\nTOTAL: " . number_format($sale['total_amount'], 2) . " TZS\n";
$whatsapp_message .= "PAID: " . number_format($sale['amount_paid'], 2) . " TZS\n";
if ($balance > 0) {
    $whatsapp_message .= "BALANCE: " . number_format($balance, 2) . " TZS\n";
} else {
    $whatsapp_message .= "CHANGE: " . number_format(abs($balance), 2) . " TZS\n";
}

$whatsapp_message .= "\nThank you for your business!\n$company_name\n$company_phone";

// URL encode the message
$whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $sale['customer_phone']) . 
                "?text=" . urlencode($whatsapp_message);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $sale_id ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #fff;
        }
        
        .receipt-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #ddd;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #004E89;
        }
        
        .company-address {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .company-contact {
            font-size: 12px;
            color: #666;
        }
        
        .receipt-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
            color: #FF6B35;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .customer-info {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
            background-color: #f5f5f5;
            font-size: 14px;
        }
        
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .totals {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .total-row.total {
            font-weight: bold;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .balance {
            color: #d23333;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        .thank-you {
            font-style: italic;
            margin-top: 10px;
        }
        
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status.paid {
            background-color: #4cc9f0;
            color: white;
        }
        
        .status.pending {
            background-color: #f72585;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .btn-print {
            background-color: #004E89;
            color: white;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
        }
        
        .btn-close {
            background-color: #666;
            color: white;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .receipt-container {
                border: none;
                box-shadow: none;
                max-width: 100%;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
            <div class="company-address"><?= htmlspecialchars($company_address) ?></div>
            <div class="company-contact">Tel: <?= htmlspecialchars($company_phone) ?> | Email: <?= htmlspecialchars($company_email) ?></div>
        </div>
        
        <div class="receipt-title">SALES RECEIPT</div>
        
        <div class="receipt-info">
            <div>
                <strong>Receipt #:</strong> <?= $sale_id ?>
            </div>
            <div>
                <strong>Date:</strong> <?= date('M j, Y H:i', strtotime($sale['created_at'])) ?>
            </div>
        </div>
        
        <div class="receipt-info">
            <div>
                <strong>Status:</strong> 
                <span class="status <?= $balance <= 0 ? 'paid' : 'pending' ?>">
                    <?= $balance <= 0 ? 'PAID' : 'PENDING' ?>
                </span>
            </div>
        </div>
        
        <div class="customer-info">
            <div><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
            <?php if (!empty($sale['customer_phone'])): ?>
                <div><strong>Phone:</strong> <?= htmlspecialchars($sale['customer_phone']) ?></div>
            <?php endif; ?>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td class="text-right"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= number_format($item['price'], 2) ?></td>
                    <td class="text-right"><?= number_format($item['total_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?= number_format($sale['total_amount'], 2) ?> TZS</span>
            </div>
            
            <div class="total-row">
                <span>Amount Paid:</span>
                <span><?= number_format($sale['amount_paid'], 2) ?> TZS</span>
            </div>
            
            <?php if ($balance > 0): ?>
            <div class="total-row balance">
                <span>Balance Due:</span>
                <span><?= number_format($balance, 2) ?> TZS</span>
            </div>
            <?php else: ?>
            <div class="total-row">
                <span>Change:</span>
                <span><?= number_format(abs($balance), 2) ?> TZS</span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <div>Thank you for your business!</div>
            <div class="thank-you">Please come again</div>
            <div style="margin-top: 15px;">
                <small>Receipt generated on <?= date('M j, Y H:i') ?></small>
            </div>
        </div>
        
        <div class="action-buttons no-print">
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            
            <?php if (!empty($sale['customer_phone'])): ?>
            <a href="<?= $whatsapp_url ?>" target="_blank" class="btn btn-whatsapp">
                <i class="fab fa-whatsapp"></i> Send via WhatsApp
            </a>
            <?php endif; ?>
            
            <button onclick="window.close()" class="btn btn-close">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        window.onload = function() {
            // Uncomment the line below if you want the print dialog to open automatically
            // window.print();
        };
    </script>
</body>
</html>