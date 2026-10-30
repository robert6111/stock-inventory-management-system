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

$page_title = 'Stock Movement History';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/leftsidebar.php';

$query = "
    SELECT
        sm.created_at,
        p.name AS product_name,
        w.name AS warehouse_name,
        sm.quantity,
        sm.type,
        sm.movement_type,
        sm.reference,
        sm.note,
        u.name AS user_name
    FROM stock_movements sm
    INNER JOIN products p ON p.id = sm.product_id
    INNER JOIN warehouses w ON w.id = sm.warehouse_id
    LEFT JOIN users u ON u.id = sm.user_id
    ORDER BY sm.created_at DESC, sm.id DESC
";
$result = $conn->query($query);
?>
<div class="content-page">
    <div class="content">
        <div class="container-fluid">
            <h4 class="page-title">Stock Movement History</h4>
            <div class="card-box">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Warehouse</th>
                                <th>Movement</th>
                                <th>Quantity</th>
                                <th>Reference</th>
                                <th>Note</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                    $type = strtoupper($row['type'] ?: $row['movement_type']);
                                    $qty = (float)$row['quantity'];
                                    $qtyText = ($type === 'OUT' ? '-' : '+') . number_format($qty, 2);
                                    $qtyClass = $type === 'OUT' ? 'text-danger' : 'text-success';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= htmlspecialchars($row['warehouse_name']); ?></td>
                                    <td><strong><?= htmlspecialchars($type); ?></strong></td>
                                    <td><span class="<?= $qtyClass; ?>"><?= htmlspecialchars($qtyText); ?></span></td>
                                    <td><?= htmlspecialchars($row['reference'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['note'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($row['user_name'] ?? 'System'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center">No stock movements found</td></tr>
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
