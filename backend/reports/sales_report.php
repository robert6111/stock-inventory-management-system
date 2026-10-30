<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';

date_default_timezone_set('Africa/Dar_es_Salaam');

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

require_once __DIR__ . '/../includes/db.php';


/* ================= HELPER FUNCTIONS ================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected($current, $value)
{
    return ((string)$current === (string)$value) ? 'selected' : '';
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

/* ================= GET FILTERS ================= */

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

/* ================= WHERE CLAUSE ================= */

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

/* ================= SALES REPORT ================= */

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

/* ================= DAILY / MONTHLY / YEARLY SUMMARY ================= */

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
    $where
    GROUP BY period
    ORDER BY period DESC
";

$summaryQuery = runPrepared($conn, $summarySql, $types, $params);

/* ================= BEST SELLING PRODUCTS ================= */

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
    $bestWhere
    GROUP BY p.id, p.name
    ORDER BY qty DESC
    LIMIT 10
";

$bestProducts = runPrepared($conn, $bestProductsSql, $bestTypes, $bestParams);

/* ================= SALES BY WAREHOUSE ================= */

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

/* ================= FILTER DATA ================= */

$warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name ASC");
$customers  = $conn->query("SELECT id, name FROM customers ORDER BY name ASC");
$products   = $conn->query("SELECT id, name FROM products ORDER BY name ASC");

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <title>Sales Report</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 20px;
            color: #222;
        }

        .container {
            max-width: 1250px;
            margin: auto;
            background: #fff;
            padding: 22px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .back-link {
            text-decoration: none;
            background: #444;
            color: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
        }

        h1 {
            margin: 0;
            font-size: 26px;
        }

        h2 {
            margin-top: 35px;
            border-left: 4px solid #333;
            padding-left: 10px;
            font-size: 20px;
        }

        .filter-box {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        label {
            font-weight: bold;
            font-size: 13px;
            display: block;
            margin-bottom: 4px;
        }

        input, select {
            width: 100%;
            padding: 7px;
            border: 1px solid #bbb;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .filter-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        button, .reset-btn {
            padding: 9px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .filter-btn {
            background: #1f6feb;
            color: white;
        }

        .pdf-btn {
            background: #198754;
            color: white;
        }

        .reset-btn {
            background: #777;
            color: white;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 25px;
        }

        .card {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 6px;
        }

        .card-title {
            font-size: 13px;
            color: #666;
        }

        .card-value {
            font-size: 22px;
            font-weight: bold;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: white;
        }

        th, td {
            padding: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }

        th {
            background: #f4f4f4;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .invoice-link {
            text-decoration: none;
            background: #333;
            color: white;
            padding: 5px 9px;
            border-radius: 4px;
            font-size: 13px;
        }

        .grand-row th {
            background: #e8e8e8;
        }

        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 6px;
            }
        }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>

<body>

<div class="container">

    <div class="top-bar">
        <h1>Sales Report</h1>
        <a class="back-link" href="../dashboard.php">← Go Back</a>
    </div>

    <!-- FILTER FORM -->
    <div class="filter-box">
        <form method="GET">

            <div class="filter-grid">

                <div>
                    <label>Sale ID</label>
                    <input type="number" name="sale_id" value="<?= h($sale_id) ?>" placeholder="Example: 15">
                </div>

                <div>
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?= h($from_date) ?>">
                </div>

                <div>
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?= h($to_date) ?>">
                </div>

                <div>
                    <label>Warehouse</label>
                    <select name="warehouse_id">
                        <option value="">All Warehouses</option>
                        <?php while ($w = $warehouses->fetch_assoc()): ?>
                            <option value="<?= h($w['id']) ?>" <?= selected($warehouse, $w['id']) ?>>
                                <?= h($w['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Customer</label>
                    <select name="customer_id">
                        <option value="">All Customers</option>
                        <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= h($c['id']) ?>" <?= selected($customer, $c['id']) ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Search Customer</label>
                    <input type="text" name="search_customer" value="<?= h($search_customer) ?>" placeholder="Name, phone, or email">
                </div>

                <div>
                    <label>Product</label>
                    <select name="product_id">
                        <option value="">All Products</option>
                        <?php while ($p = $products->fetch_assoc()): ?>
                            <option value="<?= h($p['id']) ?>" <?= selected($product, $p['id']) ?>>
                                <?= h($p['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label>Customer Type</label>
                    <select name="customer_type">
                        <option value="all" <?= selected($customer_type, 'all') ?>>All</option>
                        <option value="registered" <?= selected($customer_type, 'registered') ?>>Registered Customers</option>
                        <option value="walkin" <?= selected($customer_type, 'walkin') ?>>Walk-in Customers</option>
                    </select>
                </div>

                <div>
                    <label>Min Total</label>
                    <input type="number" step="0.01" name="min_total" value="<?= h($min_total) ?>" placeholder="0.00">
                </div>

                <div>
                    <label>Max Total</label>
                    <input type="number" step="0.01" name="max_total" value="<?= h($max_total) ?>" placeholder="100000.00">
                </div>

                <div>
                    <label>View</label>
                    <select name="group_by">
                        <option value="daily" <?= selected($group_by, 'daily') ?>>Daily</option>
                        <option value="monthly" <?= selected($group_by, 'monthly') ?>>Monthly</option>
                        <option value="yearly" <?= selected($group_by, 'yearly') ?>>Yearly</option>
                    </select>
                </div>

                <div>
                    <label>Sort By</label>
                    <select name="sort_by">
                        <option value="date_desc" <?= selected($sort_by, 'date_desc') ?>>Newest First</option>
                        <option value="date_asc" <?= selected($sort_by, 'date_asc') ?>>Oldest First</option>
                        <option value="total_desc" <?= selected($sort_by, 'total_desc') ?>>Highest Total</option>
                        <option value="total_asc" <?= selected($sort_by, 'total_asc') ?>>Lowest Total</option>
                        <option value="customer_asc" <?= selected($sort_by, 'customer_asc') ?>>Customer A-Z</option>
                        <option value="warehouse_asc" <?= selected($sort_by, 'warehouse_asc') ?>>Warehouse A-Z</option>
                    </select>
                </div>

                <div>
                    <label>Records Limit</label>
                    <select name="limit">
                        <option value="25" <?= selected($limit, '25') ?>>25</option>
                        <option value="50" <?= selected($limit, '50') ?>>50</option>
                        <option value="100" <?= selected($limit, '100') ?>>100</option>
                        <option value="200" <?= selected($limit, '200') ?>>200</option>
                        <option value="500" <?= selected($limit, '500') ?>>500</option>
                        <option value="all" <?= selected($limit, 'all') ?>>All</option>
                    </select>
                </div>

            </div>

            <div class="filter-actions">
                <button class="filter-btn" type="submit" formaction="sales_report.php">
                    Filter
                </button>

                <button class="pdf-btn"
                        type="submit"
                        formaction="sales_report_pdf.php"
                        formmethod="GET"
                        formtarget="_blank">
                    Export PDF
                </button>

                <a class="reset-btn" href="sales_report.php">
                    Reset
                </a>
            </div>

        </form>
    </div>

    <!-- SALES TABLE -->
    <h2>Sales List</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Phone</th>
            <th>Warehouse</th>
            <th class="text-right">Total</th>
            <th class="text-center">Invoice</th>
        </tr>

        <?php
        $grand = 0;
        $salesCount = 0;
        ?>

        <?php if ($sales->num_rows > 0): ?>

            <?php while ($row = $sales->fetch_assoc()): ?>
                <?php
                $grand += (float)$row['total'];
                $salesCount++;
                ?>

                <tr>
                    <td>#<?= h($row['id']) ?></td>
                    <td><?= h($row['sale_date']) ?></td>
                    <td><?= h($row['customer']) ?></td>
                    <td><?= h($row['customer_phone']) ?></td>
                    <td><?= h($row['warehouse']) ?></td>
                    <td class="text-right"><?= number_format((float)$row['total'], 2) ?></td>
                    <td class="text-center">
                        <a class="invoice-link" href="../sales/invoice.php?sale_id=<?= h($row['id']) ?>" target="_blank">
                            PDF
                        </a>
                    </td>
                </tr>

            <?php endwhile; ?>

            <tr class="grand-row">
                <th colspan="5">Grand Total</th>
                <th class="text-right"><?= number_format($grand, 2) ?></th>
                <th></th>
            </tr>

        <?php else: ?>

            <tr>
                <td colspan="7" class="text-center">No sales found for the selected filters.</td>
            </tr>

        <?php endif; ?>
    </table>

    <!-- FILTER RESULT SUMMARY -->
    <div class="summary-cards">
        <div class="card">
            <div class="card-title">Displayed Sales</div>
            <div class="card-value"><?= number_format($salesCount) ?></div>
        </div>

        <div class="card">
            <div class="card-title">Displayed Total</div>
            <div class="card-value"><?= number_format($grand, 2) ?></div>
        </div>

        <div class="card">
            <div class="card-title">View Type</div>
            <div class="card-value"><?= h(ucfirst($group_by)) ?></div>
        </div>

        <div class="card">
            <div class="card-title">Generated</div>
            <div class="card-value" style="font-size:16px;"><?= date('d M Y H:i') ?></div>
        </div>
    </div>

    <!-- DAILY / MONTHLY / YEARLY -->
    <h2><?= h(ucfirst($group_by)) ?> Sales Summary</h2>

    <table>
        <tr>
            <th>Period</th>
            <th class="text-center">Sales Count</th>
            <th class="text-right">Total Amount</th>
        </tr>

        <?php if ($summaryQuery->num_rows > 0): ?>

            <?php while ($s = $summaryQuery->fetch_assoc()): ?>
                <tr>
                    <td><?= h($s['period']) ?></td>
                    <td class="text-center"><?= number_format((int)$s['sales_count']) ?></td>
                    <td class="text-right"><?= number_format((float)$s['amount'], 2) ?></td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="3" class="text-center">No summary data found.</td>
            </tr>

        <?php endif; ?>
    </table>

    <!-- BEST SELLING PRODUCTS -->
    <h2>Best Selling Products</h2>

    <table>
        <tr>
            <th>Product</th>
            <th class="text-center">Qty Sold</th>
            <th class="text-right">Revenue</th>
        </tr>

        <?php if ($bestProducts->num_rows > 0): ?>

            <?php while ($p = $bestProducts->fetch_assoc()): ?>
                <tr>
                    <td><?= h($p['name']) ?></td>
                    <td class="text-center"><?= number_format((float)$p['qty'], 2) ?></td>
                    <td class="text-right"><?= number_format((float)$p['revenue'], 2) ?></td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="3" class="text-center">No product data found.</td>
            </tr>

        <?php endif; ?>
    </table>

    <!-- SALES BY WAREHOUSE -->
    <h2>Sales by Warehouse</h2>

    <table>
        <tr>
            <th>Warehouse</th>
            <th class="text-center">Sales Count</th>
            <th class="text-right">Total Sales</th>
        </tr>

        <?php if ($salesWarehouse->num_rows > 0): ?>

            <?php while ($w = $salesWarehouse->fetch_assoc()): ?>
                <tr>
                    <td><?= h($w['name']) ?></td>
                    <td class="text-center"><?= number_format((int)$w['sales_count']) ?></td>
                    <td class="text-right"><?= number_format((float)$w['total_sales'], 2) ?></td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="3" class="text-center">No warehouse data found.</td>
            </tr>

        <?php endif; ?>
    </table>

</div>

</body>
</html>