<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/security.php';
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Stock Inventory System'); ?></title>
   <link rel="stylesheet" href="/stock-inventory-management-system/backend/includes/css/header.css">
<link rel="stylesheet" href="/stock-inventory-management-system/backend/includes/css/dashboard.css">
<link rel="stylesheet" href="/stock-inventory-management-system/backend/includes/css/common.css">
<link rel="stylesheet" href="/stock-inventory-management-system/backend/includes/css/responsive-fixes.css">
    <!-- FontAwesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">


    <link rel="icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="shortcut icon" type="image/png" href="/stock-inventory-management-system/backend/includes/images/logo.png?v=7">
    <link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">
</head>
<body>
    <div class="page-loader">
        <span class="loader"></span>
    </div>

   <header class="topbar">
    <button type="button" id="mobileMenuToggle" class="mobile-menu-toggle" aria-label="Open navigation" aria-expanded="false">
        <span class="hamburger-icon" aria-hidden="true">☰</span>
    </button>

    <div class="topbar-left">
    <a href="/stock-inventory-management-system/backend/dashboard.php" class="logo">
        <img 
            class="topbar-logo-img"
            src="/stock-inventory-management-system/backend/includes/images/logo.png"
            alt="Logo"
            style="position:static!important;width:auto!important;height:auto!important;max-width:150px!important;max-height:55px!important;object-fit:contain!important;display:block!important;margin:0!important;padding:0!important;"
        >
    </a>
</div>
        <div class="topbar-right">
            <div class="profile-dropdown">
                <!-- Profile icon using FontAwesome -->
                <i class="fa-solid fa-user profile-pic" onclick="toggleDropdown()"></i>

                <!-- Dynamic username from session -->
                <span class="profile-name">
                    <?php 
                    if (isset($_SESSION['name'])) {
                        echo htmlspecialchars($_SESSION['name']);
                    } else {
                        echo "Guest";
                    }
                    ?>
                </span>

                <div class="dropdown-menu" id="profileMenu">
                    <a href="/stock-inventory-management-system/backend/profile.php">Profile</a>
                    <a href="/stock-inventory-management-system/backend/admin/change_password.php">Change Password</a>
                    <a href="/stock-inventory-management-system/backend/authentication/logout.php">Logout</a>
                </div>
            </div>
        </div>
    </header>


    <script>
    // Universal navigation fallback: works even if icon fonts fail or browser cache is strict.
    (function () {
        function ready(fn) {
            if (document.readyState !== 'loading') { fn(); }
            else { document.addEventListener('DOMContentLoaded', fn); }
        }

        function sidebar() { return document.querySelector('.left.side-menu'); }
        function overlay() { return document.getElementById('sidebarOverlay'); }
        function button() { return document.getElementById('mobileMenuToggle') || document.querySelector('.mobile-menu-toggle'); }

        function setButtonState(open) {
            var btn = button();
            if (!btn) return;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            var icon = btn.querySelector('.hamburger-icon');
            if (icon) icon.textContent = open ? '×' : '☰';
        }

        window.openSidebarMenu = function () {
            var side = sidebar();
            if (!side) return;
            side.classList.add('active');
            document.body.classList.add('sidebar-open');
            var ov = overlay();
            if (ov) ov.classList.add('active');
            setButtonState(true);
        };

        window.closeSidebarMenu = function () {
            var side = sidebar();
            if (side) side.classList.remove('active');
            document.body.classList.remove('sidebar-open');
            var ov = overlay();
            if (ov) ov.classList.remove('active');
            setButtonState(false);
        };

        window.toggleSidebarMenu = function () {
            var side = sidebar();
            if (!side) return;
            if (side.classList.contains('active') || document.body.classList.contains('sidebar-open')) {
                window.closeSidebarMenu();
            } else {
                window.openSidebarMenu();
            }
        };

        window.toggleDropdown = function () {
            var menu = document.getElementById('profileMenu');
            if (!menu) return;
            menu.classList.toggle('show');
            menu.style.display = menu.classList.contains('show') ? 'block' : 'none';
        };

        ready(function () {
            var btn = button();
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var ov = overlay();

            if (btn && !btn.dataset.navReady) {
                btn.dataset.navReady = '1';
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.toggleSidebarMenu();
                }, false);
            }

            if (closeBtn && !closeBtn.dataset.navReady) {
                closeBtn.dataset.navReady = '1';
                closeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.closeSidebarMenu();
                }, false);
            }

            if (ov && !ov.dataset.navReady) {
                ov.dataset.navReady = '1';
                ov.addEventListener('click', function () { window.closeSidebarMenu(); }, false);
            }

            var submenuLinks = document.querySelectorAll('#sidebar-menu li.has_sub > a[href^="javascript"]');
            for (var i = 0; i < submenuLinks.length; i++) {
                if (submenuLinks[i].dataset.submenuReady) continue;
                submenuLinks[i].dataset.submenuReady = '1';
                submenuLinks[i].addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var parent = this.closest ? this.closest('li.has_sub') : null;
                    if (!parent) return;
                    var openItems = document.querySelectorAll('#sidebar-menu li.has_sub.open');
                    for (var j = 0; j < openItems.length; j++) {
                        if (openItems[j] !== parent) openItems[j].classList.remove('open');
                    }
                    parent.classList.toggle('open');
                }, false);
            }

            document.addEventListener('click', function (e) {
                var profile = e.target.closest ? e.target.closest('.profile-dropdown') : null;
                var menu = document.getElementById('profileMenu');
                if (menu && !profile) {
                    menu.classList.remove('show');
                    menu.style.display = 'none';
                }
            }, false);
        });
    })();
    </script>
    <script src="/stock-inventory-management-system/backend/includes/js/dashboard.js?v=20260529"></script>
