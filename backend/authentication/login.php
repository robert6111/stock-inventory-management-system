<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/db.php';

// Public error display is disabled for customer delivery.
error_reporting(0);
ini_set('display_errors', 0);

$login_error = '';
$login_success = '';

if (isset($_GET['setup_complete'])) {
    $login_success = 'Setup completed. Please login with the real admin account you created.';
}
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $login_success = 'Password reset successfully. Please login with your new password.';
}
if (isset($_GET['timeout']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $login_error = 'Your session expired after 10 minutes of inactivity. Please login again.';
}

$loginLocked = !empty($_SESSION['login_lock_until']) && time() < (int)$_SESSION['login_lock_until'];
if ($loginLocked) {
    $remainingMinutes = max(1, (int)ceil(((int)$_SESSION['login_lock_until'] - time()) / 60));
    $login_error = 'Too many failed login attempts. Please try again in ' . $remainingMinutes . ' minute(s).';
}

// Check database connection
if (!$conn) {
    die('Database connection failed. Please check database settings.');
}

function record_failed_login_attempt(): void
{
    $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_lock_until'] = time() + 900; // 15 minutes
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && !$loginLocked) {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = "Please enter a valid email address.";
    }
    $password = trim($_POST['password'] ?? '');

    if ($email && $password && $login_error === '') {
        // Prepare SQL
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $name, $userEmail, $hash, $role, $status);
            $stmt->fetch();

            // Verify password and active account
            if ((int)$status !== 1) {
                $login_error = "Your account is inactive. Please contact the system owner.";
                record_failed_login_attempt();
            } elseif (password_verify($password, $hash)) {
                // Set session variables
                unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $userEmail;
                $_SESSION['role'] = $role;
                $_SESSION['role_id'] = ($role === 'admin') ? 1 : 2;
                $_SESSION['LAST_ACTIVITY'] = time();

                // Redirect to dashboard
                header("Location: ../dashboard.php");
                exit;
            } else {
                $login_error = "Invalid email or password.";
                record_failed_login_attempt();
            }
        } else {
            $login_error = "Invalid email or password.";
            record_failed_login_attempt();
        }

        $stmt->close();
    } else {
        $login_error = "Please enter both email and password.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Stock Inventory Management System</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/style.css">
        <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/login.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
    <div class="main-container centered-flex">
        <div class="form-container">
            <div class="icon fa fa-user"></div>

            <?php if ($login_success): ?>
                <p class="alert success"><?= htmlspecialchars($login_success) ?></p>
            <?php endif; ?>
            <?php if ($login_error): ?>
                <p class="alert error"><?= htmlspecialchars($login_error) ?></p>
            <?php endif; ?>

            <form class="centered-flex" method="post" action="">
                <div class="title">LOGIN</div>

                <div class="field">
                    <input type="email" name="email" id="email" placeholder="Email" required>
                    <span class="fa fa-user"></span>
                </div>

                <div class="field">
                    <input type="password" name="password" placeholder="Password" required>
                    <span class="fa fa-lock"></span>
                    
                </div>

                <div class="action centered-flex">
                    <label for="remember" class="centered-flex">
                        <input type="checkbox" id="remember"> Remember me
                    </label>
                    <a href="../admin/forgot_password.php">Forgot Password?</a>
                </div>

                <div class="btn-container">
                    <input type="submit" name="login" id="login-btn" value="Login">
                </div>

                <div class="signup">
                    User accounts are created only by the system administrator.
                </div>
            </form>
        </div>
    </div>
</body>
</html>
