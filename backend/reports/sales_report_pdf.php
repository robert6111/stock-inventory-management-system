<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
define('STOCK_DISABLE_CSRF_INJECTION', true);
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

date_default_timezone_set('Africa/Dar_es_Salaam');

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/AppPdf.php';

/* ================= HELPER FUNCTIONS ================= */

function pdfText($text)
{
    $text = (string)$text;

    if (trim($text) === '') {
        return '-';
    }

    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);

    return $converted !== false ? $converted : $text;
}

function shortText($text, $length = 35)
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

function runPrepared($conn, $sql, $types = "", $params = [])
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("SQL prepare error: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return $stmt->get_result();
}

function pdfPageBreak($pdf, $height = 10)
{
    if (method_exists($pdf, 'NeedsPageBreak')) {
        return $pdf->NeedsPageBreak($height);
    }

    return ($pdf->GetY() + $height > 265);
}

/* ================= GET ALL FILTERS FROM SALES REPORT PAGE ================= */

$from_date       = $_GET['from_date'] ?? '';
$to_date         = $_GET['to_date'] ?? '';
$warehouse       = $_GET['warehouse_id'] ?? '';
$customer        = $_GET['customer_id'] ?? '';
$product         = $_GET['product_id'] ?? '';
$sale_id         = $_GET['sale_id'] ?? '';
$search_customer = trim($_GET['search_customer'] ?? '');
$min_total       = $_GET['min_total'] ?? '';
$max_total       = $_GET['max_total'] ?? '';
$customer_type   = $_GET['customer_type'] ?? 'all';
$group_by        = $_GET['group_by'] ?? 'daily';
$sort_by         = $_GET['sort_by'] ?? 'date_desc';
$limit           = $_GET['limit'] ?? '100';

/* ================= CLEAN FILTERS ================= */

$warehouse = filter_var($warehouse, FILTER_VALIDATE_INT) ? (int)$warehouse : '';
$customer  = filter_var($customer, FILTER_VALIDATE_INT) ? (int)$customer : '';
$product   = filter_var($product, FILTER_VALIDATE_INT) ? (int)$product : '';
$sale_id   = filter_var($sale_id, FILTER_VALIDATE_INT) ? (int)$sale_id : '';

$min_total = is_numeric($min_total) ? (float)$min_total : '';
$max_total = is_numeric($max_total) ? (float)$max_total : '';

$allowed_group = ['daily', 'monthly', 'yearly'];
if (!in_array($group_by, $allowed_group)) {
    $group_by = 'daily';
}

$allowed_customer_type = ['all', 'registered', 'walkin'];
if (!in_array($customer_type, $allowed_customer_type)) {
    $customer_type = 'all';
}

$allowed_limits = ['25', '50', '100', '200', '500', 'all'];
if (!in_array($limit, $allowed_limits)) {
    $limit = '100';
}

/* ================= SORTING ================= */

$orderBy = "s.sale_date DESC";

switch ($sort_by) {
    case 'date_asc':
        $orderBy = "s.sale_date ASC";
        break;

    case 'total_desc':
        $orderBy = "s.total DESC";
        break;

    case 'total_asc':
        $orderBy = "s.total ASC";
        break;

    case 'customer_asc':
        $orderBy = "customer ASC";
        break;

    case 'warehouse_asc':
        $orderBy = "warehouse ASC";
        break;

    case 'date_desc':
    default:
        $orderBy = "s.sale_date DESC";
        break;
}

/* ================= BUILD WHERE CLAUSE FOR ALL FILTERS ================= */

$where = " WHERE 1=1 ";
$params = [];
$types = "";

if ($sale_id) {
    $where .= " AND s.id = ? ";
    $params[] = $sale_id;
    $types .= "i";
}

if ($from_date) {
    $where .= " AND s.sale_date >= ? ";
    $params[] = $from_date . " 00:00:00";
    $types .= "s";
}

if ($to_date) {
    $where .= " AND s.sale_date <= ? ";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

if ($warehouse) {
    $where .= " AND s.warehouse_id = ? ";
    $params[] = $warehouse;
    $types .= "i";
}

if ($customer) {
    $where .= " AND s.customer_id = ? ";
    $params[] = $customer;
    $types .= "i";
}

if ($product) {
    $where .= "
        AND EXISTS (
            SELECT 1
            FROM sales_items si_filter
            WHERE si_filter.sale_id = s.id
            AND si_filter.product_id = ?
        )
    ";
    $params[] = $product;
    $types .= "i";
}

if ($search_customer !== '') {
    $where .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?) ";
    $searchTerm = "%" . $search_customer . "%";

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= "sss";
}

if ($min_total !== '') {
    $where .= " AND s.total >= ? ";
    $params[] = $min_total;
    $types .= "d";
}

