<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
define('STOCK_DISABLE_CSRF_INJECTION', true);
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

$requestedFile = basename($_GET['file'] ?? '');

if ($requestedFile === '' || !preg_match('/\.sql$/i', $requestedFile)) {
    http_response_code(400);
    exit('Invalid backup file.');
}

$backupDir = realpath(__DIR__ . '/../backups');
$filePath = realpath(__DIR__ . '/../backups/' . $requestedFile);

if (!$backupDir || !$filePath || strpos($filePath, $backupDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Backup file not found.');
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Pragma: no-cache');
header('Expires: 0');
readfile($filePath);
exit;
