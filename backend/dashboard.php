<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/security.php';

require_once __DIR__ . '/includes/db.php';

// Simple authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /../stock-inventory-management-system/backend/authentication/login.php");
    exit;
}

// Automatic daily database backup. Runs silently once per day.
require_once __DIR__ . '/backup/auto_backup.php';


// Fetch counts
$totalProducts = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];

$totalStock = $conn->query("SELECT IFNULL(SUM(quantity),0) AS total FROM stock")->fetch_assoc()['total'];

$lowStock = $conn->query("SELECT COUNT(*) AS total FROM stock WHERE quantity < 10")->fetch_assoc()['total'];

$totalCategories = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'];

$totalWarehouses = $conn->query("SELECT COUNT(*) AS total FROM warehouses")->fetch_assoc()['total'];

$totalCustomers = $conn->query("SELECT COUNT(*) AS total FROM customers")->fetch_assoc()['total'];

$totalSales = $conn->query("SELECT COUNT(*) AS total FROM sales")->fetch_assoc()['total'];

$totalRevenue = $conn->query("SELECT IFNULL(SUM(total),0) AS total FROM sales")->fetch_assoc()['total'];

$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];


// Low stock alert query
$lowStockQuery = $conn->query("
    SELECT p.name, s.quantity, w.name AS warehouse
    FROM stock s
    JOIN products p ON p.id = s.product_id
    JOIN warehouses w ON w.id = s.warehouse_id
    WHERE s.quantity <= 5
    ORDER BY s.quantity ASC
");


$page_title = "Dashboard";
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/leftsidebar.php';
?>
<div class="content-page">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Dashboard</h4>

            <?php if ($lowStockQuery && $lowStockQuery->num_rows > 0): ?>
                <div class="stock-dashboard-alert">
                    <div class="stock-dashboard-alert-top">
                        <div>
                            <strong>⚠ Low Stock Alert</strong>
                            <p>These products are running low and need attention.</p>
                        </div>

                        <?php if (stock_has_permission('stock_manage')): ?>
                        <a href="/../stock-inventory-management-system/backend/stock/view_stock.php">
                            View Stock
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="stock-dashboard-alert-items">
                        <?php while ($row = $lowStockQuery->fetch_assoc()): ?>
                            <div class="stock-dashboard-alert-item">
                                <div>
                                    <b><?= htmlspecialchars($row['name']); ?></b>
                                    <span><?= htmlspecialchars($row['warehouse']); ?></span>
                                </div>

                                <em><?= htmlspecialchars($row['quantity']); ?> left</em>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">

                <!-- Total products -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-cube-outline widget-one-icon"></i>

                        <div class="wigdet-one-content">
                            <p>Total Products</p>
                            <h2><?php echo $totalProducts; ?></h2>
                        </div>
                        <?php if (stock_has_permission('products_manage')): ?>
                        <a href="/../stock-inventory-management-system/backend/products/view_products.php" class="small-box-footer">Manage Products</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available stock -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-package-variant widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Available Stock</p>
                            <h2><?php echo $totalStock; ?></h2>
                        </div>
                        <?php if (stock_has_permission('stock_manage')): ?>
                        <a href="/../stock-inventory-management-system/backend/stock/view_stock.php" class="small-box-footer">View Available</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- low stock -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-alert-circle-outline widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Low Stocks</p>
                            <h2><?php echo $lowStock; ?></h2>
                        </div>
                        <?php if (stock_has_permission('stock_manage')): ?>
                        <a href="/../stock-inventory-management-system/backend/stock/view_stock.php" class="small-box-footer">View Low Stocks</a>
                        <?php endif; ?>
                        <span class="text-danger" style="font-size: 14px; font-weight: bold; display:block; margin:8px 12px;">
                            Red indicates extremely low stock
                        </span>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-account widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Total Customers</p>
                            <h2><?php echo $totalCustomers; ?></h2>
                        </div>
                        <a href="/../stock-inventory-management-system/backend/customers/manage_customers.php" class="small-box-footer">Manage Customers</a>
                    </div>
                </div>

                <!-- Total sales -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-calendar-clock widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Total Sales</p>
                            <h2><?php echo $totalSales; ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Total revenue -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-cash widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Total Revenue</p>
                            <h2><?php echo $totalRevenue; ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Total Users -->
                <div class="col-lg-3 col-md-6">
                    <div class="card-box widget-box-one">
                        <i class="mdi mdi-account-multiple widget-one-icon"></i>
                        <div class="wigdet-one-content">
                            <p>Total Users</p>
                            <h2><?php echo $totalUsers; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent sales Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-box">
                        <h4 class="header-title mb-3">Recent sales</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Customer</th>
                                        <th>Warehouse</th>
                                        <th>Sale Date</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php
                                    $recentSales = $conn->query("
                                        SELECT
                                            s.id,
                                            c.name AS customer_name,
                                            w.name AS warehouse,
                                            s.total,
                                            s.sale_date
                                        FROM sales s
                                        LEFT JOIN customers c ON s.customer_id = c.id
                                        LEFT JOIN warehouses w ON s.warehouse_id = w.id
                                        ORDER BY s.created_at DESC
                                        LIMIT 3
                                    ");

                                    $i = 1;
                                    while ($sale = $recentSales->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?= $i++; ?></td>
                                            <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                            <td><?= htmlspecialchars($sale['warehouse'] ?? '-'); ?></td>
                                            <td><?= htmlspecialchars($sale['sale_date']); ?></td>
                                            <td><?= number_format($sale['total'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- container -->
    </div> <!-- content -->
</div> <!-- content-page -->

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="/stock-inventory-management-system/backend/includes/js/dashboard.js?v=20260529"></script>