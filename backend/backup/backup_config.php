<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
/**
 * Backup configuration for the Stock Inventory Management System.
 *
 * OneDrive backup method used here:
 * - The system creates the normal local .sql backup first.
 * - Then it copies the same backup into your OneDrive synced folder.
 * - The official OneDrive desktop app uploads that folder to the cloud automatically.
 *
 * IMPORTANT:
 * If automatic OneDrive detection does not work on your computer/server, set
 * STOCK_ONEDRIVE_BACKUP_DIR manually to your OneDrive backup folder path.
 *
 * Windows examples:
 * define('STOCK_ONEDRIVE_BACKUP_DIR', 'C:\\Users\\Admin\\OneDrive\\Stock Inventory Backups');
 * define('STOCK_ONEDRIVE_BACKUP_DIR', 'C:\\Users\\Admin\\OneDrive - Company Name\\Stock Inventory Backups');
 *
 * If you see "OneDrive backup folder is not writable", create the folder manually,
 * make sure OneDrive is signed in, and run Apache from XAMPP Control Panel as the
 * same Windows user instead of running Apache as a Windows Service.
 */

// Set to false if you want to temporarily stop OneDrive copies but keep local backups.
if (!defined('STOCK_ONEDRIVE_BACKUP_ENABLED')) {
    define('STOCK_ONEDRIVE_BACKUP_ENABLED', true);
}

// Leave empty for automatic OneDrive folder detection using Windows environment variables.
// If auto detection fails, put the full OneDrive backup folder path here.
if (!defined('STOCK_ONEDRIVE_BACKUP_DIR')) {
    define('STOCK_ONEDRIVE_BACKUP_DIR', '');
}

// Used only when STOCK_ONEDRIVE_BACKUP_DIR is empty and the system auto-detects the base OneDrive folder.
if (!defined('STOCK_ONEDRIVE_BACKUP_SUBFOLDER')) {
    define('STOCK_ONEDRIVE_BACKUP_SUBFOLDER', 'Stock Inventory Management System Backups');
}

// Keep only the latest N local backup files for this database.
if (!defined('STOCK_BACKUP_KEEP_LATEST_LOCAL')) {
    define('STOCK_BACKUP_KEEP_LATEST_LOCAL', 30);
}

// Keep only the latest N OneDrive backup files for this database.
if (!defined('STOCK_BACKUP_KEEP_LATEST_ONEDRIVE')) {
    define('STOCK_BACKUP_KEEP_LATEST_ONEDRIVE', 30);
}
