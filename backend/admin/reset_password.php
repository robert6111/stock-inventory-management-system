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
require_once __DIR__ . '/../includes/db.php';

$error = '';
$validToken = false;
$userId = 0;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($token !== '') {
    $stmt = $conn->prepare('SELECT id, reset_expires FROM users WHERE reset_token = ? AND status = 1 LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    if ($row && strtotime($row['reset_expires']) >= time()) {
        $validToken = true;
        $userId = (int)$row['id'];
    } else {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
} else {
    $error = 'Invalid reset request.';
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
        $update->bind_param('si', $hash, $userId);
        if ($update->execute()) {
            header('Location: ../authentication/login.php?reset=success');
            exit;
        }
        $error = 'Failed to reset password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/style.css">
    <style>
        body { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f3f4f6; font-family:Arial, sans-serif; }
        .reset-card { width:min(460px, 94vw); background:#fff; padding:28px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
        .reset-card h2 { margin:0 0 16px; color:#111827; }
        .reset-card input { width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; margin:10px 0 14px; }
        .reset-card button { border:0; background:#2563eb; color:#fff; padding:11px 16px; border-radius:8px; font-weight:700; cursor:pointer; }
        .reset-card a { color:#2563eb; display:inline-block; margin-top:14px; text-decoration:none; }
        .alert { padding:11px 12px; border-radius:8px; margin:12px 0; font-weight:600; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    </style>
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<div class="reset-card">
    <h2>Reset Password</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($validToken): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
            <input type="password" name="new_password" placeholder="New password" minlength="8" required>
            <input type="password" name="confirm_password" placeholder="Confirm password" minlength="8" required>
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
    <a href="../authentication/login.php">Back to Login</a>
</div>
</body>
</html>
