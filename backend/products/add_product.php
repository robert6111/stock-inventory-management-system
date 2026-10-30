<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: /backend/authentication/login.php");
    exit;
}
$msg = $error = "";
/* Fetch categories & units */
$categories = $conn->query("SELECT id, name FROM categories");
$units = $conn->query("SELECT id, name FROM units");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    // SKU is optional; store blank as NULL so many products can have no SKU.
    $sku = trim($_POST['sku'] ?? '');
    $sku = $sku === '' ? null : $sku;
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $unit_id = $_POST['unit_id'];
    $quantity = (int)$_POST['quantity'];
    $warehouse_id = (int)$_POST['warehouse_id'];

    // Make failed queries throw so the catch block rolls back and shows the error.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    try {
        /* 1. Insert product */
        $stmt = $conn->prepare("
            INSERT INTO products (name, sku, price, category_id, unit_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdii", $name, $sku, $price, $category_id, $unit_id);
        $stmt->execute();

        $product_id = $conn->insert_id;

        /* 2. Update stock table */
        $stmt = $conn->prepare("
            INSERT INTO stock (product_id, warehouse_id, quantity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $stmt->bind_param("iid", $product_id, $warehouse_id, $quantity);
        $stmt->execute();

        /* 3. Save exact opening stock movement */
        $movementType = 'IN';
        $reference = 'OPENING-STOCK';
        $note = 'Initial quantity when product was added';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare("
            INSERT INTO stock_movements
            (product_id, warehouse_id, user_id, type, quantity, movement_type, reference, note)
            VALUES (?, ?, ?, 'IN', ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiidsss", $product_id, $warehouse_id, $userId, $quantity, $movementType, $reference, $note);
        $stmt->execute();

        $conn->commit();

        header("Location: view_products.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error =  "Error: " . $e->getMessage();
    }
    mysqli_report(MYSQLI_REPORT_OFF);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add A Product</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';
?>
<div class="content-page">
<h2>Add A New Product</h2>

<?php if($msg) echo "<p style='color:green;'>$msg</p>"; ?>
<?php if($error) echo "<p style='color:red;'>$error</p>"; ?>


<form method="post">
    <label>Product Name</label>
    <input type="text" name="name" required>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <br><br>

   <label>SKU <span class="text-muted">(Product Code, optional)</span></label>
<input type="text" name="sku" class="form-control" placeholder="e.g. BUILD-CMNT-TWG-25KG">
<small class="text-muted">
    Internal product code used for stock tracking
</small>
<br><br>

    <label>Price</label>
    <input type="number" name="price" step="0.01" required><br><br>

    <label>Category</label>
    <select name="category_id" required>
        <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= $c['id']; ?>"><?= $c['name']; ?></option>
        <?php endwhile; ?>
    </select><br><br>

    <label>Unit</label>
    <select name="unit_id" required>
        <?php while ($u = $units->fetch_assoc()): ?>
            <option value="<?= $u['id']; ?>"><?= $u['name']; ?></option>
        <?php endwhile; ?>
    </select>
    <br>
    <br>

<label>Initial Quantity</label>
<input type="number" name="quantity" min="0" required>

<br><br>

<label>Warehouse</label>
<select name="warehouse_id" required>
    <?php
    $warehouses = $conn->query("SELECT id, name FROM warehouses");
    while ($w = $warehouses->fetch_assoc()):
    ?>
        <option value="<?= $w['id']; ?>"><?= htmlspecialchars($w['name']); ?></option>
    <?php endwhile; ?>
</select>

<br><br>

    <button type="submit">Save Product</button>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>