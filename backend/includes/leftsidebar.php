<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/security.php';
$role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
$user_role = stock_current_role();
$is_admin_user = stock_is_admin();
?>

<div class="left side-menu">
    <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Close navigation">&times;</button>
    <div class="sidebar-inner slimscrollleft">

        <!-- Sidebar Menu -->
        <div id="sidebar-menu">
            <ul>
                <li class="menu-title">Navigation</li>

                <!-- Dashboard -->
                <li class="has_sub">
                    <a href="/../stock-inventory-management-system/backend/dashboard.php" class="waves-effect">
                        <i class="mdi mdi-view-dashboard"></i> <span> Dashboard </span>
                    </a>
                </li>

                <!-- Manage Users: Only for Admin -->
                <?php if (stock_has_permission('users_manage')): ?>
                    <li class="has_sub">
                        <a href="javascript:void(0);" class="waves-effect">
                            <i class="mdi mdi-account-multiple"></i> <span> Manage Users</span>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul class="list-unstyled">
                            <li><a href="/../stock-inventory-management-system/backend/users/add.php">Add User</a></li>
                            <li><a href="/../stock-inventory-management-system/backend/users/manage.php">Manage Users</a></li>
                            
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Manage products -->
                <?php if (stock_has_permission('products_manage')): ?>
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
<i class="mdi mdi-layers-outline"></i>
                        <span> Products </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                            <li><a href="/../stock-inventory-management-system/backend/products/add_product.php">Add Products</a></li>
                            <!-- <li><a href="/../backend/cars/all_cars.php">Manage Cars</a></li> -->
                            <!-- <li><a href="/../backend/cars/add_make.php">Add Make</a></li> -->
                            <!-- <li><a href="/../backend/cars/add_model.php">Add Model</a></li> -->
                             <!-- <li><a href="/../backend/cars/add_years.php">Add Years</a></li> -->
                             <!-- <li><a href="/../backend/maintainance_and_repair/damage.php">Add Damage</a></li> -->
                             <!-- <li><a href="/../backend/maintainance_and_repair/service.php">Add servicing records</a></li> -->
                        <li><a href="/../stock-inventory-management-system/backend/products/view_products.php">Available Products</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- sales -->
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="mdi mdi-calendar"></i> <span> Sales </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                        <li><a href="/../stock-inventory-management-system/backend/sales/create.php">New Sale</a></li>
                        <!-- <li><a href="/backend/rentals/active.php">Active Rentals</a></li> -->
                        <!-- <li><a href="/backend/sales/store.php">Manage Sales</a></li> -->
                    </ul>
                </li>
                <!-- stock -->
                <?php if (stock_has_permission('stock_manage')): ?>
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
<i class="mdi mdi-warehouse"></i>
                            
                        </i> <span> Stocks </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                        <li><a href="/../stock-inventory-management-system/backend/stock/view_stock.php">view stocks</a></li>
                        <!-- <li><a href="/backend/customers/view_customers.php">Manage Customers</a></li> -->
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Customers -->
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="mdi mdi-account"></i> <span> Customers </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                        <li><a href="/../stock-inventory-management-system/backend/customers/add_customers.php">Add Customer</a></li>
                        <li><a href="/../stock-inventory-management-system/backend/customers/manage_customers.php">Manage Customers</a></li>

                    </ul>
                </li>

                <!-- Payments -->
                <!-- <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="mdi mdi-cash-multiple"></i> <span> Payments </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                        <li><a href="/backend/paying/add_payment_method.php">Add Payment</a></li>
                        <li><a href="/backend/paying/manage.php">Manage Payments</a></li>
                    </ul>
                </li> -->

                <!-- Locations -->
                <!-- <li class="has_sub"> -->
                    <!-- <a href="javascript:void(0);" class="waves-effect"> -->
                        <!-- <i class="mdi mdi-map-marker"></i> <span> Locations </span> -->
                        <!-- <span class="menu-arrow"></span> -->
                    <!-- </a> -->
                    <!-- <ul class="list-unstyled"> -->
                        <!-- <li><a href="/backend/locations/add.php">Add Location</a></li> -->
                        <!-- <li><a href="/backend/locations/manage.php">Manage Locations</a></li> -->
                    <!-- </ul> -->
                <!-- </li> -->

                <!-- Database Backups -->
                <?php if (stock_has_permission('backups_manage')): ?>
                <li class="has_sub">
                    <a href="javascript:void(0);" class="waves-effect">
                        <i class="mdi mdi-database"></i> <span> Backups </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="list-unstyled">
                        <li><a href="/../stock-inventory-management-system/backend/backup/index.php">Database Backups</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Reports (Admin only, also checks utype) -->
                <?php if (stock_has_permission('reports_view')): ?>
                    <li class="has_sub">
                        <a href="javascript:void(0);" class="waves-effect">
                            <i class="mdi mdi-chart-bar"></i> <span> Reports </span>
                            <span class="menu-arrow"></span>
                        </a>
                        <ul class="list-unstyled">
                            <li><a href="/../stock-inventory-management-system/backend/reports/sales_report.php">Sales Reports</a></li>
                            <!-- <li><a href="/backend/report/payment_report.php">Reven Reports</a></li> -->
                        </ul>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
        <!-- Sidebar End -->

        <div class="clearfix"></div>
    </div>
</div>
<div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>
