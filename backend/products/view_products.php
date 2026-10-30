<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

/* Auth check */
if (!isset($_SESSION['user_id'])) {
    header("Location: /backend/authentication/login.php");
    exit;
}

/* ================= FILTERS ================= */
$sku        = $_GET['sku'] ?? '';
$name       = $_GET['name'] ?? '';
$min_price  = $_GET['min_price'] ?? '';
$max_price  = $_GET['max_price'] ?? '';
$low_stock  = $_GET['low_stock'] ?? '';

/* ================= BUILD QUERY ================= */
$where = "WHERE 1 "; // Changed from p.deleted_at to 1 so query is always valid
$params = [];
$types = "";

// Filters for SKU, Name, Min Price, Max Price
if ($sku) {
    $where .= " AND p.sku LIKE ? ";
    $params[] = "%$sku%";
    $types .= "s";
}
if ($name) {
    $where .= " AND p.name LIKE ? ";
    $params[] = "%$name%";
    $types .= "s";
}
if ($min_price !== '') {
    $where .= " AND p.price >= ? ";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price !== '') {
    $where .= " AND p.price <= ? ";
    $params[] = $max_price;
    $types .= "d";
}

// Low-stock filter must go into HAVING
$having = "";
if ($low_stock === '1') {
    $having = "HAVING total_stock <= 5";
}

/* ================= FETCH PRODUCTS ================= */
$query = "
    SELECT 
        p.id,
        p.sku,
        p.name,
        p.price,
        IFNULL(SUM(s.quantity), 0) AS total_stock
    FROM products p
    LEFT JOIN stock s ON s.product_id = p.id
    $where
    GROUP BY p.id
    $having
    ORDER BY p.name ASC
";

// Prepare and execute
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Products</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
 <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .products-card { background: #fff; padding: 20px; border-radius: 6px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .low-stock { color: red; font-weight: bold; }
        .actions a { margin-right: 8px; text-decoration: none; }

        /* ================= FILTER STYLES ================= */
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-form .form-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }

        .filter-form label {
            font-size: 0.85rem;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filter-form input,
        .filter-form select {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-form button,
        .filter-form a.reset-btn {
            padding: 8px 15px;
            font-size: 0.9rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 22px;
        }

        .filter-form button {
            background-color: #28a745;
            color: #fff;
        }

        .filter-form a.reset-btn {
            background-color: #6c757d;
            color: #fff;
        }

        @media (max-width: 768px) {
            .filter-form .form-group {
                min-width: 100%;
            }

            .filter-form button, 
            .filter-form a.reset-btn {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>

<body>

<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';
?>

<div class="content-page">
    <div class="content">

        <h2>Products</h2>

        <!-- ================= FILTER FORM ================= -->
        <form class="filter-form" method="GET">
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" value="<?= htmlspecialchars($sku) ?>">
            </div>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>">
            </div>
            <div class="form-group">
                <label>Min Price</label>
                <input type="number" step="0.01" name="min_price" value="<?= htmlspecialchars($min_price) ?>">
            </div>
            <div class="form-group">
                <label>Max Price</label>
                <input type="number" step="0.01" name="max_price" value="<?= htmlspecialchars($max_price) ?>">
            </div>
            <div class="form-group">
                <label>Low Stock</label>
                <select name="low_stock">
                    <option value="">All</option>
                    <option value="1" <?= $low_stock==='1'?'selected':'' ?>>Yes (≤5)</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit">Filter</button>
                <a href="/../stock-inventory-management-system/backend/products/view_products.php" class="reset-btn">Reset</a>
            </div>
        </form>

        <div class="products-card">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Total Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5">No products found.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['sku'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= number_format($row['price'], 2); ?></td>
                            <td class="<?= ($row['total_stock'] <= 5 ? 'low-stock' : '') ?>">
                                <?= $row['total_stock']; ?>
                            </td>
                            <td class="actions">
                                <a href="/../stock-inventory-management-system/backend/stock/stock_adjustment.php?id=<?= $row['id']; ?>">📦 Stock</a> | <a href="/../stock-inventory-management-system/backend/stock/stock_adjustment.php?id=<?= $row['id']; ?>">✏ Edit</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>