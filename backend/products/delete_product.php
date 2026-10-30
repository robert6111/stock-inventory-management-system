<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */

require_once __DIR__ . '/../includes/security.php';
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/db.php';

/* Auth check */
if (!isset($_SESSION['user_id'])) {
    header("Location: /backend/authentication/login.php");
    exit;
}

/* Validate ID */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !is_numeric($_POST['id'])) {
    header("Location: view_products.php?error=Invalid product");
    exit;
}

$product_id = (int) $_POST['id'];

try {
    $conn->begin_transaction();

    /* 1️⃣ Check if product exists and not already deleted */
    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Product not found or already deleted.");
    }

    /* 2️⃣ Check stock */
    $stmt = $conn->prepare("
        SELECT IFNULL(SUM(quantity), 0) AS total_stock
        FROM stock
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stock = $stmt->get_result()->fetch_assoc()['total_stock'];

    if ($stock > 0) {
        throw new Exception("Cannot delete: product has stock.");
    }

    /* 3️⃣ Check sales records */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM sales_items
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $sales = $stmt->get_result()->fetch_assoc()['cnt'];

    if ($sales > 0) {
        throw new Exception("Cannot delete: product already used in sales.");
    }

    /* 4️⃣ Check stock movements */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM stock_movements
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $movements = $stmt->get_result()->fetch_assoc()['cnt'];

    if ($movements > 0) {
        throw new Exception("Cannot delete: product has stock history.");
    }

    /* 5️⃣ Soft delete (mark as deleted) */
    // Make sure your products table has this column:
    // ALTER TABLE products ADD COLUMN deleted_at DATETIME DEFAULT NULL;
    $stmt = $conn->prepare("UPDATE products SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception("Cannot delete product: unknown error.");
    }

    $conn->commit();

    header("Location: view_products.php?msg=Product deleted successfully");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    header("Location: view_products.php?error=" . urlencode($e->getMessage()));
    exit;
}

?>
