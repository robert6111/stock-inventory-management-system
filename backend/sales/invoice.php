<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
define('STOCK_DISABLE_CSRF_INJECTION', true);
require_once __DIR__ . '/../includes/security.php';
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);





/* Auth check */
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

require_once __DIR__ . '/../includes/db.php';
    // require_once __DIR__ . '/../includes/fpdf/fpdf.php';
require_once __DIR__ . '/../includes/AppPdf.php';
/* Validate sale_id */
$sale_id = $_GET['sale_id'] ?? null;

if (!$sale_id || !is_numeric($sale_id)) {
    die('Sale ID missing or invalid');
}

$sale_id = (int)$sale_id;

/* Fetch sale */
$saleStmt = $conn->prepare("
    SELECT 
        s.id,
        s.sale_date,
        s.total,
        c.name AS customer_name,
        c.phone AS customer_phone,
        w.name AS warehouse_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    JOIN warehouses w ON w.id = s.warehouse_id
    WHERE s.id = ?
");
$saleStmt->bind_param("i", $sale_id);
$saleStmt->execute();
$sale = $saleStmt->get_result()->fetch_assoc();

if (!$sale) {
    die('Sale not found');
}

/* Fetch sale items */
$itemsStmt = $conn->prepare("
    SELECT 
        p.name AS product_name,
        si.quantity,
        si.price
    FROM sales_items si
    JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
");
$itemsStmt->bind_param("i", $sale_id);
$itemsStmt->execute();
$items = $itemsStmt->get_result();

/* Create PDF */
$pdf = new AppPdf();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

/* Header */
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'C');
$pdf->Ln(5);

/* Company Info */
$pdf->SetFont('Arial', '', 10);
// $pdf->Cell(100, 6, 'Company: Sabrina Holdings', 0, 0);
$pdf->Cell(0, 6, 'Invoice #: ' . $sale['id'], 0, 1);
$pdf->Cell(100, 6, 'Warehouse: ' . $sale['warehouse_name'], 0, 0);
$pdf->Cell(0, 6, 'Date: ' . $sale['sale_date'], 0, 1);
$pdf->Ln(6);

/* Customer Info */
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'Bill To:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $sale['customer_name'] ?: 'Walk-in Customer', 0, 1);

if (!empty($sale['customer_phone'])) {
    $pdf->Cell(0, 6, 'Phone: ' . $sale['customer_phone'], 0, 1);
}

$pdf->Ln(6);

/* Table Header */
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 8, 'Product', 1);
$pdf->Cell(30, 8, 'Qty', 1, 0, 'C');
$pdf->Cell(40, 8, 'Price', 1, 0, 'R');
$pdf->Cell(40, 8, 'Total', 1, 0, 'R');
$pdf->Ln();

/* Table Data */
$pdf->SetFont('Arial', '', 10);
$grandTotal = 0;

while ($row = $items->fetch_assoc()) {
    $lineTotal = $row['quantity'] * $row['price'];
    $grandTotal += $lineTotal;

    $pdf->Cell(80, 8, $row['product_name'], 1);
    $pdf->Cell(30, 8, $row['quantity'], 1, 0, 'C');
    $pdf->Cell(40, 8, number_format($row['price'], 2), 1, 0, 'R');
    $pdf->Cell(40, 8, number_format($lineTotal, 2), 1, 0, 'R');
    $pdf->Ln();
}

/* Grand Total */
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(150, 10, 'Grand Total', 1);
$pdf->Cell(40, 10, number_format($grandTotal, 2), 1, 0, 'R');

/* Footer */
$pdf->Ln(15);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, 'Thank you for your business!', 0, 1, 'C');

/* Output PDF */
$pdf->Output('I', 'Invoice_' . $sale_id . '.pdf');
