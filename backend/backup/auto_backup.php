<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
/**
 * Automatic MySQL database backup for Stock Inventory Management System.
 *
 * This file is safe to include from dashboard.php. It silently creates one
 * automatic backup per day, keeps local backups, copies backups to OneDrive
 * when configured, and deletes older backups based on retention settings.
 */

$configFile = __DIR__ . '/backup_config.php';
if (is_file($configFile)) {
    require_once $configFile;
}

if (!defined('STOCK_ONEDRIVE_BACKUP_ENABLED')) {
    define('STOCK_ONEDRIVE_BACKUP_ENABLED', true);
}
if (!defined('STOCK_ONEDRIVE_BACKUP_DIR')) {
    define('STOCK_ONEDRIVE_BACKUP_DIR', '');
}
if (!defined('STOCK_ONEDRIVE_BACKUP_SUBFOLDER')) {
    define('STOCK_ONEDRIVE_BACKUP_SUBFOLDER', 'Stock Inventory Management System Backups');
}
if (!defined('STOCK_BACKUP_KEEP_LATEST_LOCAL')) {
    define('STOCK_BACKUP_KEEP_LATEST_LOCAL', 30);
}
if (!defined('STOCK_BACKUP_KEEP_LATEST_ONEDRIVE')) {
    define('STOCK_BACKUP_KEEP_LATEST_ONEDRIVE', 30);
}

if (!function_exists('stock_backup_sql_value')) {
    function stock_backup_sql_value(mysqli $conn, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . $conn->real_escape_string((string)$value) . "'";
    }
}

if (!function_exists('stock_backup_safe_database_name')) {
    function stock_backup_safe_database_name(string $database): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $database);
        return $safe ?: 'database';
    }
}

if (!function_exists('stock_backup_join_path')) {
    function stock_backup_join_path(string $base, string $child): string
    {
        return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . ltrim($child, "\\/");
    }
}

