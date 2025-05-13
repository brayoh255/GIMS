<?php
// includes/pdf.php

//require_once __DIR__ . '/../vendor/autoload.php'; // Require Composer's autoload if using TCPDF via Composer

function generateReceiptPDF($sale_id) {
    try {
        // Database connection
        require_once __DIR__ . '/../config/database.php';
        global $pdo;
        
        // Get sale data
        $stmt = $pdo->prepare("SELECT s.*, u.username as created_by_name 
                              FROM sales s
                              JOIN users u ON s.created_by = u.id
                              WHERE s.id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sale) {
            throw new Exception("Sale not found");
        }
        
        // Get sale items
        $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('GIMS');
        $pdf->SetAuthor('GIMS');
        $pdf->SetTitle('Receipt #' . $sale_id);
        $pdf->SetSubject('Sale Receipt');
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        
        // Add a page
        $pdf->AddPage();
        
        // Content
        $html = '
        <style>
            .header { text-align: center; margin-bottom: 20px; }
            .title { font-size: 18px; font-weight: bold; }
            .receipt-info { margin-bottom: 15px; }
            .table { width: 100%; border-collapse: collapse; }
            .table th { background-color: #f2f2f2; text-align: left; padding: 5px; }
            .table td { padding: 5px; border-bottom: 1px solid #ddd; }
            .text-right { text-align: right; }
            .total-row { font-weight: bold; }
        </style>
        
        <div class="header">
            <div class="title">GAS INVENTORY MANAGEMENT SYSTEM</div>
            <div>Sale Receipt</div>
        </div>
        
        <div class="receipt-info">
            <div><strong>Receipt #:</strong> ' . $sale_id . '</div>
            <div><strong>Date:</strong> ' . date('M j, Y H:i', strtotime($sale['created_at'])) . '</div>
            <div><strong>Customer:</strong> ' . htmlspecialchars($sale['customer_name']) . '</div>
            <div><strong>Phone:</strong> ' . htmlspecialchars($sale['customer_phone']) . '</div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($items as $item) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['product_name']) . '</td>
                    <td class="text-right">' . number_format($item['price'], 2) . '</td>
                    <td class="text-right">' . $item['quantity'] . '</td>
                    <td class="text-right">' . number_format($item['total_amount'], 2) . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="3" class="text-right">Subtotal:</td>
                    <td class="text-right">' . number_format($sale['total_amount'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" class="text-right">Amount Paid:</td>
                    <td class="text-right">' . number_format($sale['amount_paid'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" class="text-right">Balance:</td>
                    <td class="text-right">' . number_format(max($sale['total_amount'] - $sale['amount_paid'], 0), 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin-top: 30px; text-align: center; font-size: 12px;">
            Thank you for your business!
        </div>';
        
        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save PDF file
        $pdfPath = __DIR__ . '/../receipts/receipt_' . $sale_id . '.pdf';
        $pdf->Output($pdfPath, 'F');
        
        return $pdfPath;
        
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return false;
    }
}

function sendWhatsAppReceipt($phone, $sale_id) {
    try {
        // This is a placeholder - you'll need to implement actual WhatsApp integration
        // Example using Twilio API:
        /*
        require_once __DIR__ . '/../config/twilio.php';
        
        $pdfUrl = 'https://yourdomain.com/receipts/receipt_' . $sale_id . '.pdf';
        $message = "Thank you for your purchase! Here's your receipt: " . $pdfUrl;
        
        $client->messages->create(
            "whatsapp:+$phone",
            [
                'from' => 'whatsapp:+14155238886', // Your Twilio WhatsApp number
                'body' => $message
            ]
        );
        */
        
        // For now, just log that we would send WhatsApp
        error_log("Would send WhatsApp to $phone for sale $sale_id");
        return true;
        
    } catch (Exception $e) {
        error_log("WhatsApp Error: " . $e->getMessage());
        return false;
    }
}
?>