<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
/**
 * Central security helpers for the Stock Inventory Management System.
 * This file is intentionally small and framework-free so the existing system
 * keeps the same UI and workflow while gaining safer sessions, CSRF protection,
 * and server-side role checks.
 */

if (!function_exists('stock_start_secure_session')) {
    function stock_start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        // Unique per-app cookie: sibling apps on this host must not share a session.
        session_name('stock_inventory_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}

stock_start_secure_session();

// Public error display is disabled for customer delivery.

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

error_reporting(0);
ini_set('display_errors', '0');

if (!function_exists('stock_app_base_path')) {
    function stock_app_base_path(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $marker = '/backend/';
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            $base = substr($script, 0, $pos);
            return $base === '' ? '' : rtrim($base, '/');
        }

        return '/stock-inventory-management-system';
    }
}

if (!function_exists('stock_url')) {
    function stock_url(string $path = ''): string
    {
        $base = stock_app_base_path();
        $path = ltrim($path, '/');
        return $base . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('stock_current_role')) {
    function stock_current_role(): string
    {
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        if (in_array($role, ['admin', 'manager', 'staff'], true)) {
            return $role;
        }

        // Backward compatibility with older sessions that used role_id.
        if ((int)($_SESSION['role_id'] ?? 0) === 1) {
            return 'admin';
        }

        return '';
    }
}

if (!function_exists('stock_is_admin')) {
    function stock_is_admin(): bool
    {
        return stock_current_role() === 'admin';
    }
}

if (!function_exists('stock_has_permission')) {
    function stock_has_permission(string $permission): bool
    {
        $role = stock_current_role();
        $permissions = [
            'users_manage'     => ['admin'],
            'backups_manage'   => ['admin'],
            'reports_view'     => ['admin'],
            'products_manage'  => ['admin', 'manager'],
            'products_delete'  => ['admin'],
            'stock_manage'     => ['admin', 'manager'],
            'sales_manage'     => ['admin', 'manager', 'staff'],
            'sales_delete'     => ['admin', 'manager'],
            'customers_manage' => ['admin', 'manager', 'staff'],
            'customers_delete' => ['admin', 'manager'],
            'profile_manage'   => ['admin', 'manager', 'staff'],
            'view_only'        => ['admin', 'manager', 'staff'],
        ];

        return in_array($role, $permissions[$permission] ?? [], true);
    }
}

if (!function_exists('stock_redirect')) {
    function stock_redirect(string $path): void
    {
        header('Location: ' . stock_url($path));
        exit;
    }
}

if (!function_exists('stock_forbidden')) {
    function stock_forbidden(string $message = 'You are not allowed to access this page.'): void
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f4f6f8;padding:40px}.box{max-width:520px;margin:auto;background:white;padding:25px;border-radius:10px;box-shadow:0 8px 25px rgba(0,0,0,.08)}a{color:#2563eb}</style>';
        echo '<link rel="stylesheet" media="print" href="/stock-inventory-management-system/backend/includes/css/print.css">';
        echo '</head><body><div class="box"><h2>Access Denied</h2><p>' . e($message) . '</p><p><a href="' . e(stock_url('backend/dashboard.php')) . '">Go to dashboard</a></p></div></body></html>';
        exit;
    }
}

if (!function_exists('stock_require_login')) {
    function stock_require_login(): void
    {
        if (empty($_SESSION['user_id'])) {
            stock_redirect('backend/authentication/login.php');
        }

        $timeout = 600; // 10 minutes of inactivity
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - (int)$_SESSION['LAST_ACTIVITY'] > $timeout)) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
            }
            session_destroy();
            stock_redirect('backend/authentication/login.php?timeout=1');
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

if (!function_exists('stock_require_permission')) {
    function stock_require_permission(string $permission): void
    {
        stock_require_login();
        if (!stock_has_permission($permission)) {
            stock_forbidden();
        }
    }
}

if (!function_exists('stock_csrf_token')) {
    function stock_csrf_token(): string
    {
        if (empty($_SESSION['stock_csrf_token'])) {
            $_SESSION['stock_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['stock_csrf_token'];
    }
}

if (!function_exists('stock_csrf_input')) {
    function stock_csrf_input(): string
    {
        return '<input type="hidden" name="stock_csrf_token" value="' . e(stock_csrf_token()) . '">';
    }
}

if (!function_exists('stock_validate_csrf')) {
    function stock_validate_csrf(): void
    {
        $submitted = (string)($_POST['stock_csrf_token'] ?? '');
        $expected = (string)($_SESSION['stock_csrf_token'] ?? '');
        if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            exit('Security check failed. Please go back, refresh the page, and try again.');
        }
    }
}

if (!function_exists('stock_relative_script')) {
    function stock_relative_script(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $pos = strpos($script, '/backend/');
        return $pos === false ? $script : substr($script, $pos);
    }
}

if (!function_exists('stock_starts_with')) {
    function stock_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('stock_enforce_route_access')) {
    function stock_enforce_route_access(): void
    {
        $path = stock_relative_script();
        if ($path === '') {
            return;
        }

        $public = [
            '/backend/authentication/login.php',
            '/backend/authentication/register.php',
            '/backend/authentication/logout.php',
            '/backend/admin/forgot_password.php',
            '/backend/admin/reset_password.php',
        ];

        if (in_array($path, $public, true)) {
            return;
        }

        if (!stock_starts_with($path, '/backend/')) {
            return;
        }

        stock_require_login();

        $exactRules = [
            '/backend/users/add.php'             => 'users_manage',
            '/backend/users/manage.php'          => 'users_manage',
            '/backend/backup/index.php'          => 'backups_manage',
            '/backend/backup/download.php'       => 'backups_manage',
            '/backend/products/add_product.php'  => 'products_manage',
            '/backend/products/view_products.php' => 'products_manage',
            '/backend/products/delete_product.php' => 'products_delete',
            '/backend/stock/view_stock.php' => 'stock_manage',
            '/backend/stock/stock_movement.php' => 'stock_manage',
            '/backend/stock/stock_adjustment.php' => 'stock_manage',
            '/backend/sales/processing_sales.php' => 'sales_manage',
            '/backend/sales/delete.php'          => 'sales_delete',
            '/backend/reports/sales_report.php'  => 'reports_view',
            '/backend/reports/sales_report_pdf.php' => 'reports_view',
            '/backend/customers/delete_customer.php' => 'customers_delete',
        ];

        if (isset($exactRules[$path])) {
            stock_require_permission($exactRules[$path]);
        }
    }
}

if (!defined('STOCK_DISABLE_AUTO_ACCESS')) {
    stock_enforce_route_access();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('STOCK_DISABLE_CSRF')) {
    stock_validate_csrf();
}

// Automatically add CSRF tokens to existing POST forms without changing page design.
if (!defined('STOCK_DISABLE_CSRF_INJECTION') && PHP_SAPI !== 'cli') {
    stock_csrf_token();
    ob_start(static function (string $buffer): string {
        if (stripos($buffer, '<html') === false || stripos($buffer, '<form') === false) {
            return $buffer;
        }

        return preg_replace_callback('/<form\b([^>]*)>/i', static function (array $matches): string {
            $attrs = $matches[1];
            if (!preg_match('/\bmethod\s*=\s*(["\'])?post\1/i', $attrs)) {
                return $matches[0];
            }
            if (stripos($matches[0], 'stock-csrf-injected') !== false) {
                return $matches[0];
            }
            return '<form' . $attrs . ' data-stock-csrf-injected="1">' . stock_csrf_input();
        }, $buffer) ?? $buffer;
    });
}
?>
