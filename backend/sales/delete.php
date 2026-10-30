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
    header('Location: create.php?error=' . urlencode('Invalid request.'));
    exit;
}

$sale_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($sale_id <= 0) {
    header('Location: create.php?error=' . urlencode('Invalid sale selected.'));
    exit;
}

$conn->begin_transaction();

try {
    $saleStmt = $conn->prepare('SELECT warehouse_id FROM sales WHERE id = ? FOR UPDATE');
    $saleStmt->bind_param('i', $sale_id);
    $saleStmt->execute();
    $sale = $saleStmt->get_result()->fetch_assoc();

    if (!$sale) {
        throw new Exception('Sale not found.');
    }

    $warehouse_id = (int)$sale['warehouse_id'];

    $itemsStmt = $conn->prepare('SELECT product_id, quantity FROM sales_items WHERE sale_id = ?');
    $itemsStmt->bind_param('i', $sale_id);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result();

    $restoreStock = $conn->prepare('
        INSERT INTO stock (product_id, warehouse_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ');

    $movement = $conn->prepare('
        INSERT INTO stock_movements
        (product_id, warehouse_id, user_id, type, quantity, movement_type, reference, note, reference_id)
        VALUES (?, ?, ?, "IN", ?, "IN", ?, ?, ?)
    ');

    $userId = (int)($_SESSION['user_id'] ?? 0);
    while ($item = $items->fetch_assoc()) {
        $productId = (int)$item['product_id'];
        $quantity = (float)$item['quantity'];
        $reference = 'SALE-DELETE-' . $sale_id;
        $note = 'Stock restored after deleting sale #' . $sale_id;

        $restoreStock->bind_param('iid', $productId, $warehouse_id, $quantity);
        $restoreStock->execute();

        $movement->bind_param('iiidssi', $productId, $warehouse_id, $userId, $quantity, $reference, $note, $sale_id);
        $movement->execute();
    }

    $delItems = $conn->prepare('DELETE FROM sales_items WHERE sale_id = ?');
    $delItems->bind_param('i', $sale_id);
    $delItems->execute();

    $clearPayments = $conn->prepare('UPDATE payments SET sale_id = NULL WHERE sale_id = ?');
    $clearPayments->bind_param('i', $sale_id);
    $clearPayments->execute();

    $delSale = $conn->prepare('DELETE FROM sales WHERE id = ?');
    $delSale->bind_param('i', $sale_id);
    $delSale->execute();

    $conn->commit();
    header('Location: create.php?msg=' . urlencode('Sale deleted and stock restored.'));
    exit;
} catch (Exception $e) {
    $conn->rollback();
    header('Location: create.php?error=' . urlencode('Sale could not be deleted safely.'));
    exit;
}
