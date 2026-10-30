Automatic backup module

What it does
- dashboard.php includes backend/backup/auto_backup.php
- one automatic .sql backup is created per day in backend/backups
- manual backups can be created from backend/backup/index.php
- every successful backup is first kept locally in backend/backups
- the same backup is then copied to OneDrive when OneDrive backup is enabled/configured
- old local backups are pruned, keeping the latest configured number
- old OneDrive backups are pruned, keeping the latest configured number
- backend/backups/.htaccess blocks direct web access; download goes through backend/backup/download.php

OneDrive setup
1. Install and sign in to the OneDrive desktop app on the computer/server running XAMPP.
2. Open backend/backup/backup_config.php.
3. Leave STOCK_ONEDRIVE_BACKUP_DIR empty for automatic detection, or set it manually.

Manual Windows examples:
define('STOCK_ONEDRIVE_BACKUP_DIR', 'C:\\Users\\Admin\\OneDrive\\Stock Inventory Backups');
define('STOCK_ONEDRIVE_BACKUP_DIR', 'C:\\Users\\Admin\\OneDrive - Company Name\\Stock Inventory Backups');

Retention settings
- STOCK_BACKUP_KEEP_LATEST_LOCAL controls how many local .sql backups to keep.
- STOCK_BACKUP_KEEP_LATEST_ONEDRIVE controls how many OneDrive .sql backups to keep.

Important note
This uses the OneDrive synced folder method. PHP copies the backup into the OneDrive folder, then the OneDrive desktop app uploads it to the cloud automatically.

TROUBLESHOOTING: "OneDrive backup folder is not writable"
----------------------------------------------------------
This means the local backup was created, but PHP/Apache could not write into the OneDrive folder.

Fix checklist:
1. Confirm OneDrive is installed, signed in, and syncing on the same Windows user account running XAMPP.
2. Create the backup folder manually inside OneDrive, for example:
   C:\Users\Robert\OneDrive\Stock Inventory Backups
3. Open backend/backup/backup_config.php and set the exact path:
   define('STOCK_ONEDRIVE_BACKUP_DIR', 'C:\\Users\\Robert\\OneDrive\\Stock Inventory Backups');
4. In File Explorer, right-click the folder > Properties > Security, and make sure your Windows user has Write permission.
5. If Apache is running as a Windows Service, stop the service and run Apache from the XAMPP Control Panel as the logged-in Windows user. OneDrive folders are user-profile folders, and Apache running as LocalSystem may not be able to write there.
6. If Windows Security Controlled Folder Access is enabled, allow Apache/XAMPP or temporarily test with it disabled.
7. Open the system backup page again. It will show a clearer writable/not-writable diagnostic message.

Important: Local backups still remain available in backend/backups/ even if OneDrive writing fails.
