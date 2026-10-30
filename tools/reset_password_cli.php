<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
/**
 * Emergency password reset for when no administrator can log in.
 *
 * This script only runs from the command line on the computer that hosts the
 * system. It cannot be opened from a web browser.
 *
 * Usage (open Command Prompt on the server computer):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\stock-inventory-management-system\tools\reset_password_cli.php user@example.com
 *
 * You will be asked to type the new password twice. The account is also set to Active.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../backend/includes/db.php';

$email = trim($argv[1] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php reset_password_cli.php user@example.com\n");
    exit(1);
}

$stmt = $conn->prepare('SELECT id, name, role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    fwrite(STDERR, "No user found with email: $email\n");
    exit(1);
}

echo "Resetting password for {$user['name']} ({$user['role']}).\n";
echo 'New password (min 8 characters): ';
$password = trim((string)fgets(STDIN));
echo 'Confirm new password: ';
$confirm = trim((string)fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$userId = (int)$user['id'];
$update = $conn->prepare('UPDATE users SET password = ?, status = 1, reset_token = NULL, reset_expires = NULL WHERE id = ?');
$update->bind_param('si', $hash, $userId);

if (!$update->execute()) {
    fwrite(STDERR, "Failed to update password: {$conn->error}\n");
    exit(1);
}

echo "Password updated. You can now log in with the new password.\n";
