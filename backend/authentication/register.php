<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';

// Public registration is disabled for customer delivery.
// Users must be created only by an authenticated admin from Manage Users > Add User.
if (isset($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'admin' || (int)($_SESSION['role_id'] ?? 0) === 1)) {
    header('Location: ../users/add.php');
    exit;
}

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Disabled</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/style.css">
    <style>
        .notice-box {
            max-width: 460px;
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 12px 35px rgba(0,0,0,.10);
            text-align: center;
        }
        .notice-box h2 { margin-bottom: 12px; color: #111827; }
        .notice-box p { color: #4b5563; line-height: 1.6; }
        .notice-box a {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 16px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
    <div class="main-container centered-flex">
        <div class="notice-box">
            <h2>Registration Disabled</h2>
            <p>For security, new accounts cannot be created from this page. Only an administrator can add users from inside the system.</p>
            <a href="login.php">Go to Login</a>
        </div>
    </div>
</body>
</html>
