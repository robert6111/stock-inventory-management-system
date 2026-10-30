<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';
stock_require_permission('customers_delete');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: manage_customers.php?error=' . urlencode('Customer ID missing'));
    exit;
}

$id = (int)$_POST['id'];
if ($id <= 0) {
    header('Location: manage_customers.php?error=' . urlencode('Invalid customer selected'));
    exit;
}

$balanceStmt = $conn->prepare("
    SELECT
        (SELECT COALESCE(SUM(total), 0) FROM sales WHERE customer_id = ?) -
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ?) AS balance
");
$balanceStmt->bind_param('ii', $id, $id);
$balanceStmt->execute();
$balance = max(0, (float)($balanceStmt->get_result()->fetch_assoc()['balance'] ?? 0));

if ($balance > 0) {
    header('Location: manage_customers.php?error=' . urlencode('Cannot delete customer with outstanding balance.'));
    exit;
}

$delete = $conn->prepare('DELETE FROM customers WHERE id = ?');
$delete->bind_param('i', $id);

if ($delete->execute()) {
    header('Location: manage_customers.php?deleted=1');
    exit;
}

header('Location: manage_customers.php?error=' . urlencode('Failed to delete customer.'));
exit;
