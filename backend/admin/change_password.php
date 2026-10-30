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

$user_id = (int)$_SESSION['user_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $error = 'All fields are required.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row || !password_verify($current_password, $row['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->bind_param('si', $new_hashed, $user_id);
            if ($update->execute()) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Failed to update password. Try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/responsive-fixes.css">
    <style>
        .password-card { width:min(620px, 100%); background:#fff; padding:24px; border-radius:12px; box-shadow:0 8px 25px rgba(0,0,0,.06); }
        .password-card h2 { margin-top:0; color:#111827; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-weight:700; color:#111827; margin-bottom:7px; }
        .form-control { width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; background:#fff; color:#111827; box-sizing:border-box; }
        .btn-primary { border:0; border-radius:8px; padding:11px 16px; background:#2563eb; color:#fff; font-weight:700; cursor:pointer; }
        .alert { padding:11px 12px; border-radius:8px; margin-bottom:14px; font-weight:600; }
        .alert-danger { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .alert-success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        @media (max-width: 900px) { .password-card { padding:18px; } }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/leftsidebar.php'; ?>

<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <div class="password-card">
                <h2>Change Password</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/../stock-inventory-management-system/backend/includes/js/dashboard.js"></script>
</body>
</html>
