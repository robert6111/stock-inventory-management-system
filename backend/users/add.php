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
    header('Location: ../authentication/login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

// Only admin users should manage system users.
if (($_SESSION['role'] ?? '') !== 'admin' && (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ../dashboard.php');
    exit;
}

$success = '';
$error = '';
$name = '';
$email = '';
$role = 'staff';
$status = '1';
$isSetupAccount = (($_SESSION['email'] ?? '') === 'setup@stockmanager.local');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $status = $_POST['status'] ?? '1';

    $allowedRoles = ['admin', 'manager', 'staff'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'staff';
    }

    $status = $status === '1' ? 1 : 0;

    if ($name === '') {
        $error = 'User name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($isSetupAccount && $role !== 'admin') {
        $error = 'First setup must create a real admin user.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password and confirm password do not match.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'A user with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssi', $name, $email, $hash, $role, $status);

            if ($stmt->execute()) {
                // If the temporary setup account creates the real admin, disable the setup account immediately.
                if ($isSetupAccount && $role === 'admin') {
                    $setupEmail = 'setup@stockmanager.local';
                    $disableSetup = $conn->prepare('UPDATE users SET status = 0 WHERE email = ?');
                    if ($disableSetup) {
                        $disableSetup->bind_param('s', $setupEmail);
                        $disableSetup->execute();
                    }

                    session_unset();
                    session_destroy();
                    header('Location: ../authentication/login.php?setup_complete=1');
                    exit;
                }

                header('Location: manage.php?created=1');
                exit;
            }

            $error = 'Failed to add user: ' . $conn->error;
        }
    }
}
$page_title = 'Add User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/responsive-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .user-card { background:#fff; border-radius:12px; padding:22px; box-shadow:0 8px 25px rgba(0,0,0,.06); max-width:760px; }
        .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .form-group { display:flex; flex-direction:column; gap:7px; }
        .form-group.full { grid-column:1 / -1; }
        .form-group label { font-weight:600; color:#1f2937; }
        .form-group input, .form-group select { width:100%; padding:11px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; }
        .form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
        .btn-primary, .btn-secondary { display:inline-block; border:0; border-radius:8px; padding:11px 16px; text-decoration:none; cursor:pointer; font-weight:600; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#e5e7eb; color:#111827; }
        .alert-box { padding:12px 14px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .alert-box.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .alert-box.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .help-text { font-size:13px; color:#64748b; margin-top:4px; }
        @media (max-width: 768px) { .form-grid { grid-template-columns:1fr; } .user-card { padding:16px; } }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/leftsidebar.php'; ?>

<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <h4 class="page-title">Add User</h4>
            <div class="user-card">
                <?php if ($isSetupAccount): ?><div class="alert-box success">Create the real admin account now. After saving it, this temporary setup login will be disabled automatically.</div><?php endif; ?>
                <?php if ($error): ?><div class="alert-box error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert-box success"><?= htmlspecialchars($success); ?></div><?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" minlength="8" required>
                            <div class="help-text">Use at least 8 characters.</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role">
                                <option value="admin" <?= $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="manager" <?= $role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="staff" <?= $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="1" <?= (string)$status === '1' ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?= (string)$status === '0' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary"><i class="fa fa-save"></i> Save User</button>
                        <a href="manage.php" class="btn-secondary">Back to Users</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/../stock-inventory-management-system/backend/includes/js/dashboard.js"></script>
</body>
</html>
