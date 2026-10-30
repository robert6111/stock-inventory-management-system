<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
define('STOCK_BACKUP_SKIP_AUTO', true);
require_once __DIR__ . '/auto_backup.php';

$backupDir = __DIR__ . '/../backups';
$message = '';
$messageClass = '';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_now'])) {
    $result = stock_create_database_backup($conn, (string)$database, $backupDir, true);
    $message = $result['message'] ?? '';
    $messageClass = !empty($result['success']) ? 'success' : 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_onedrive'])) {
    $result = stock_sync_existing_backups_to_onedrive($backupDir, (string)$database);
    $message = $result['message'] ?? '';
    $messageClass = !empty($result['success']) ? 'success' : 'error';
    stock_backup_log($backupDir, $message);
}

$backupFiles = glob($backupDir . '/*.sql') ?: [];
usort($backupFiles, static function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

$oneDriveStatus = stock_backup_get_onedrive_diagnostics();
$oneDriveDir = $oneDriveStatus['path'] ?? stock_resolve_onedrive_backup_dir();
$oneDriveFiles = ($oneDriveDir !== '' && is_dir($oneDriveDir)) ? (glob(stock_backup_join_path($oneDriveDir, '*.sql')) ?: []) : [];
usort($oneDriveFiles, static function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});

$lastStatus = stock_backup_read_last_status($backupDir);
$page_title = 'Database Backups';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backups</title>
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/header.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/dashboard.css">
    <link rel="stylesheet" href="/../stock-inventory-management-system/backend/includes/css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <style>
        .backup-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        .backup-btn { border: 0; background: #2563eb; color: #fff; padding: 11px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .backup-btn:hover { background: #1d4ed8; }
        .backup-btn.secondary { background: #0f766e; }
        .backup-btn.secondary:hover { background: #115e59; }
        .backup-note { color: #64748b; font-size: 14px; }
        .backup-msg { padding: 12px 14px; border-radius: 8px; margin-bottom: 15px; }
        .backup-msg.success { background: #e8f7ee; color: #166534; border: 1px solid #bbf7d0; }
        .backup-msg.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .download-link { color: #2563eb; font-weight: 600; }
        .backup-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .backup-summary-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #fff; }
        .backup-summary-card h5 { margin: 0 0 8px; font-size: 15px; color: #0f172a; }
        .backup-summary-card p { margin: 4px 0; color: #475569; font-size: 14px; word-break: break-word; }
        .backup-status-ok { color: #166534; font-weight: 700; }
        .backup-status-warning { color: #92400e; font-weight: 700; }
        .backup-status-bad { color: #991b1b; font-weight: 700; }
        .backup-small-text { font-size: 13px; color: #64748b; }
    </style>
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
<?php
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';
?>

<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <h4 class="page-title">Database Backups</h4>

            <div class="card-box">
                <form method="post" class="backup-actions">
                    <button type="submit" name="backup_now" value="1" class="backup-btn">
                        <i class="fa fa-database"></i> Backup Now
                    </button>
                    <button type="submit" name="sync_onedrive" value="1" class="backup-btn secondary">
                        <i class="fa fa-cloud-upload-alt"></i> Sync Existing Backups to OneDrive
                    </button>
                    <span class="backup-note">Automatic backups are created once per day when the dashboard is opened. Local backups are kept, copied to OneDrive when available, and old backups are deleted automatically.</span>
                </form>

                <?php if ($message): ?>
                    <div class="backup-msg <?= htmlspecialchars($messageClass); ?>"><?= htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="backup-summary">
                    <div class="backup-summary-card">
                        <h5><i class="fa fa-folder"></i> Local Backup</h5>
                        <p><span class="backup-status-ok">Active</span></p>
                        <p>Folder: <?= htmlspecialchars($backupDir); ?></p>
                        <p>Files: <?= count($backupFiles); ?></p>
                        <p>Retention: latest <?= (int)STOCK_BACKUP_KEEP_LATEST_LOCAL; ?> backup files</p>
                    </div>

                    <div class="backup-summary-card">
                        <h5><i class="fa fa-cloud"></i> OneDrive Backup</h5>
                        <?php if (!STOCK_ONEDRIVE_BACKUP_ENABLED): ?>
                            <p><span class="backup-status-warning">Disabled</span></p>
                            <p>Enable it in backend/backup/backup_config.php</p>
                        <?php elseif ($oneDriveDir === ''): ?>
                            <p><span class="backup-status-bad">Not configured</span></p>
                            <p><?= htmlspecialchars($oneDriveStatus['message'] ?? 'Set STOCK_ONEDRIVE_BACKUP_DIR in backend/backup/backup_config.php'); ?></p>
                        <?php else: ?>
                            <p><span class="<?= !empty($oneDriveStatus['success']) ? 'backup-status-ok' : 'backup-status-bad'; ?>"><?= !empty($oneDriveStatus['success']) ? 'Ready and writable' : 'Folder not writable'; ?></span></p>
                            <p>Folder: <?= htmlspecialchars($oneDriveDir); ?></p>
                            <p><?= htmlspecialchars($oneDriveStatus['message'] ?? ''); ?></p>
                            <p>Files: <?= count($oneDriveFiles); ?></p>
                            <p>Retention: latest <?= (int)STOCK_BACKUP_KEEP_LATEST_ONEDRIVE; ?> backup files</p>
                        <?php endif; ?>
                    </div>

                    <div class="backup-summary-card">
                        <h5><i class="fa fa-clock"></i> Last Backup Status</h5>
                        <?php if (empty($lastStatus)): ?>
                            <p>No backup status recorded yet.</p>
                        <?php else: ?>
                            <p><?= htmlspecialchars($lastStatus['message'] ?? ''); ?></p>
                            <p class="backup-small-text">Checked: <?= htmlspecialchars($lastStatus['checked_at'] ?? ''); ?></p>
                            <?php if (!empty($lastStatus['local_file'])): ?>
                                <p class="backup-small-text">File: <?= htmlspecialchars($lastStatus['local_file']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Backup File</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($backupFiles)): ?>
                            <tr><td colspan="6">No backups found yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($backupFiles as $index => $file): ?>
                                <?php $name = basename($file); ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><?= htmlspecialchars($name); ?></td>
                                    <td><?= stripos($name, '_manual_') !== false ? 'Manual' : 'Automatic'; ?></td>
                                    <td><?= number_format(filesize($file) / 1024, 2); ?> KB</td>
                                    <td><?= date('Y-m-d H:i:s', filemtime($file)); ?></td>
                                    <td><a class="download-link" href="download.php?file=<?= urlencode($name); ?>">Download</a></td>
                                </tr>
                            <?php endforeach; ?>
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