if ($max_total !== '') {
    $where .= " AND s.total <= ? ";
    $params[] = $max_total;
    $types .= "d";
}

if ($customer_type === 'registered') {
    $where .= " AND s.customer_id IS NOT NULL ";
}

if ($customer_type === 'walkin') {
    $where .= " AND s.customer_id IS NULL ";
}

/* ================= LIMIT ================= */

$limitSql = "";

if ($limit !== 'all') {
    $limitSql = " LIMIT " . (int)$limit;
}

/* ================= SALES DATA - FILTERED ================= */

$salesSql = "
    SELECT 
        s.id,
        s.sale_date,
        COALESCE(c.name, 'Walk-in') AS customer,
        COALESCE(c.phone, '-') AS customer_phone,
        w.name AS warehouse,
        s.total
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN warehouses w ON s.warehouse_id = w.id
    $where
    ORDER BY $orderBy
    $limitSql
";

$sales = runPrepared($conn, $salesSql, $types, $params);

/* ================= SUMMARY - FILTERED ================= */

if ($group_by === 'monthly') {
    $groupFormat = "%Y-%m";
} elseif ($group_by === 'yearly') {
    $groupFormat = "%Y";
} else {
    $groupFormat = "%Y-%m-%d";
}

$summarySql = "
    SELECT 
        DATE_FORMAT(s.sale_date, '$groupFormat') AS period,
        COUNT(s.id) AS sales_count,
        SUM(s.total) AS amount
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN warehouses w ON s.warehouse_id = w.id
    $where
    GROUP BY period
    ORDER BY period DESC
";

$summary = runPrepared($conn, $summarySql, $types, $params);

/* ================= BEST SELLING PRODUCTS - FILTERED ================= */

$bestWhere = $where;
$bestParams = $params;
$bestTypes = $types;

if ($product) {
    $bestWhere .= " AND si.product_id = ? ";
    $bestParams[] = $product;
    $bestTypes .= "i";
}

$bestProductsSql = "
    SELECT 
        p.name,
        SUM(si.quantity) AS qty,
        SUM(si.quantity * si.price) AS revenue
    FROM sales_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN warehouses w ON s.warehouse_id = w.id
    $bestWhere
    GROUP BY p.id, p.name
    ORDER BY qty DESC
    LIMIT 10
";

$bestProducts = runPrepared($conn, $bestProductsSql, $bestTypes, $bestParams);

/* ================= SALES BY WAREHOUSE - FILTERED ================= */

$salesWarehouseSql = "
    SELECT 
        w.name,
        COUNT(s.id) AS sales_count,
        SUM(s.total) AS total_sales
    FROM sales s
    JOIN warehouses w ON s.warehouse_id = w.id
    LEFT JOIN customers c ON s.customer_id = c.id
    $where
    GROUP BY w.id, w.name
    ORDER BY total_sales DESC
";

$salesWarehouse = runPrepared($conn, $salesWarehouseSql, $types, $params);

/* ================= PDF ================= */

$pdf = new AppPdf();
$pdf->AliasNbPages();
$pdf->AddPage();

/* HEADER */
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, pdfText('SALES REPORT'), 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, pdfText('Generated: ' . date('d M Y H:i')), 0, 1);
$pdf->Ln(3);

