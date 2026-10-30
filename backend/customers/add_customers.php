<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

require_once __DIR__ . '/../includes/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $phone   = trim($_POST['phone']);
    $email   = trim($_POST['email']);
    $address = trim($_POST['address']);

    if (empty($name)) {
        $error = "Customer name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $phone, $email, $address);

        if ($stmt->execute()) {
            $success = "Customer added successfully.";
        } else {
            $error = "Failed to add customer.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <title>Add Customer</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .wrapper {
            width: 100%;
            max-width: 460px;
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
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        h2 {
            margin: 0 0 20px;
            color: #222;
            text-align: center;
        }

        .msg {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .success {
            background: #eafaf1;
            color: #1e7e34;
            border: 1px solid #b7ebc6;
        }

        .error {
            background: #fdecec;
            color: #b02a37;
            border: 1px solid #f5c2c7;
        }

        label {
            display: block;
            margin-bottom: 6px;
            margin-top: 14px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
            background: #fff;
        }

        input:focus,
        textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
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

<div class="wrapper">
    <div class="card">
        <a class="back-link" href="/stock-inventory-management-system/backend/sales/create.php">← Back to Sales</a>

        <h2> Add Customer</h2>

        <?php if (!empty($success)): ?>
            <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Customer Name *</label>
            <input type="text" id="name" name="name" required>

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone">

            <label for="email">Email</label>
            <input type="email" id="email" name="email">

            <label for="address">Address</label>
            <textarea id="address" name="address"></textarea>

            <button type="submit">Save Customer</button>
        </form>
    </div>
</div>

</body>
</html>