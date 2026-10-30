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

if (!isset($_GET['id'])) die('Customer ID missing');
$id = (int)$_GET['id'];

$success = '';
$error = '';
$customer = null;

/* FETCH CUSTOMER */
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) die('Customer not found');

/* UPDATE CUSTOMER */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $email   = trim($_POST['email']);
    $address = trim($_POST['address']);

    if (empty($name)) {
        $error = "Name is required.";
    } else {
        $update = $conn->prepare("
            UPDATE customers 
            SET name=?, phone=?, email=?, address=?
            WHERE id=?
        ");
        $update->bind_param("ssssi", $name, $phone, $email, $address, $id);

        if ($update->execute()) {
            header("Location: manage_customers.php?updated=1");
            exit;
        } else {
            $error = "Failed to update customer.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<meta charset="UTF-8">
<title>Edit Customer</title>

<style>
    * {
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body {
        margin: 0;
        background: #f4f7fb;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }

    .container {
        width: 100%;
        max-width: 500px;
        padding: 20px;
    }

    .card {
        background: #fff;
        border-radius: 14px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }

    .back-link {
        display: inline-block;
        margin-bottom: 15px;
        text-decoration: none;
        color: #2563eb;
        font-size: 14px;
        font-weight: bold;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    h2 {
        margin: 0 0 20px;
        text-align: center;
        color: #111827;
    }

    .msg {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
        text-align: center;
    }

    .success {
        background: #dcfce7;
        color: #166534;
    }

    .error {
        background: #fee2e2;
        color: #991b1b;
    }

    label {
        display: block;
        margin-top: 14px;
        font-weight: bold;
        font-size: 14px;
        color: #333;
    }

    input, textarea {
        width: 100%;
        padding: 11px;
        margin-top: 6px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        font-size: 14px;
        transition: 0.2s;
    }

    input:focus, textarea:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }

    textarea {
        min-height: 90px;
        resize: vertical;
    }

    button {
        width: 100%;
        margin-top: 20px;
        padding: 12px;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
    }

    button:hover {
        background: #1d4ed8;
    }
</style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>

<body>

<div class="container">
    <div class="card">

        <a class="back-link" href="manage_customers.php">← Back to Customers</a>

        <h2>✏️ Edit Customer</h2>

        <?php if (!empty($success)): ?>
            <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <label>Name *</label>
            <input type="text" name="name"
                value="<?= htmlspecialchars($customer['name'] ?? '') ?>" required>

            <label>Phone</label>
            <input type="text" name="phone"
                value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">

            <label>Email</label>
            <input type="email" name="email"
                value="<?= htmlspecialchars($customer['email'] ?? '') ?>">

            <label>Address</label>
            <textarea name="address"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>

            <button type="submit">Update Customer</button>

        </form>

    </div>
</div>

</body>
</html>