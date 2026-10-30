<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

require_once __DIR__ . '/../includes/db.php';

/* ================= VALIDATE CUSTOMER ID ================= */
$customer_id = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);

if (!$customer_id) {
    die("Invalid customer ID");
}

/* ================= FETCH CUSTOMER ================= */
$custStmt = $conn->prepare("
    SELECT id, name, phone, email
    FROM customers
    WHERE id = ?
");
$custStmt->bind_param("i", $customer_id);
$custStmt->execute();
$customer = $custStmt->get_result()->fetch_assoc();

if (!$customer) {
    die("Customer not found");
}

/* ================= CALCULATE BALANCE ================= */
$balanceStmt = $conn->prepare("
    SELECT
        (
            SELECT COALESCE(SUM(total), 0)
            FROM sales
            WHERE customer_id = ?
        ) -
        (
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE customer_id = ?
        ) AS balance
");
$balanceStmt->bind_param("ii", $customer_id, $customer_id);
$balanceStmt->execute();
$balance = (float)$balanceStmt->get_result()->fetch_assoc()['balance'];
$balance = max(0, $balance);

/* ================= CHECK CUSTOMER STATUS ================= */
$statusStmt = $conn->prepare("
    SELECT
        (SELECT COUNT(*) FROM sales WHERE customer_id = ?) AS sales_count,
        (SELECT COUNT(*) FROM payments WHERE customer_id = ?) AS payment_count
");
$statusStmt->bind_param("ii", $customer_id, $customer_id);
$statusStmt->execute();
$status = $statusStmt->get_result()->fetch_assoc();

$sales_count = (int)$status['sales_count'];
$payment_count = (int)$status['payment_count'];

/* ================= ADD PAYMENT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {

    $amount = (float)$_POST['amount'];
    $method = trim($_POST['method'] ?? '');
    $note   = trim($_POST['note'] ?? '');

    // Re-check balance (IMPORTANT)
    $balanceStmt->execute();
    $balance = (float)$balanceStmt->get_result()->fetch_assoc()['balance'];
    $balance = max(0, $balance);

    if ($balance <= 0) {
        $error = "Customer is already fully paid.";
    } elseif ($amount <= 0) {
        $error = "Invalid payment amount.";
    } elseif ($amount > $balance) {
        $error = "Payment exceeds remaining balance.";
    } else {
        $insert = $conn->prepare("
            INSERT INTO payments (customer_id, amount, payment_date, method, note)
            VALUES (?, ?, CURDATE(), ?, ?)
        ");
        $insert->bind_param("idss", $customer_id, $amount, $method, $note);
        $insert->execute();

        header("Location: customer_payments.php?customer_id=".$customer_id."&paid=1");
        exit;
    }
}

/* ================= PAYMENT HISTORY ================= */
$payments = $conn->prepare("
    SELECT amount, payment_date, method, note
    FROM payments
    WHERE customer_id = ?
    ORDER BY payment_date DESC, id DESC
");
$payments->bind_param("i", $customer_id);
$payments->execute();
$paymentRows = $payments->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <title>Customer Payments</title>
    <a href="/../stock-inventory-management-system/backend/customers/manage_customers.php"> <---go back</a>
    <style>
        body { font-family: Arial; background:#f4f6f8; padding:20px; }
        .card { background:#fff; padding:20px; margin-bottom:20px; border-radius:5px; }
        h2, h3 { margin-top:0; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; border:1px solid #ddd; }
        th { background:#eee; }
        .success { color:green; }
        .error { color:red; }
        .balance { font-size:20px; font-weight:bold; }
        input, textarea, select, button {
            padding:8px; width:100%; margin-bottom:10px;
        }
        button { background:#28a745; color:#fff; border:none; cursor:pointer; }
        button:disabled { background:#aaa; }
        .paid { color:green; font-weight:bold; }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>

<div class="card">
    <h2>💰 Customer Payments</h2>
    <p><strong>Name:</strong> <?= htmlspecialchars($customer['name']) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></p>
</div>

<div class="card">
    <h3>Remaining Balance</h3>
    <p class="balance">
        <?= number_format($balance, 2) ?>
    </p>

    <?php if (isset($_GET['paid'])): ?>
        <p class="success">Payment recorded successfully.</p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($sales_count === 0 && $payment_count === 0): ?>

        <p style="color:#856404; font-weight:bold;">
            ⚠ New customer. No sales or payments recorded yet.
        </p>

    <?php elseif ($balance > 0): ?>
        <form method="POST">
            <label>Payment Amount</label>
            <input type="number"
                   name="amount"
                   step="0.01"
                   min="0.01"
                   max="<?= $balance ?>"
                   required>

            <label>Payment Method</label>
            <select name="method">
                <option value="Cash">Cash</option>
                <option value="Mobile Money Transfer">Mobile Money Transfer</option>
                <option value="Bank Transfer">Bank Transfer</option>
            </select>

            <label>Note (optional)</label>
            <textarea name="note"></textarea>

            <button>Add Payment</button>
        </form>
    <?php else: ?>
        <p class="paid">✔ Customer fully paid. No further action required.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Payment History</h3>

    <?php if ($paymentRows->num_rows === 0): ?>
        <p>No payments recorded.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Note</th>
            </tr>
            <?php while ($p = $paymentRows->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($p['payment_date']) ?></td>
                    <td><?= number_format($p['amount'],2) ?></td>
                    <td><?= htmlspecialchars($p['method']) ?></td>
                    <td><?= htmlspecialchars($p['note']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>