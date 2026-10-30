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

$message = '';
$messageClass = '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$allowedRoles = ['admin', 'manager', 'staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $status = ($_POST['status'] ?? '1') === '1' ? 1 : 0;
        $newPassword = (string)($_POST['new_password'] ?? '');

        if (!in_array($role, $allowedRoles, true)) {
            $role = 'staff';
        }

        if ($id <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid name and email.';
            $messageClass = 'error';
        } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
            $message = 'New password must be at least 8 characters.';
            $messageClass = 'error';
        } else {
            $check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $check->bind_param('si', $email, $id);
            $check->execute();
            $check->store_result();

            // Block changes that would leave the system without an active admin.
            $adminCountResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 1");
            $adminCount = $adminCountResult ? (int)$adminCountResult->fetch_assoc()['total'] : 0;
            $userResult = $conn->query('SELECT role, status FROM users WHERE id = ' . $id . ' LIMIT 1');
            $targetUser = $userResult ? $userResult->fetch_assoc() : null;
            $isActiveAdmin = $targetUser && $targetUser['role'] === 'admin' && (int)$targetUser['status'] === 1;
            $staysActiveAdmin = $role === 'admin' && $status === 1;

            if ($check->num_rows > 0) {
                $message = 'Another user already uses this email.';
                $messageClass = 'error';
            } elseif ($isActiveAdmin && !$staysActiveAdmin && $adminCount <= 1) {
                $message = 'You cannot change the role or status of the last active admin user.';
                $messageClass = 'error';
            } elseif ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?');
                $stmt->bind_param('ssssii', $name, $email, $hash, $role, $status, $id);
                $ok = $stmt->execute();
                $message = $ok ? 'User updated successfully.' : 'Failed to update user.';
                $messageClass = $ok ? 'success' : 'error';
            } else {
                $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?');
                $stmt->bind_param('sssii', $name, $email, $role, $status, $id);
                $ok = $stmt->execute();
                $message = $ok ? 'User updated successfully.' : 'Failed to update user.';
                $messageClass = $ok ? 'success' : 'error';
            }
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $message = 'Invalid user selected.';
            $messageClass = 'error';
        } elseif ($id === $currentUserId) {
            $message = 'You cannot delete the account you are currently using.';
            $messageClass = 'error';
        } else {
            $adminCountResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND status = 1");
            $adminCount = $adminCountResult ? (int)$adminCountResult->fetch_assoc()['total'] : 0;
            $userResult = $conn->query('SELECT role, status FROM users WHERE id = ' . $id . ' LIMIT 1');
            $targetUser = $userResult ? $userResult->fetch_assoc() : null;

            if ($targetUser && $targetUser['role'] === 'admin' && (int)$targetUser['status'] === 1 && $adminCount <= 1) {
                $message = 'You cannot delete the last active admin user.';
                $messageClass = 'error';
            } else {
                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $id);
                $ok = $stmt->execute();
                $message = $ok ? 'User deleted successfully.' : 'Failed to delete user.';
                $messageClass = $ok ? 'success' : 'error';
            }
        }
    }
}

if (isset($_GET['created'])) {
    $message = 'User created successfully.';
    $messageClass = 'success';
}

$search = trim($_GET['search'] ?? '');
$whereSql = '';
if ($search !== '') {
    $safe = '%' . $search . '%';
    $stmt = $conn->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE name LIKE ? OR email LIKE ? OR role LIKE ? ORDER BY id DESC');
    $stmt->bind_param('sss', $safe, $safe, $safe);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query('SELECT id, name, email, role, status, created_at FROM users ORDER BY id DESC');
}

