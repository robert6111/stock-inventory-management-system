<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';
if (!isset($_SESSION['user_id'])) die('Unauthorized');

require_once __DIR__ . '/../includes/db.php';

$error = '';
if (isset($_GET['error'])) {
    $error = trim((string)$_GET['error']);
}

/* ================= HANDLE DELETE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_customer') {
    stock_require_permission('customers_delete');
    $customer_id = (int)($_POST['customer_id'] ?? 0);

    // Check invoices count
    $salesCheck = $conn->prepare(""
        . "SELECT COUNT(*) AS total_sales FROM sales WHERE customer_id = ?"
    );
    $salesCheck->bind_param("i", $customer_id);
    $salesCheck->execute();
    $salesResult = $salesCheck->get_result()->fetch_assoc();
    $salesCount = (int)$salesResult['total_sales'];

    // Check remaining balance using customer-level payments.
    $balanceQuery = $conn->prepare(""
        . "SELECT "
        . "(SELECT COALESCE(SUM(total), 0) FROM sales WHERE customer_id = ?) - "
        . "(SELECT COALESCE(SUM(amount), 0) FROM payments WHERE customer_id = ?) AS balance"
    );
    $balanceQuery->bind_param("ii", $customer_id, $customer_id);
    $balanceQuery->execute();
    $balanceResult = $balanceQuery->get_result()->fetch_assoc();
    $balance = max(0, (float)$balanceResult['balance']);

    if ($salesCount > 0 && $balance > 0) {
        $error = "Cannot delete customer. Outstanding balance: " . number_format($balance, 2);
    } else {
        $delete = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $delete->bind_param("i", $customer_id);

        if ($delete->execute()) {
            header("Location: manage_customers.php?deleted=1");
            exit;
        } else {
            $error = "Failed to delete customer.";
        }
    }
}

/* ================= HANDLE EDIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id      = (int)$_POST['edit_id'];
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $email   = trim($_POST['email']);
    $address = trim($_POST['address']);

    if ($name !== '') {
        $update = $conn->prepare("
            UPDATE customers
            SET name = ?, phone = ?, email = ?, address = ?
            WHERE id = ?
        ");
        $update->bind_param("ssssi", $name, $phone, $email, $address, $id);

        if ($update->execute()) {
            header("Location: manage_customers.php?updated=1");
            exit;
        } else {
            $error = "Failed to update customer.";
        }
    } else {
        $error = "Customer name is required.";
    }
}

/* ================= SEARCH / FILTER ================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$debt_status = isset($_GET['debt_status']) ? trim($_GET['debt_status']) : '';

/* ================= FETCH CUSTOMERS ================= */
$customersQuery = "
    SELECT 
        c.id,
        c.name,
        c.phone,
        c.email,
        c.address,
        COALESCE(sales_summary.invoices, 0) AS invoices,
        COALESCE(sales_summary.total_sales, 0) AS total_sales,
        COALESCE(payments_summary.total_paid, 0) AS total_paid,
        COALESCE(sales_summary.total_sales, 0) - COALESCE(payments_summary.total_paid, 0) AS amount_remaining
    FROM customers c
    LEFT JOIN (
        SELECT customer_id, COUNT(*) AS invoices, SUM(total) AS total_sales
        FROM sales
        GROUP BY customer_id
    ) sales_summary ON sales_summary.customer_id = c.id
    LEFT JOIN (
        SELECT customer_id, SUM(amount) AS total_paid
        FROM payments
        GROUP BY customer_id
    ) payments_summary ON payments_summary.customer_id = c.id
";

$whereConditions = [];

if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $whereConditions[] = "(
        c.name LIKE '%$safeSearch%' OR
        c.phone LIKE '%$safeSearch%' OR
        c.email LIKE '%$safeSearch%'
    )";
}

if (!empty($whereConditions)) {
    $customersQuery .= " WHERE " . implode(" AND ", $whereConditions);
}

$customersQuery .= "
    GROUP BY c.id, c.name, c.phone, c.email, c.address, sales_summary.invoices, sales_summary.total_sales, payments_summary.total_paid
";

$havingConditions = [];

if ($debt_status === 'with_debt') {
    $havingConditions[] = "(COALESCE(sales_summary.total_sales, 0) - COALESCE(payments_summary.total_paid, 0)) > 0";
} elseif ($debt_status === 'paid') {
    $havingConditions[] = "(COALESCE(sales_summary.total_sales, 0) - COALESCE(payments_summary.total_paid, 0)) <= 0";
}