/* FILTER INFO */
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, pdfText('Applied Filters:'), 0, 1);

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, pdfText('Sale ID: ' . ($sale_id ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('From: ' . ($from_date ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('To: ' . ($to_date ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('Warehouse ID: ' . ($warehouse ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('Customer ID: ' . ($customer ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('Product ID: ' . ($product ?: 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('Search Customer: ' . ($search_customer ?: 'None')), 0, 1);
$pdf->Cell(0, 6, pdfText('Customer Type: ' . ucfirst($customer_type)), 0, 1);
$pdf->Cell(0, 6, pdfText('Min Total: ' . ($min_total !== '' ? number_format($min_total, 2) : 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('Max Total: ' . ($max_total !== '' ? number_format($max_total, 2) : 'All')), 0, 1);
$pdf->Cell(0, 6, pdfText('View: ' . ucfirst($group_by)), 0, 1);
$pdf->Cell(0, 6, pdfText('Limit: ' . $limit), 0, 1);

$pdf->Ln(5);

/* ================= SALES TABLE ================= */

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, pdfText('Sales List'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(15, 8, pdfText('ID'), 1, 0, 'C', true);
$pdf->Cell(32, 8, pdfText('Date'), 1, 0, 'C', true);
$pdf->Cell(43, 8, pdfText('Customer'), 1, 0, 'C', true);
$pdf->Cell(28, 8, pdfText('Phone'), 1, 0, 'C', true);
$pdf->Cell(34, 8, pdfText('Warehouse'), 1, 0, 'C', true);
$pdf->Cell(28, 8, pdfText('Total'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

$grand = 0;
$salesCount = 0;

if ($sales->num_rows > 0) {
    while ($row = $sales->fetch_assoc()) {

        if (pdfPageBreak($pdf, 10)) {
            $pdf->AddPage();

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);

            $pdf->Cell(15, 8, pdfText('ID'), 1, 0, 'C', true);
            $pdf->Cell(32, 8, pdfText('Date'), 1, 0, 'C', true);
            $pdf->Cell(43, 8, pdfText('Customer'), 1, 0, 'C', true);
            $pdf->Cell(28, 8, pdfText('Phone'), 1, 0, 'C', true);
            $pdf->Cell(34, 8, pdfText('Warehouse'), 1, 0, 'C', true);
            $pdf->Cell(28, 8, pdfText('Total'), 1, 1, 'C', true);

            $pdf->SetFont('Arial', '', 9);
        }

        $grand += (float)$row['total'];
        $salesCount++;

        $pdf->Cell(15, 8, pdfText('#' . $row['id']), 1, 0, 'C');
        $pdf->Cell(32, 8, pdfText($row['sale_date']), 1);
        $pdf->Cell(43, 8, pdfText(shortText($row['customer'], 25)), 1);
        $pdf->Cell(28, 8, pdfText(shortText($row['customer_phone'], 16)), 1);
        $pdf->Cell(34, 8, pdfText(shortText($row['warehouse'], 20)), 1);
        $pdf->Cell(28, 8, number_format((float)$row['total'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(180, 8, pdfText('No sales found for the selected filters.'), 1, 1, 'C');
}

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(152, 8, pdfText('Displayed Grand Total'), 1, 0, 'R', true);
$pdf->Cell(28, 8, number_format($grand, 2), 1, 1, 'R', true);

$pdf->Ln(10);

/* ================= DAILY / MONTHLY / YEARLY SUMMARY ================= */

if (pdfPageBreak($pdf, 35)) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, pdfText(ucfirst($group_by) . ' Sales Summary'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(60, 8, pdfText('Period'), 1, 0, 'C', true);
$pdf->Cell(40, 8, pdfText('Sales Count'), 1, 0, 'C', true);
$pdf->Cell(50, 8, pdfText('Amount'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

if ($summary->num_rows > 0) {
    while ($s = $summary->fetch_assoc()) {
        $pdf->Cell(60, 8, pdfText($s['period']), 1);
        $pdf->Cell(40, 8, number_format((int)$s['sales_count']), 1, 0, 'C');
        $pdf->Cell(50, 8, number_format((float)$s['amount'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(150, 8, pdfText('No summary data found.'), 1, 1, 'C');
}

$pdf->Ln(10);

/* ================= BEST SELLING PRODUCTS ================= */

if (pdfPageBreak($pdf, 35)) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, pdfText('Best Selling Products'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(80, 8, pdfText('Product'), 1, 0, 'C', true);
$pdf->Cell(35, 8, pdfText('Qty Sold'), 1, 0, 'C', true);
$pdf->Cell(45, 8, pdfText('Revenue'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

if ($bestProducts->num_rows > 0) {
    while ($p = $bestProducts->fetch_assoc()) {
        $pdf->Cell(80, 8, pdfText(shortText($p['name'], 40)), 1);
        $pdf->Cell(35, 8, number_format((float)$p['qty'], 2), 1, 0, 'C');
        $pdf->Cell(45, 8, number_format((float)$p['revenue'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(160, 8, pdfText('No product data found.'), 1, 1, 'C');
}

$pdf->Ln(10);

/* ================= SALES BY WAREHOUSE ================= */

if (pdfPageBreak($pdf, 35)) {
    $pdf->AddPage();
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, pdfText('Sales by Warehouse'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(80, 8, pdfText('Warehouse'), 1, 0, 'C', true);
$pdf->Cell(35, 8, pdfText('Sales Count'), 1, 0, 'C', true);
$pdf->Cell(45, 8, pdfText('Total Sales'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

if ($salesWarehouse->num_rows > 0) {
    while ($w = $salesWarehouse->fetch_assoc()) {
        $pdf->Cell(80, 8, pdfText(shortText($w['name'], 40)), 1);
        $pdf->Cell(35, 8, number_format((int)$w['sales_count']), 1, 0, 'C');
        $pdf->Cell(45, 8, number_format((float)$w['total_sales'], 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(160, 8, pdfText('No warehouse data found.'), 1, 1, 'C');
}

/* OUTPUT */
$pdf->Output('I', 'Sales_Report.pdf');
exit;