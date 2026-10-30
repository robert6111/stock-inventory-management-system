<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create.php');
    exit;
}

$product_id   = (int)($_POST['product_id'] ?? 0);
$warehouse_id = (int)($_POST['warehouse_id'] ?? 0);
$quantity     = (float)($_POST['quantity'] ?? 0);
$price        = (float)($_POST['price'] ?? 0);
$customer_id  = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$created_by   = (int)$_SESSION['user_id'];
$sale_date    = date('Y-m-d');

if ($product_id <= 0 || $warehouse_id <= 0 || $quantity <= 0 || $price < 0) {
    header('Location: create.php?error=' . urlencode('Invalid sale details.'));
    exit;
}

$conn->begin_transaction();

try {
    $checkStock = $conn->prepare('SELECT quantity FROM stock WHERE product_id = ? AND warehouse_id = ? FOR UPDATE');
    $checkStock->bind_param('ii', $product_id, $warehouse_id);
    $checkStock->execute();
    $row = $checkStock->get_result()->fetch_assoc();

    if (!$row || (float)$row['quantity'] < $quantity) {
        throw new Exception('Insufficient stock.');
    }

    $total = $quantity * $price;

    $saleStmt = $conn->prepare('INSERT INTO sales (customer_id, warehouse_id, total, sale_date, created_by) VALUES (?, ?, ?, ?, ?)');
    $saleStmt->bind_param('iidsi', $customer_id, $warehouse_id, $total, $sale_date, $created_by);
    $saleStmt->execute();
    $sale_id = (int)$conn->insert_id;

    $itemStmt = $conn->prepare('INSERT INTO sales_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
    $itemStmt->bind_param('iidd', $sale_id, $product_id, $quantity, $price);
    $itemStmt->execute();

    $updateStock = $conn->prepare('UPDATE stock SET quantity = quantity - ? WHERE product_id = ? AND warehouse_id = ?');
    $updateStock->bind_param('dii', $quantity, $product_id, $warehouse_id);
    $updateStock->execute();

    $movementType = 'OUT';
    $reference = 'SALE-' . $sale_id;
    $note = 'Sale #' . $sale_id;
    $movement = $conn->prepare('INSERT INTO stock_movements (product_id, warehouse_id, user_id, type, quantity, movement_type, reference, note, reference_id) VALUES (?, ?, ?, "OUT", ?, ?, ?, ?, ?)');
    $movement->bind_param('iiidsssi', $product_id, $warehouse_id, $created_by, $quantity, $movementType, $reference, $note, $sale_id);
    $movement->execute();

    $conn->commit();
    header('Location: create.php?sale_id=' . $sale_id);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: create.php?error=' . urlencode('Sale failed. Please check stock and try again.'));
    exit;
}