if (!empty($havingConditions)) {
    $customersQuery .= " HAVING " . implode(" AND ", $havingConditions);
}

$customersQuery .= " ORDER BY c.name ASC";

$customers = $conn->query($customersQuery);

if (!$customers) {
    die("Error fetching customers: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Management</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #1f2937;
        }

        .page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            background: #ffffff;
            padding: 22px 25px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 28px;
            color: #111827;
        }

        .back-link {
            display: inline-block;
            text-decoration: none;
            background: #2563eb;
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .back-link:hover {
            background: #1d4ed8;
        }

        .subtext {
            margin-top: 8px;
            color: #6b7280;
            font-size: 14px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .filter-box {
            background: #ffffff;
            padding: 18px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 220px;
            flex: 1;
        }

        .filter-group label {
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: bold;
            color: #374151;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn,
        .reset-btn {
            display: inline-block;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }

        .filter-btn {
            background: #2563eb;
            color: white;
        }

        .filter-btn:hover {
            background: #1d4ed8;
        }

        .reset-btn {
            background: #9ca3af;
            color: white;
        }

        .reset-btn:hover {
            background: #6b7280;
        }

        .table-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        thead {
            background: #eff6ff;
        }

        th {
            text-align: left;
            padding: 16px 14px;
            font-size: 14px;
            color: #1e3a8a;
            border-bottom: 1px solid #dbeafe;
            white-space: nowrap;
        }

        td {
            padding: 15px 14px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: middle;
            font-size: 14px;
        }

        tr:hover {
            background: #f9fbff;
        }

        .amount {
            font-weight: bold;
        }

        .remaining-zero {
            color: #16a34a;
            font-weight: bold;
        }

        .remaining-positive {
            color: #dc2626;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            color: white;
            transition: 0.2s ease;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-payments {
            background: #16a34a;
        }

        .btn-edit {
            background: #2563eb;
        }

        .btn-delete {
            background: #dc2626;
        }

        .btn-disabled {
            background: #9ca3af;
            cursor: not-allowed;
            pointer-events: none;
        }

        .muted {
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .title {
                font-size: 22px;
            }

            .page {
                padding: 20px 12px;
            }
        }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>

<div class="page">
    <div class="header">
        <div class="header-top">
            <h2 class="title">👥 Customers Management</h2>
            <a class="back-link" href="/stock-inventory-management-system/backend/dashboard.php">← Back to Dashboard</a>
        </div>
        <div class="subtext">View customers, their invoices, payments, and remaining balances.</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert success">Customer deleted successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert success">Customer updated successfully.</div>
    <?php endif; ?>

    <div class="filter-box">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label for="search">Search</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    placeholder="Search by name, phone, or email"
                    value="<?= htmlspecialchars($search) ?>"
                >
            </div>

            <div class="filter-group">
                <label for="debt_status">Filter</label>
                <select id="debt_status" name="debt_status">
                    <option value="">All Customers</option>
                    <option value="with_debt" <?= $debt_status === 'with_debt' ? 'selected' : '' ?>>Customers With Debt</option>
                    <option value="paid" <?= $debt_status === 'paid' ? 'selected' : '' ?>>Customers Who Finished Debt</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="filter-btn">Apply</button>
                <a href="manage_customers.php" class="reset-btn">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Invoices</th>
                        <th>Total Sales</th>
                        <th>Total Paid</th>
                        <th>Amount Remaining</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($customers->num_rows > 0): ?>
                    <?php while ($c = $customers->fetch_assoc()): ?>
                        <?php $remaining = (float)$c['amount_remaining']; ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['phone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($c['email'] ?: '-') ?></td>
                            <td><?= (int)$c['invoices'] ?></td>
                            <td class="amount"><?= number_format((float)$c['total_sales'], 2) ?></td>
                            <td class="amount"><?= number_format((float)$c['total_paid'], 2) ?></td>
                            <td class="<?= $remaining > 0 ? 'remaining-positive' : 'remaining-zero' ?>">
                                <?= number_format($remaining, 2) ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="customer_payments.php?customer_id=<?= $c['id'] ?>" class="btn btn-payments">Payments</a>
                                    <a href="edit_customer.php?id=<?= $c['id'] ?>" class="btn btn-edit">Edit</a>

                                    <?php if ($remaining == 0 && stock_has_permission('customers_delete')): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this customer?')">
                                            <input type="hidden" name="action" value="delete_customer">
                                            <input type="hidden" name="customer_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="btn btn-delete">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="btn btn-disabled">Delete</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="muted" style="text-align:center; padding: 25px;">
                            No customers found.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>