if (!function_exists('stock_backup_log')) {
    function stock_backup_log(string $backupDir, string $message): void
    {
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(stock_backup_join_path($backupDir, 'backup_log.txt'), $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('stock_backup_write_last_status')) {
    function stock_backup_write_last_status(string $backupDir, array $status): void
    {
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $status['checked_at'] = date('Y-m-d H:i:s');
        @file_put_contents(
            stock_backup_join_path($backupDir, 'last_backup_status.json'),
            json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}

if (!function_exists('stock_backup_read_last_status')) {
    function stock_backup_read_last_status(string $backupDir): array
    {
        $file = stock_backup_join_path($backupDir, 'last_backup_status.json');
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('stock_backup_get_home_dir')) {
    function stock_backup_get_home_dir(): string
    {
        $candidates = [
            getenv('USERPROFILE') ?: '',
            getenv('HOME') ?: '',
            (getenv('HOMEDRIVE') ?: '') . (getenv('HOMEPATH') ?: ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && is_dir($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('stock_backup_find_onedrive_base_dir')) {
    function stock_backup_find_onedrive_base_dir(): string
    {
        $candidates = [];

        foreach (['OneDriveCommercial', 'OneDriveConsumer', 'OneDrive', 'ONEDRIVE'] as $envName) {
            $value = getenv($envName);
            if ($value) {
                $candidates[] = $value;
            }
        }

        $home = stock_backup_get_home_dir();
        if ($home !== '') {
            $candidates[] = stock_backup_join_path($home, 'OneDrive');

            $matches = glob(stock_backup_join_path($home, 'OneDrive - *')) ?: [];
            foreach ($matches as $match) {
                $candidates[] = $match;
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && is_dir($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('stock_resolve_onedrive_backup_dir')) {
    function stock_resolve_onedrive_backup_dir(): string
    {
        if (!STOCK_ONEDRIVE_BACKUP_ENABLED) {
            return '';
        }

        $configuredDir = trim((string)STOCK_ONEDRIVE_BACKUP_DIR);
        if ($configuredDir !== '') {
            return rtrim($configuredDir, "\\/");
        }

        $baseDir = stock_backup_find_onedrive_base_dir();
        if ($baseDir === '') {
            return '';
        }

        $subfolder = trim((string)STOCK_ONEDRIVE_BACKUP_SUBFOLDER, "\\/");
        return $subfolder === '' ? rtrim($baseDir, "\\/") : stock_backup_join_path($baseDir, $subfolder);
    }
}


if (!function_exists('stock_backup_prepare_writable_dir')) {
    function stock_backup_prepare_writable_dir(string $dir, string $label = 'backup folder'): array
    {
        $dir = rtrim(trim($dir), "\\/");

        if ($dir === '') {
            return [
                'success' => false,
                'message' => ucfirst($label) . ' path is empty.',
                'path' => '',
            ];
        }

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $parent = dirname($dir);
                $parentMessage = is_dir($parent)
                    ? ' Parent folder exists, but PHP/Apache could not create the backup folder there.'
                    : ' Parent folder does not exist or cannot be reached by PHP/Apache.';

                return [
                    'success' => false,
                    'message' => 'Could not create ' . $label . ': ' . $dir . '.' . $parentMessage,
                    'path' => $dir,
                ];
            }
        }

        if (!is_dir($dir)) {
            return [
                'success' => false,
                'message' => ucfirst($label) . ' does not exist: ' . $dir,
                'path' => $dir,
            ];
        }

        $testFile = stock_backup_join_path($dir, '.stock_backup_write_test_' . bin2hex(random_bytes(4)) . '.tmp');
        $written = @file_put_contents($testFile, 'write-test-' . date('c'), LOCK_EX);
        if ($written === false) {
            $phpUser = function_exists('get_current_user') ? get_current_user() : 'unknown';
            $lastError = error_get_last();
            $extra = $lastError && !empty($lastError['message']) ? ' Last PHP error: ' . $lastError['message'] : '';

            return [
                'success' => false,
                'message' => ucfirst($label) . ' exists but is not writable by PHP/Apache: ' . $dir . '. PHP user: ' . $phpUser . '. Check Windows folder permissions, OneDrive sign-in, and whether Apache is running as a Windows service.' . $extra,
                'path' => $dir,
            ];
        }

        @unlink($testFile);

        return [
            'success' => true,
            'message' => ucfirst($label) . ' is writable.',
            'path' => $dir,
        ];
    }
}

if (!function_exists('stock_backup_get_onedrive_diagnostics')) {
    function stock_backup_get_onedrive_diagnostics(): array
    {
        if (!STOCK_ONEDRIVE_BACKUP_ENABLED) {
            return [
                'success' => false,
                'enabled' => false,
                'message' => 'OneDrive backup is disabled in backup_config.php.',
                'path' => '',
            ];
        }

        $oneDriveDir = stock_resolve_onedrive_backup_dir();
        if ($oneDriveDir === '') {
            return [
                'success' => false,
                'enabled' => true,
                'message' => 'OneDrive folder was not detected. Set STOCK_ONEDRIVE_BACKUP_DIR in backend/backup/backup_config.php.',
                'path' => '',
            ];
        }

        $check = stock_backup_prepare_writable_dir($oneDriveDir, 'OneDrive backup folder');
        $check['enabled'] = true;
        return $check;
    }
}

if (!function_exists('stock_prune_backups')) {
    function stock_prune_backups(string $backupDir, string $database, int $keep = 30): int
    {
        if ($keep < 1 || !is_dir($backupDir)) {
            return 0;
        }

        $prefix = stock_backup_safe_database_name($database);
        $files = glob(stock_backup_join_path($backupDir, $prefix . '_*.sql')) ?: [];

        usort($files, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });

        $deleted = 0;
        foreach (array_slice($files, $keep) as $oldFile) {
            if (is_file($oldFile) && @unlink($oldFile)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

if (!function_exists('stock_prune_auto_backups')) {
    function stock_prune_auto_backups(string $backupDir, string $database, int $keep = 14): void
    {
        // Backward-compatible wrapper. The improved retention system deletes
        // old backup files by database prefix, not only automatic files.
        stock_prune_backups($backupDir, $database, $keep);
    }
}

if (!function_exists('stock_sync_backup_to_onedrive')) {
    function stock_sync_backup_to_onedrive(string $backupFilePath, string $database, string $backupDir = ''): array
    {
        if (!STOCK_ONEDRIVE_BACKUP_ENABLED) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'OneDrive backup is disabled in backup_config.php.',
                'path' => '',
            ];
        }

        if (!is_file($backupFilePath)) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Backup file was not found for OneDrive sync.',
                'path' => '',
            ];
        }

        $oneDriveDir = stock_resolve_onedrive_backup_dir();
        if ($oneDriveDir === '') {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'OneDrive folder was not detected. Set STOCK_ONEDRIVE_BACKUP_DIR in backend/backup/backup_config.php.',
                'path' => '',
            ];
        }

        $dirCheck = stock_backup_prepare_writable_dir($oneDriveDir, 'OneDrive backup folder');
        if (empty($dirCheck['success'])) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => $dirCheck['message'] ?? ('OneDrive backup folder is not writable: ' . $oneDriveDir),
                'path' => $oneDriveDir,
            ];
        }

        $destination = stock_backup_join_path($oneDriveDir, basename($backupFilePath));
        if (!@copy($backupFilePath, $destination)) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Could not copy backup to OneDrive folder.',
                'path' => $oneDriveDir,
            ];
        }

        @touch($destination, filemtime($backupFilePath));
        $deleted = stock_prune_backups($oneDriveDir, $database, (int)STOCK_BACKUP_KEEP_LATEST_ONEDRIVE);

        return [
            'success' => true,
            'skipped' => false,
            'message' => 'Backup copied to OneDrive successfully' . ($deleted > 0 ? ' and ' . $deleted . ' old OneDrive backup(s) deleted.' : '.') ,
            'path' => $destination,
            'folder' => $oneDriveDir,
            'deleted_old' => $deleted,
        ];
    }
}

if (!function_exists('stock_sync_existing_backups_to_onedrive')) {
    function stock_sync_existing_backups_to_onedrive(string $backupDir, string $database): array
    {
        if (!is_dir($backupDir)) {
            return ['success' => false, 'message' => 'Local backup folder does not exist yet.', 'copied' => 0];
        }

        $oneDriveDir = stock_resolve_onedrive_backup_dir();
        if ($oneDriveDir === '') {
            return [
                'success' => false,
                'message' => 'OneDrive folder was not detected. Set STOCK_ONEDRIVE_BACKUP_DIR in backend/backup/backup_config.php.',
                'copied' => 0,
            ];
        }

        $dirCheck = stock_backup_prepare_writable_dir($oneDriveDir, 'OneDrive backup folder');
        if (empty($dirCheck['success'])) {
            return [
                'success' => false,
                'message' => $dirCheck['message'] ?? 'OneDrive backup folder is not writable.',
                'copied' => 0,
                'folder' => $oneDriveDir,
            ];
        }

        $prefix = stock_backup_safe_database_name($database);
        $files = glob(stock_backup_join_path($backupDir, $prefix . '_*.sql')) ?: [];
        $copied = 0;
        $failed = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $destination = stock_backup_join_path($oneDriveDir, basename($file));
            if (!is_file($destination) || filesize($destination) !== filesize($file)) {
                if (@copy($file, $destination)) {
                    @touch($destination, filemtime($file));
                    $copied++;
                } else {
                    $failed++;
                }
            }
        }

        $deleted = stock_prune_backups($oneDriveDir, $database, (int)STOCK_BACKUP_KEEP_LATEST_ONEDRIVE);
        $success = $failed === 0;

        return [
            'success' => $success,
            'message' => 'OneDrive sync complete. Copied: ' . $copied . ', failed: ' . $failed . ', old deleted: ' . $deleted . '.',
            'copied' => $copied,
            'failed' => $failed,
            'deleted_old' => $deleted,
            'folder' => $oneDriveDir,
        ];
    }
}

if (!function_exists('stock_create_database_backup')) {
    function stock_create_database_backup(mysqli $conn, string $database, string $backupDir, bool $manual = false): array
    {
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
                return ['success' => false, 'message' => 'Could not create backup folder.'];
            }
        }

        if (!is_writable($backupDir)) {
            return ['success' => false, 'message' => 'Backup folder is not writable.'];
        }

        $prefix = stock_backup_safe_database_name($database);
        $type = $manual ? 'manual' : 'auto';
        $filename = $prefix . '_' . $type . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = stock_backup_join_path($backupDir, $filename);

        $handle = fopen($filepath, 'wb');
        if (!$handle) {
            return ['success' => false, 'message' => 'Could not create backup file.'];
        }

        fwrite($handle, "-- =========================================\n");
        fwrite($handle, "-- Stock Inventory Management System Backup\n");
        fwrite($handle, "-- Database: `" . str_replace('`', '``', $database) . "`\n");
        fwrite($handle, "-- Backup type: " . strtoupper($type) . "\n");
        fwrite($handle, "-- Created at: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- =========================================\n\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET time_zone = \"+00:00\";\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        fwrite($handle, "CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $database) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n");
        fwrite($handle, "USE `" . str_replace('`', '``', $database) . "`;\n\n");

        $tablesResult = $conn->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        if (!$tablesResult) {
            fclose($handle);
            @unlink($filepath);
            return ['success' => false, 'message' => 'Could not read database tables: ' . $conn->error];
        }

        while ($tableRow = $tablesResult->fetch_array(MYSQLI_NUM)) {
            $table = $tableRow[0];
            $safeTable = str_replace('`', '``', $table);

            fwrite($handle, "\n-- --------------------------------------------------------\n");
            fwrite($handle, "-- Table structure for `$safeTable`\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$safeTable`;\n");

            $createResult = $conn->query("SHOW CREATE TABLE `$safeTable`");
            if (!$createResult) {
                fclose($handle);
                @unlink($filepath);
                return ['success' => false, 'message' => 'Could not export table structure for ' . $table . ': ' . $conn->error];
            }

            $createRow = $createResult->fetch_assoc();
            fwrite($handle, $createRow['Create Table'] . ";\n\n");

            $rows = $conn->query("SELECT * FROM `$safeTable`");
            if (!$rows) {
                fclose($handle);
                @unlink($filepath);
                return ['success' => false, 'message' => 'Could not export table data for ' . $table . ': ' . $conn->error];
            }

            if ($rows->num_rows > 0) {
                fwrite($handle, "-- Data for `$safeTable`\n");
                while ($row = $rows->fetch_assoc()) {
                    $columns = [];
                    $values = [];

                    foreach ($row as $column => $value) {
                        $columns[] = '`' . str_replace('`', '``', $column) . '`';
                        $values[] = stock_backup_sql_value($conn, $value);
                    }

                    fwrite($handle, "INSERT INTO `$safeTable` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n");
                }
                fwrite($handle, "\n");
            }
        }

        $triggersResult = $conn->query('SHOW TRIGGERS');
        if ($triggersResult && $triggersResult->num_rows > 0) {
            fwrite($handle, "\n-- --------------------------------------------------------\n");
            fwrite($handle, "-- Triggers\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");

            while ($trigger = $triggersResult->fetch_assoc()) {
                $triggerName = $trigger['Trigger'] ?? '';
                if ($triggerName === '') {
                    continue;
                }

                $safeTrigger = str_replace('`', '``', $triggerName);
                $createTriggerResult = $conn->query("SHOW CREATE TRIGGER `$safeTrigger`");
                if (!$createTriggerResult) {
                    continue;
                }

                $createTriggerRow = $createTriggerResult->fetch_assoc();
                $createTriggerSql = $createTriggerRow['SQL Original Statement']
                    ?? $createTriggerRow['Create Trigger']
                    ?? '';

                if ($createTriggerSql !== '') {
                    fwrite($handle, "DROP TRIGGER IF EXISTS `$safeTrigger`;\n");
                    fwrite($handle, "DELIMITER $$\n");
                    fwrite($handle, $createTriggerSql . "$$\n");
                    fwrite($handle, "DELIMITER ;\n\n");
                }
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($handle, "\n-- Backup completed successfully.\n");
        fclose($handle);

        $localDeleted = stock_prune_backups($backupDir, $database, (int)STOCK_BACKUP_KEEP_LATEST_LOCAL);
        $oneDriveResult = stock_sync_backup_to_onedrive($filepath, $database, $backupDir);

        $message = 'Backup created locally.';
        if ($localDeleted > 0) {
            $message .= ' ' . $localDeleted . ' old local backup(s) deleted.';
        }

        if (!empty($oneDriveResult['success']) && empty($oneDriveResult['skipped'])) {
            $message .= ' OneDrive copy completed.';
        } elseif (!empty($oneDriveResult['skipped'])) {
            $message .= ' ' . ($oneDriveResult['message'] ?? 'OneDrive sync skipped.');
        } else {
            $message .= ' Local backup is safe, but OneDrive copy failed: ' . ($oneDriveResult['message'] ?? 'Unknown OneDrive error.');
        }

        $status = [
            'success' => true,
            'backup_type' => $type,
            'local_file' => $filename,
            'local_path' => $filepath,
            'local_deleted_old' => $localDeleted,
            'onedrive_success' => !empty($oneDriveResult['success']) && empty($oneDriveResult['skipped']),
            'onedrive_skipped' => !empty($oneDriveResult['skipped']),
            'onedrive_message' => $oneDriveResult['message'] ?? '',
            'onedrive_path' => $oneDriveResult['path'] ?? '',
            'message' => $message,
        ];

        stock_backup_write_last_status($backupDir, $status);
        stock_backup_log($backupDir, $message);

        return [
            'success' => true,
            'message' => $message,
            'file' => $filename,
            'path' => $filepath,
            'onedrive' => $oneDriveResult,
            'local_deleted_old' => $localDeleted,
        ];
    }
}

if (!function_exists('stock_run_daily_auto_backup')) {
    function stock_run_daily_auto_backup(mysqli $conn, string $database, string $backupDir): void
    {
        $prefix = stock_backup_safe_database_name($database);
        $todayPattern = stock_backup_join_path($backupDir, $prefix . '_auto_' . date('Y-m-d') . '_*.sql');

        if (glob($todayPattern)) {
            return;
        }

        $result = stock_create_database_backup($conn, $database, $backupDir, false);
        if (empty($result['success'])) {
            stock_backup_log($backupDir, 'Automatic backup failed: ' . ($result['message'] ?? 'Unknown error.'));
        }
    }
}

if (!defined('STOCK_BACKUP_SKIP_AUTO') && isset($conn, $database) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
    stock_run_daily_auto_backup($conn, (string)$database, __DIR__ . '/../backups');
}
