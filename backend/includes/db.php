<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
$servername = "localhost";
// $username   = "stock_management_admin";
$username   = "root";
// $password   = "stock_management_admin";
$password   = "";
$database   = "stock_management_system";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die('Database connection failed. Please check database settings.');
} 

// If the connection is successful, you can perform database operations here
// else {
   //  echo "Connected successfully";
 //}
// ?>



