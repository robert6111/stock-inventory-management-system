<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/includes/security.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: authentication/login.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$message = '';
$messageClass = '';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid name and email.';
        $messageClass = 'error';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $check->bind_param('si', $email, $userId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'Another user already uses this email.';
            $messageClass = 'error';
        } else {
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $email, $userId);
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $message = 'Profile updated successfully.';
                $messageClass = 'success';
            } else {
                $message = 'Failed to update profile.';
                $messageClass = 'error';
            }
        }
    }
}

$stmt = $conn->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: authentication/login.php');
    exit;
}

$page_title = 'Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/responsive-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .profile-card { background:#fff; border-radius:12px; padding:22px; box-shadow:0 8px 25px rgba(0,0,0,.06); max-width:680px; }
        .profile-row { display:grid; grid-template-columns:160px 1fr; gap:10px; padding:10px 0; border-bottom:1px solid #e5e7eb; }
        .profile-row strong { color:#475569; }
        .form-group { margin-top:14px; }
        .form-group label { display:block; font-weight:600; margin-bottom:7px; }
        .form-group input { width:100%; padding:11px 12px; border:1px solid #d1d5db; border-radius:8px; }
        .btn-primary { display:inline-block; margin-top:18px; background:#2563eb; color:#fff; border:0; border-radius:8px; padding:11px 16px; cursor:pointer; font-weight:600; }
        .alert-box { padding:12px 14px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .alert-box.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .alert-box.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        @media (max-width: 600px) { .profile-row { grid-template-columns:1fr; } }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/leftsidebar.php'; ?>

<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <h4 class="page-title">My Profile</h4>
            <div class="profile-card">
                <?php if ($message): ?><div class="alert-box <?= htmlspecialchars($messageClass); ?>"><?= htmlspecialchars($message); ?></div><?php endif; ?>

                <div class="profile-row"><strong>Role</strong><span><?= htmlspecialchars(ucfirst($user['role'])); ?></span></div>
                <div class="profile-row"><strong>Status</strong><span><?= (int)$user['status'] === 1 ? 'Active' : 'Inactive'; ?></span></div>
                <div class="profile-row"><strong>Created</strong><span><?= htmlspecialchars($user['created_at']); ?></span></div>

                <form method="post">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fa fa-save"></i> Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="/../stock-inventory-management-system/backend/includes/js/dashboard.js"></script>
</body>
</html>
