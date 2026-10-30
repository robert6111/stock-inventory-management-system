<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$page_title = "Products";

/* ================= FILTERS ================= */
$sku        = $_GET['sku'] ?? '';
$name       = $_GET['name'] ?? '';
$category   = $_GET['category'] ?? '';
$min_price  = $_GET['min_price'] ?? '';
$max_price  = $_GET['max_price'] ?? '';
$low_stock  = $_GET['low_stock'] ?? '';

/* ================= FETCH PRODUCTS + STOCK ================= */
$query = "
    SELECT 
        p.id,
        p.sku,
        p.name AS product_name,
        p.price,
        c.name AS category,
        u.name AS unit,
        w.name AS warehouse,
        IFNULL(s.quantity, 0) AS stock_qty
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN units u ON u.id = p.unit_id
    LEFT JOIN stock s ON s.product_id = p.id
    LEFT JOIN warehouses w ON w.id = s.warehouse_id
    WHERE 1
";

$params = [];
$types = "";

// Apply filters
if ($sku) {
    $query .= " AND p.sku LIKE ? ";
    $params[] = "%$sku%";
    $types .= "s";
}
if ($name) {
    $query .= " AND p.name LIKE ? ";
    $params[] = "%$name%";
    $types .= "s";
}
if ($category) {
    $query .= " AND c.id = ? ";
    $params[] = $category;
    $types .= "i";
}
if ($min_price !== '') {
    $query .= " AND p.price >= ? ";
    $params[] = $min_price;
    $types .= "d";
}
if ($max_price !== '') {
    $query .= " AND p.price <= ? ";
    $params[] = $max_price;
    $types .= "d";
}

$query .= " ORDER BY p.name ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* ================= GROUP PRODUCTS ================= */
$products = [];
while ($row = $result->fetch_assoc()) {
    $pid = $row['id'];
    if (!isset($products[$pid])) {
        $products[$pid] = [
            'sku' => $row['sku'],
            'name' => $row['product_name'],
            'category' => $row['category'],
            'unit' => $row['unit'],
            'price' => $row['price'],
            'stock' => []
        ];
    }
    if ($row['warehouse']) {
        $products[$pid]['stock'][] = [
            'warehouse' => $row['warehouse'],
            'qty' => $row['stock_qty']
        ];
    }
}

/* Fetch categories for filter dropdown */
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>All Products</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">

    <style>
        .products-page-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .products-card {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px;
        }

        .products-card .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .products-title {
            text-align: center;
            margin-bottom: 20px;
        }

        .products-table th,
        .products-table td {
            vertical-align: middle;
            text-align: center;
        }

        .products-table td:nth-child(2) {
            text-align: left;
        }

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

        .text-danger { color: red; font-weight: bold; }
        .text-success { color: green; font-weight: bold; }
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
        <div class="container-fluid">

            <h2 class="products-title">All Products</h2>

            <!-- ================= FILTER FORM ================= -->
            <form class="filter-form" method="GET">
                <div class="form-group">
                    <label>SKU</label>
                    <input type="text" name="sku" value="<?= htmlspecialchars($sku) ?>">
                </div>
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All</option>
                        <?php while($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= ($category==$c['id'])?'selected':'' ?>><?= $c['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
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
                    <a href="/../stock-inventory-management-system/backend/stock/view_stock.php" class="reset-btn">Reset</a>
                </div>
            </form>

            <div class="products-page-wrapper">
                <div class="card-box products-card">
                    <div class="table-responsive">
                        <table id="productsTable" class="table table-bordered table-hover products-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Stock (per warehouse)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $id => $product): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($product['sku'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td><?= htmlspecialchars($product['unit']) ?></td>
                                    <td><?= number_format($product['price'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($product['stock'])): ?>
                                            <?php foreach ($product['stock'] as $s): ?>
                                                <span class="<?= $s['qty'] <= 5 ? 'text-danger' : 'text-success' ?>">
                                                    <?= htmlspecialchars($s['warehouse']) ?>: <?= $s['qty'] ?>
                                                </span><br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/../stock-inventory-management-system/backend/stock/stock_adjustment.php?id=<?= $id ?>" class="btn btn-sm btn-warning">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#productsTable').DataTable({
        pageLength: 10,
        lengthChange: true,
        ordering: true,
        searching: true
    });
});
</script>

</body>
</html>
