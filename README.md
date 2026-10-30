# Stock Inventory Management System

A web system for a hardware shop: products and prices, stock across several warehouses, customers buying on credit, and sales with invoices and reports. It is in use by a hardware business.

## How it works

```
Product added ──► stock received (IN) ──► warehouse stock level
                                               │
Sale ──► lock stock row ──► enough stock? ──► no  ──► sale cancelled, nothing saved
                                  │
                                  └── yes ──► stock reduced ──► movement logged (OUT) ──► invoice (PDF)
                                                                                     └──► customer balance
Adjustment ──► movement logged (IN / OUT)      Price change ──► price history
```

**Every stock change leaves a trail.** Stock is never edited silently: each sale, deleted sale and adjustment writes a row to `stock_movements` recording the product, warehouse, user, direction (`IN` or `OUT`), quantity and a reference. Adjustments that would take stock below zero are refused. Every price change is saved in `price_history` with the old price, the new price and who changed it.

**Stock can't go below zero, even with two cashiers.** A sale runs inside one database transaction. For each item the stock row is locked with `SELECT … FOR UPDATE`, the quantity is checked, then reduced and logged. If any item is short, the whole sale is rolled back. Because the row is locked, two people selling the last unit at the same moment can't both succeed. Deleting a sale returns the stock the same way.

**Customer credit** is calculated live as total sales minus total payments for that customer, so the balance is always consistent with the records.

**Daily reports** come from two database views, `daily_sales_report` and `daily_stock_report`, which group sales and movements by day.

## Security design

All protection lives in `backend/includes/security.php`, loaded by every page:

- **Hardened sessions:** HttpOnly and SameSite cookies (Secure over HTTPS), a new session ID at login, and logout after 10 minutes of inactivity.
- **Login lockout:** 5 failed attempts lock the login for 15 minutes.
- **Role-based access by URL:** three roles (admin, manager, staff) and a permission map for every action. Staff can sell and manage customers but cannot delete sales, change stock or prices, or open reports, backups or user management, even by typing the URL directly.
- **CSRF protection:** a 32-byte random token is checked on every form submission with `hash_equals`.
- **No self-service password reset.** Only an admin can reset a password, from Manage Users, or someone with access to the server can use `tools/reset_password_cli.php`, which refuses to run from a browser. This removes a common way of taking over accounts.
- **Self-disabling setup account:** the first-login setup account can only create the real admin, and is switched off as soon as it has.
- **Prepared statements** for queries, output escaped with `htmlspecialchars`, and security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`).
- **`.htaccess` rules** block direct access to the database, backup, include and tools folders.

## Backups

One automatic SQL backup is made per day, and admins can make more by hand. Each backup is stored locally, then copied into the computer's OneDrive folder, which the system finds automatically, so it reaches the cloud. The newest 30 copies are kept in each place and older ones are deleted.

## Database design

| Area | Tables |
|---|---|
| Catalogue | `products`, `categories`, `units`, `suppliers`, `price_history` |
| Stock | `warehouses`, `stock`, `stock_movements` |
| Sales and credit | `sales`, `sales_items`, `customers`, `payments` |
| Users | `users` |
| Reports (views) | `daily_sales_report`, `daily_stock_report` |

## Project structure

```
backend/includes/        security.php (security layer), db.php, PDF and email helpers
backend/authentication/  Login and logout
backend/admin/           Change password (self-service reset is disabled)
backend/products/        Products and prices
backend/stock/           Stock levels, adjustments and the movement log
backend/sales/           Point of sale, invoices and deleting sales
backend/customers/       Customers and credit payments
backend/reports/         Sales reports on screen and as PDF
backend/backup/          Daily, manual and OneDrive backups (admin only)
backend/users/           User management (admin only)
tools/                   Command-line password reset (cannot run from a browser)
```

## Tech stack

PHP 8 (mysqli) · MySQL / MariaDB · HTML · CSS · JavaScript · FPDF/FPDI · PHPMailer · Apache (XAMPP)

## Setup

Full steps are in `INSTALLATION_GUIDE.txt`. In short:

1. Create a database called `stock_management_system` and import `backend/database/stock_management_system.sql`.
2. Set the database details in `backend/includes/db.php`.
3. Copy the project into XAMPP's `htdocs` and open `backend/authentication/login.php`.
4. Sign in with the setup account in `CUSTOMER_DELIVERY_LOGIN.txt` and create your real admin. The setup account then switches itself off.

> **Portfolio note.** This public copy contains no business data or backups. The logo and PDF letterhead are neutral placeholders. The code is published for review only; see `LICENSE.txt`.

## Author

Robert Baraka Kunenga, Computer Engineering, OSTIM Technical University · [github.com/robert6111](https://github.com/robert6111)
