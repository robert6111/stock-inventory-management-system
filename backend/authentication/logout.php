<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
// Start the session
require_once __DIR__ . '/../includes/security.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: /../stock-inventory-management-system/backend/authentication/login.php");
exit();
?>