$page_title = 'Manage Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/responsive-fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
<link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .user-toolbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .user-search { display:flex; gap:8px; flex-wrap:wrap; }
        .user-search input { padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; min-width:240px; }
        .btn { border:0; border-radius:8px; padding:10px 13px; text-decoration:none; cursor:pointer; font-weight:600; display:inline-block; }
        .btn-primary { background:#2563eb; color:#fff; }
        .btn-secondary { background:#e5e7eb; color:#111827; }
        .btn-danger { background:#dc2626; color:#fff; }
        .btn-small { padding:8px 10px; font-size:13px; }
        .alert-box { padding:12px 14px; border-radius:8px; margin-bottom:15px; font-weight:600; }
        .alert-box.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .alert-box.success { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .user-edit-form { display:grid; grid-template-columns:1.2fr 1.5fr .9fr .8fr 1.2fr auto; gap:8px; align-items:center; }
        .user-edit-form input, .user-edit-form select { width:100%; padding:8px 9px; border:1px solid #d1d5db; border-radius:6px; }
        .status-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; }
        .status-active { background:#dcfce7; color:#166534; }
        .status-inactive { background:#fee2e2; color:#991b1b; }
        .table-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        @media (max-width: 1100px) {
            .user-edit-form { grid-template-columns:1fr; }
            .user-edit-form .btn { width:100%; text-align:center; }
        }
        @media (max-width: 768px) {
            .user-search, .user-search input, .user-search button, .user-toolbar .btn-primary { width:100%; }
            .table-responsive table, .table-responsive thead, .table-responsive tbody, .table-responsive th, .table-responsive td, .table-responsive tr { display:block; width:100%; }
            .table-responsive thead { display:none; }
            .table-responsive tr { background:#fff; border:1px solid #e5e7eb; border-radius:12px; margin-bottom:12px; padding:12px; }
            .table-responsive td { border:0 !important; padding:8px 0 !important; }
            .table-responsive td::before { content:attr(data-label); display:block; font-weight:700; color:#475569; margin-bottom:4px; }
        }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/leftsidebar.php'; ?>

<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <h4 class="page-title">Manage Users</h4>

            <div class="card-box">
                <div class="user-toolbar">
                    <form method="get" class="user-search">
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search name, email, or role">
                        <button type="submit" class="btn btn-secondary"><i class="fa fa-search"></i> Search</button>
                        <?php if ($search !== ''): ?><a class="btn btn-secondary" href="manage.php">Clear</a><?php endif; ?>
                    </form>
                    <a href="add.php" class="btn btn-primary"><i class="fa fa-plus"></i> Add User</a>
                </div>

                <?php if ($message): ?><div class="alert-box <?= htmlspecialchars($messageClass); ?>"><?= htmlspecialchars($message); ?></div><?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Update</th>
                            <th>Delete</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$users || $users->num_rows === 0): ?>
                            <tr><td colspan="7">No users found.</td></tr>
                        <?php else: ?>
                            <?php $i = 1; while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="#"><?= $i++; ?></td>
                                    <td data-label="User Details">
                                        <form method="post" class="user-edit-form" id="user-form-<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="update_user">
                                            <input type="hidden" name="id" value="<?= (int)$user['id']; ?>">
                                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                                            <select name="role">
                                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                            </select>
                                            <select name="status">
                                                <option value="1" <?= (int)$user['status'] === 1 ? 'selected' : ''; ?>>Active</option>
                                                <option value="0" <?= (int)$user['status'] === 0 ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                            <input type="password" name="new_password" minlength="8" placeholder="New password optional">
                                            <button type="submit" class="btn btn-primary btn-small">Save</button>
                                        </form>
                                    </td>
                                    <td data-label="Role"><?= htmlspecialchars(ucfirst($user['role'])); ?></td>
                                    <td data-label="Status">
                                        <?php if ((int)$user['status'] === 1): ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Created"><?= htmlspecialchars($user['created_at']); ?></td>
                                    <td data-label="Update"><button form="user-form-<?= (int)$user['id']; ?>" type="submit" class="btn btn-primary btn-small">Update</button></td>
                                    <td data-label="Delete">
                                        <form method="post" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id" value="<?= (int)$user['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small" <?= (int)$user['id'] === $currentUserId ? 'disabled' : ''; ?>>Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="/../stock-inventory-management-system/backend/includes/js/dashboard.js"></script>
</body>
</html>
