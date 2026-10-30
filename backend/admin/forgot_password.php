<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../includes/security.php';

// Self-service password reset is disabled. Passwords are reset only by an
// administrator from Manage Users, or on the server PC with
// tools/reset_password_cli.php (see that file for instructions).
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Stock Inventory System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.16), transparent 35%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #111827;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .reset-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .brand-box {
            text-align: center;
            margin-bottom: 22px;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            border-radius: 16px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 800;
        }

        .reset-card h2 {
            margin: 0;
            font-size: 25px;
            color: #0f172a;
        }

        .reset-card p.subtitle {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
        }

        input[type="email"] {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
            background: #ffffff;
        }

        input[type="email"]:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .form-group {
            margin-top: 22px;
        }

        .btn-primary {
            width: 100%;
            margin-top: 16px;
            border: 0;
            background: #2563eb;
            color: #ffffff;
            padding: 13px 16px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin: 16px 0;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.5;
            word-break: break-word;
        }

        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert.success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .reset-link-box {
            margin-top: 14px;
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            padding: 14px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
            word-break: break-all;
        }

        .reset-link-box strong {
            display: block;
            margin-bottom: 6px;
            color: #0f172a;
        }

        .reset-link-box a {
            color: #2563eb;
            font-weight: 700;
        }

        .back-login {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .back-login:hover {
            text-decoration: underline;
        }

        .small-note {
            margin-top: 18px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .reset-card {
                padding: 24px 20px;
                border-radius: 14px;
            }

            .reset-card h2 {
                font-size: 22px;
            }

            .brand-icon {
                width: 52px;
                height: 52px;
                font-size: 23px;
            }
        }
    </style>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>

<body>
    <div class="auth-wrapper">
        <div class="reset-card">
            <div class="brand-box">
                <div class="brand-icon">🔐</div>
                <h2>Forgot Password</h2>
                <p class="subtitle">
                    For security, passwords can only be reset by a system administrator.
                </p>
            </div>

            <div class="alert success">
                Please contact your system administrator. They can set a new password for you from Manage Users.
            </div>

            <a class="back-login" href="../authentication/login.php">
                Back to Login
            </a>

            <div class="small-note">
                Administrators: if no other admin can log in, reset your password on the server computer using tools/reset_password_cli.php.
            </div>
        </div>
    </div>
</body>
</html>