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
$success = $_SESSION['success'] ?? '';
unset($_SESSION['success']); // remove it so refresh won't show it again
$error = '';


/* Fetch products & warehouses */
$products = $conn->query("SELECT id, name FROM products ORDER BY name");
$warehouses = $conn->query("SELECT id, name FROM warehouses ORDER BY name");

$msg = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = (int)$_POST['product_id'];
    $warehouse_id = (int)$_POST['warehouse_id'];
    $quantity = (float)$_POST['quantity'];
    $type = $_POST['type'];
    $reason = trim($_POST['reason']);

    $updatePrice = isset($_POST['update_price']);
    $newPrice = isset($_POST['new_price']) ? (float)$_POST['new_price'] : null;

    if ($quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } elseif ($updatePrice && ($newPrice === null || $newPrice <= 0)) {
        $error = "Please enter a valid new price.";
    } else {

        // Make failed queries throw so the catch block rolls back the adjustment.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            /* Lock stock row */
            $stmt = $conn->prepare("
                SELECT quantity FROM stock
                WHERE product_id = ? AND warehouse_id = ?
                FOR UPDATE
            ");
            $stmt->bind_param("ii", $product_id, $warehouse_id);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                throw new Exception("Stock record not found.");
            }

            $currentStock = $res->fetch_assoc()['quantity'];

            if ($type === 'OUT' && $quantity > $currentStock) {
                throw new Exception("Insufficient stock.");
            }

            $newStock = ($type === 'IN')
                ? $currentStock + $quantity
                : $currentStock - $quantity;

            $stmt = $conn->prepare("
                UPDATE stock SET quantity = ?
                WHERE product_id = ? AND warehouse_id = ?
            ");
            $stmt->bind_param("dii", $newStock, $product_id, $warehouse_id);
            $stmt->execute();

            $movementType = $type; // keep exact movement as IN or OUT
            $reference = 'STOCK-ADJUSTMENT';
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $stmt = $conn->prepare("
                INSERT INTO stock_movements
                (product_id, warehouse_id, user_id, type, quantity, movement_type, reference, note)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiisdsss",
                $product_id,
                $warehouse_id,
                $userId,
                $type,
                $quantity,
                $movementType,
                $reference,
                $reason
            );
            $stmt->execute();

            if ($updatePrice) {
                $stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $oldPrice = $stmt->get_result()->fetch_assoc()['price'];

                $stmt = $conn->prepare("UPDATE products SET price = ? WHERE id = ?");
                $stmt->bind_param("di", $newPrice, $product_id);
                $stmt->execute();

                $stmt = $conn->prepare("
                    INSERT INTO price_history
                    (product_id, old_price, new_price, changed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iddi",
                    $product_id,
                    $oldPrice,
                    $newPrice,
                    $_SESSION['user_id']
                );
                $stmt->execute();
            }

            $conn->commit();
$_SESSION['success'] = "Stock adjustment completed successfully.";
header("Location: " . $_SERVER['PHP_SELF']); // redirect to same page
exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Adjustment</title>

    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">

    <style>
        .content-wrapper {
            padding: 90px;
            min-height: 100vh;
        }
        .adjust-card {
            max-width: 600px;
            margin: 40px auto;
            padding: 25px;
            background: #fff;
            border-radius: 6px;
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
    <div class="adjust-card">

        <h3 class="text-center">Stock Adjustment</h3>
        <br>

        <?php if ($success): ?>
    <p style="color:green;"><?= htmlspecialchars($success); ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red;"><?= htmlspecialchars($error); ?></p>
<?php endif; ?>

        <form method="post">

            <label>Product</label>
            <select name="product_id" required class="form-control">
                <option value="">-- Select Product --</option>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= $p['id']; ?>">
                        <?= htmlspecialchars($p['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <br><br>

            <label>Warehouse</label>
            <select name="warehouse_id" required class="form-control">
                <option value="">-- Select Warehouse --</option>
                <?php while ($w = $warehouses->fetch_assoc()): ?>
                    <option value="<?= $w['id']; ?>">
                        <?= htmlspecialchars($w['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <br><br>

            <label>Adjustment Type</label>
            <select name="type" required class="form-control">
                <option value="IN">Increase Stock (IN)</option>
                <option value="OUT">Decrease Stock (OUT)</option>
            </select>

            <br><br>

            <label>Quantity (Pieces)</label>
            <input type="number" name="quantity" min="1" required class="form-control">

            <br><br>

            <label>
                <input type="checkbox" name="update_price" value="1">
                Update Product Price
            </label>

            <br><br>

            <label>New Price</label>
            <input type="number" name="new_price" step="0.01" class="form-control">

            <br><br>

            <label>Reason (optional)</label>
            <textarea name="reason" class="form-control"></textarea>

            <br><br>

            <button type="submit" class="btn btn-primary">
                Save Adjustment
            </button>

        </form>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>