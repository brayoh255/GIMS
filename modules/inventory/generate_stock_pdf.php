<?php
require_once __DIR__ . '/../../libs/tcpdf/tcpdf.php';
include_once __DIR__ . '/../../config/database.php';

// Create new PDF document
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('GIMS System');
$pdf->SetTitle('Stock Report');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Stock Report', 0, 1, 'C');

// Space
$pdf->Ln(5);

// Table headers
$html = '
<table border="1" cellpadding="4">
<thead>
<tr style="background-color:#f2f2f2;">
    <th><b>#</b></th>
    <th><b>Item Name</b></th>
    <th><b>Type</b></th>
    <th><b>Quantity</b></th>
    <th><b>Unit Price (TZS)</b></th>
    <th><b>Date Added</b></th>
</tr>
</thead>
<tbody>
';

// Fetch stock data
$stmt = $pdo->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY created_at DESC");
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($stocks as $i => $stock) {
    $html .= '<tr>
        <td>' . ($i + 1) . '</td>
        <td>' . htmlspecialchars($stock['item_name']) . '</td>
        <td>' . ucfirst($stock['product_type']) . '</td>
        <td>' . (int)$stock['quantity'] . '</td>
        <td>' . number_format($stock['price'], 0) . '</td>
        <td>' . date('Y-m-d', strtotime($stock['created_at'])) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

$pdf->SetFont('helvetica', '', 10);
$pdf->writeHTML($html, true, false, true, false, '');

// Output
$pdf->Output('stock_report.pdf', 'I');
