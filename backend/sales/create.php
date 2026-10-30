<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: /backend/authentication/login.php");
    exit;
}

// Fetch data for dropdowns
$customers  = $conn->query("SELECT id, name FROM customers ORDER BY name");
$warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name");
$products   = $conn->query("SELECT id, name FROM products WHERE status = 1 ORDER BY name ");

// Handle messages
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Read sale_id from GET if present
$sale_id = '';
if (isset($_GET['sale_id'])) {
    $sale_id = (int)$_GET['sale_id'];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id  = !empty($_POST['customer_id']) ? $_POST['customer_id'] : NULL;
    $warehouse_id = (int)$_POST['warehouse_id'];
    $sale_date    = $_POST['sale_date'];
    $created_by   = $_SESSION['user_id'];

    // Make failed queries throw so the catch block rolls back the whole sale.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    try {
        // Insert sale
        $stmt = $conn->prepare("
            INSERT INTO sales (customer_id, warehouse_id, total, sale_date, created_by)
            VALUES (?, ?, 0, ?, ?)
        ");
        $stmt->bind_param("iisi", $customer_id, $warehouse_id, $sale_date, $created_by);
        $stmt->execute();
        $sale_id = $stmt->insert_id;

        $total = 0;

        if (!empty($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $index => $product_id) {
                $quantity = (float)$_POST['quantity'][$index];
                $price    = (float)$_POST['price'][$index];

                if (empty($product_id) || $quantity <= 0) continue;

                // Check stock
                $checkStock = $conn->prepare("
                    SELECT quantity FROM stock 
                    WHERE product_id = ? AND warehouse_id = ? FOR UPDATE
                ");
                $checkStock->bind_param("ii", $product_id, $warehouse_id);
                $checkStock->execute();
                $row = $checkStock->get_result()->fetch_assoc();

                if (!$row || $row['quantity'] < $quantity) {
                    $productNameRow = $conn->query("SELECT name FROM products WHERE id = " . (int)$product_id)->fetch_assoc();
                    $productName = $productNameRow ? $productNameRow['name'] : "Unknown Product";
                    throw new Exception("Insufficient stock for product: $productName");
                }

                // Insert sale item
                $stmt = $conn->prepare("
                    INSERT INTO sales_items (sale_id, product_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iiid", $sale_id, $product_id, $quantity, $price);
                $stmt->execute();

                $total += $quantity * $price;

                // Reduce stock manually
                $newQty = $row['quantity'] - $quantity;

                $updateStock = $conn->prepare("
                    UPDATE stock
                    SET quantity = ?
                    WHERE product_id = ? AND warehouse_id = ?
                ");
                $updateStock->bind_param("dii", $newQty, $product_id, $warehouse_id);
                $updateStock->execute();

                // Save exact OUT movement history
                $movementType = 'OUT';
                $reference = 'SALE-' . $sale_id;
                $note = 'Sale #' . $sale_id;
                $move = $conn->prepare("
                    INSERT INTO stock_movements
                    (product_id, warehouse_id, user_id, type, quantity, movement_type, reference, note, reference_id)
                    VALUES (?, ?, ?, 'OUT', ?, ?, ?, ?, ?)
                ");
                $move->bind_param("iiidsssi", $product_id, $warehouse_id, $created_by, $quantity, $movementType, $reference, $note, $sale_id);
                $move->execute();
            }
        }

        // Update total
        $stmt = $conn->prepare("UPDATE sales SET total = ? WHERE id = ?");
        $stmt->bind_param("di", $total, $sale_id);
        $stmt->execute();

        $conn->commit();

        $_SESSION['success'] = "Sale created successfully.";
        header("Location: create.php?sale_id=$sale_id");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
    mysqli_report(MYSQLI_REPORT_OFF);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add A Sale</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .removeBtn { background: #e74c3c; color: white; border: none; padding: 2px 6px; cursor: pointer; }
        .removeBtn:hover { opacity: 0.8; }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';
?>
<div class="content-page">
<h2>Add A New Sale</h2>

<?php if($success) echo "<p style='color:green;'>" . htmlspecialchars($success) . "</p>"; ?>
<?php if($error) echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>"; ?>

<form method="POST">

<div class="row">
    <div class="col-md-4">
        <label>Customer (optional)</label>
        <select name="customer_id" class="form-control">
            <option value="">Walk-in Customer</option>
            <?php while ($c = $customers->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div><br>

    <div class="col-md-4">
        <label>Warehouse *</label>
        <select name="warehouse_id" class="form-control" required>
            <?php while ($w = $warehouses->fetch_assoc()): ?>
                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div><br>

    <div class="col-md-4">
        <label>Sale Date *</label>
        <input type="date" name="sale_date" class="form-control" required value="<?= date('Y-m-d') ?>">
    </div>
</div>

<hr>

<h5>Products</h5>

<table class="table table-bordered" id="salesTable">
<thead>
<tr>
    <th>Product</th>
    <th>Quantity</th>
    <th>Price</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<tr>
    <td>
        <select name="product_id[]" class="form-control product">
            <option value="">Select product</option>
            <?php
            $products = $conn->query("SELECT id, name, price FROM products");
            while ($p = $products->fetch_assoc()):
            ?>
                <option value="<?= $p['id']; ?>" data-price="<?= $p['price']; ?>"><?= htmlspecialchars($p['name']); ?></option>
            <?php endwhile; ?>
        </select>
    </td>
    <td><input type="number" name="quantity[]" class="form-control qty" min="1" required></td>
    <td><input type="number" name="price[]" class="form-control price" step="0.01" required></td>
    <td><button type="button" class="removeBtn">Remove</button></td>
</tr>
</tbody>
</table>

<button type="button" class="btn btn-secondary" id="addRow">Add Product</button>

<hr><br>

<button type="submit" class="btn btn-primary">Save Sale</button><br><br>

</form>

<?php if($sale_id): ?>
<a href="/../stock-inventory-management-system/backend/sales/invoice.php?sale_id=<?= $sale_id ?>" target="_blank" class="btn btn-sm btn-primary">Invoice</a>
<?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product')) {
        const row = e.target.closest('tr');
        const priceInput = row.querySelector('.price');
        const selectedOption = e.target.selectedOptions[0];
        priceInput.value = selectedOption.dataset.price || 0;
    }
});

document.getElementById('addRow').addEventListener('click', function() {
    const table = document.querySelector('#salesTable tbody');
    const newRow = table.rows[0].cloneNode(true);
    newRow.querySelectorAll('input').forEach(i => i.value = '');
    newRow.querySelector('select').selectedIndex = 0;
    table.appendChild(newRow);
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('removeBtn')) {
        const rows = document.querySelectorAll('#salesTable tbody tr');
        if (rows.length > 1) {
            e.target.closest('tr').remove();
        }
    }
});
</script>