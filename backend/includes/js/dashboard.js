(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function getSidebar() {
        return document.querySelector('.left.side-menu');
    }

    function getOverlay() {
        return document.getElementById('sidebarOverlay');
    }

    function getMenuButton() {
        return document.getElementById('mobileMenuToggle') || document.querySelector('.mobile-menu-toggle');
    }

    function setMenuButtonState(isOpen) {
        var menuButton = getMenuButton();
        if (!menuButton) return;

        menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menuButton.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');

        var icon = menuButton.querySelector('.hamburger-icon');
        if (icon) {
            icon.textContent = isOpen ? '×' : '☰';
        }
    }

    function openSidebarMenu() {
        var sidebar = getSidebar();
        if (!sidebar) return;

        sidebar.classList.add('active');
        document.body.classList.add('sidebar-open');

        var overlay = getOverlay();
        if (overlay) overlay.classList.add('active');

        setMenuButtonState(true);
    }

    function closeSidebarMenu() {
        var sidebar = getSidebar();
        if (sidebar) sidebar.classList.remove('active');

        document.body.classList.remove('sidebar-open');

        var overlay = getOverlay();
        if (overlay) overlay.classList.remove('active');

        setMenuButtonState(false);
    }

    function toggleSidebarMenu() {
        var sidebar = getSidebar();
        if (!sidebar) return;

        if (sidebar.classList.contains('active') || document.body.classList.contains('sidebar-open')) {
            closeSidebarMenu();
        } else {
            openSidebarMenu();
        }
    }

    function toggleDropdown() {
        var menu = document.getElementById('profileMenu');
        if (!menu) return;
        menu.classList.toggle('show');
        menu.style.display = menu.classList.contains('show') ? 'block' : 'none';
    }

    window.toggleSidebarMenu = toggleSidebarMenu;
    window.closeSidebarMenu = closeSidebarMenu;
    window.openSidebarMenu = openSidebarMenu;
    window.toggleDropdown = toggleDropdown;

    function setupNavigation() {
        var menuButton = getMenuButton();
        var closeButton = document.getElementById('sidebarCloseBtn');
        var overlay = getOverlay();

        if (menuButton && !menuButton.dataset.navReady) {
            menuButton.dataset.navReady = '1';
            menuButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleSidebarMenu();
            }, false);
        }

        if (closeButton && !closeButton.dataset.navReady) {
            closeButton.dataset.navReady = '1';
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeSidebarMenu();
            }, false);
        }

        if (overlay && !overlay.dataset.navReady) {
            overlay.dataset.navReady = '1';
            overlay.addEventListener('click', function () {
                closeSidebarMenu();
            }, false);
        }

        var submenuButtons = document.querySelectorAll('#sidebar-menu li.has_sub > a[href^="javascript"]');
        for (var i = 0; i < submenuButtons.length; i++) {
            var link = submenuButtons[i];
            if (link.dataset.submenuReady) continue;
            link.dataset.submenuReady = '1';

            link.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var parent = this.closest ? this.closest('li.has_sub') : null;
                if (!parent) return;

                var openItems = document.querySelectorAll('#sidebar-menu li.has_sub.open');
                for (var j = 0; j < openItems.length; j++) {
                    if (openItems[j] !== parent) openItems[j].classList.remove('open');
                }

                parent.classList.toggle('open');
            }, false);
        }

        var realLinks = document.querySelectorAll('#sidebar-menu a[href]:not([href^="javascript"])');
        for (var k = 0; k < realLinks.length; k++) {
            var realLink = realLinks[k];
            if (realLink.dataset.realLinkReady) continue;
            realLink.dataset.realLinkReady = '1';
            realLink.addEventListener('click', function () {
                if (window.innerWidth <= 1199) closeSidebarMenu();
            }, false);
        }
    }

    ready(setupNavigation);

    document.addEventListener('click', function (event) {
        var menu = document.getElementById('profileMenu');
        var insideProfile = event.target.closest ? event.target.closest('.profile-dropdown') : null;
        if (menu && !insideProfile) {
            menu.classList.remove('show');
            menu.style.display = 'none';
        }

        var insideSidebar = event.target.closest ? event.target.closest('.left.side-menu') : null;
        var insideToggle = event.target.closest ? event.target.closest('#mobileMenuToggle, .mobile-menu-toggle') : null;
        if (document.body.classList.contains('sidebar-open') && !insideSidebar && !insideToggle) {
            closeSidebarMenu();
        }
    }, false);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebarMenu();
            var menu = document.getElementById('profileMenu');
            if (menu) {
                menu.classList.remove('show');
                menu.style.display = 'none';
            }
        }
    }, false);

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1199) {
            closeSidebarMenu();
            var openItems = document.querySelectorAll('#sidebar-menu li.has_sub.open');
            for (var i = 0; i < openItems.length; i++) {
                openItems[i].classList.remove('open');
            }
        }
    }, false);
})();
