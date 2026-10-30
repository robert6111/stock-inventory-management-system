<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
define('STOCK_DISABLE_CSRF_INJECTION', true);
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/AppPdf.php';

// ---------------------- VALIDATE SALE ID ----------------------
$sale_id = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);

if (!$sale_id) {
    die("Invalid sale ID");
}

// ---------------------- FETCH SALE ----------------------
$saleStmt = $conn->prepare("
    SELECT 
        s.id,
        s.sale_date,
        s.total,
        c.name AS customer_name,
        c.phone,
        c.email,
        w.name AS warehouse
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN warehouses w ON s.warehouse_id = w.id
    WHERE s.id = ?
");

if (!$saleStmt) {
    die("Sale query error: " . $conn->error);
}

$saleStmt->bind_param("i", $sale_id);
$saleStmt->execute();

$saleResult = $saleStmt->get_result();
$sale = $saleResult->fetch_assoc();

if (!$sale) {
    die("Sale not found");
}

// ---------------------- FETCH ITEMS ----------------------
$itemsStmt = $conn->prepare("
    SELECT 
        p.name AS product_name,
        si.quantity,
        si.price
    FROM sales_items si
    JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = ?
");

if (!$itemsStmt) {
    die("Items query error: " . $conn->error);
}

$itemsStmt->bind_param("i", $sale_id);
$itemsStmt->execute();

$items = $itemsStmt->get_result();

// ---------------------- HELPER FUNCTIONS ----------------------
function pdfText($text)
{
    if ($text === null || trim($text) === '') {
        return '-';
    }

    // FPDF works better with ISO-8859-1 than UTF-8
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
}

function shortText($text, $length = 45)
{
    $text = trim((string)$text);

    if ($text === '') {
        return '-';
    }

    if (strlen($text) > $length) {
        return substr($text, 0, $length - 3) . '...';
    }

    return $text;
}

function printItemsHeader($pdf)
{
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);

    $pdf->Cell(85, 8, pdfText("Product"), 1, 0, 'C', true);
    $pdf->Cell(25, 8, pdfText("Qty"), 1, 0, 'C', true);
    $pdf->Cell(35, 8, pdfText("Price"), 1, 0, 'C', true);
    $pdf->Cell(35, 8, pdfText("Total"), 1, 1, 'C', true);
}

// ---------------------- CREATE PDF ----------------------
$pdf = new AppPdf();
$pdf->AliasNbPages();
$pdf->AddPage();

// ---------------------- INVOICE TITLE ----------------------
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 8, pdfText("INVOICE"), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(
    0,
    6,
    pdfText("Invoice No: INV-" . str_pad($sale['id'], 5, '0', STR_PAD_LEFT)),
    0,
    1,
    'C'
);

$pdf->Ln(5);

// ---------------------- SALE INFORMATION BOX ----------------------
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, pdfText("Sale Information"), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(35, 7, pdfText("Customer:"), 1, 0);
$pdf->Cell(60, 7, pdfText($sale['customer_name'] ?: 'Walk-in Customer'), 1, 0);

$pdf->Cell(35, 7, pdfText("Date:"), 1, 0);
$pdf->Cell(50, 7, pdfText($sale['sale_date']), 1, 1);

$pdf->Cell(35, 7, pdfText("Phone:"), 1, 0);
$pdf->Cell(60, 7, pdfText($sale['phone']), 1, 0);

$pdf->Cell(35, 7, pdfText("Warehouse:"), 1, 0);
$pdf->Cell(50, 7, pdfText($sale['warehouse']), 1, 1);

$pdf->Cell(35, 7, pdfText("Email:"), 1, 0);
$pdf->Cell(145, 7, pdfText($sale['email']), 1, 1);

$pdf->Ln(8);

// ---------------------- ITEMS TABLE ----------------------
printItemsHeader($pdf);

$pdf->SetFont('Arial', '', 10);

while ($item = $items->fetch_assoc()) {

    if ($pdf->NeedsPageBreak(12)) {
        $pdf->AddPage();
        printItemsHeader($pdf);
        $pdf->SetFont('Arial', '', 10);
    }

    $quantity = (float)$item['quantity'];
    $price    = (float)$item['price'];
    $total    = $quantity * $price;

    $productName = shortText($item['product_name'], 45);

    $pdf->Cell(85, 8, pdfText($productName), 1, 0, 'L');
    $pdf->Cell(25, 8, number_format($quantity, 2), 1, 0, 'C');
    $pdf->Cell(35, 8, number_format($price, 2), 1, 0, 'R');
    $pdf->Cell(35, 8, number_format($total, 2), 1, 1, 'R');
}

// ---------------------- GRAND TOTAL ----------------------
if ($pdf->NeedsPageBreak(18)) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(145, 9, pdfText("Grand Total"), 1, 0, 'R', true);
$pdf->Cell(35, 9, number_format((float)$sale['total'], 2), 1, 1, 'R', true);

// ---------------------- FOOTER NOTE ----------------------
$pdf->Ln(10);

if ($pdf->NeedsPageBreak(18)) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 6, pdfText("Thank you for your business!"), 0, 1, 'C');
$pdf->Cell(0, 6, pdfText("This is a computer-generated invoice."), 0, 1, 'C');

// ---------------------- OUTPUT PDF ----------------------
$pdf->Output('I', "invoice_{$sale_id}.pdf");
exit;