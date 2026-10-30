# Stock Inventory Management System

A PHP and MySQL system for a hardware shop to manage products, stock across warehouses, customers and credit payments, and sales with invoices and reports. It is in use by a hardware business.

## Features

- **Products and pricing**: categories, units, suppliers and a full price history
- **Stock**: stock levels per warehouse, stock adjustments and a movement log for every change
- **Sales**: point-of-sale entry, PDF invoices, and daily sales and stock reports
- **Customers**: customer accounts with credit sales and payment tracking
- **Reports**: sales reports on screen and as PDF with filters
- **Users**: admin-managed staff accounts, with a self-disabling setup account for first installation
- **Backups**: automatic daily and manual SQL backups, copied to a OneDrive folder with automatic pruning of old copies

## Security highlights

- Central security layer in `backend/includes/security.php`, with security headers
- Login throttling with a temporary lock after repeated failed attempts, and a 10-minute idle timeout
- Passwords hashed with `password_hash`; session ID regenerated at login
- CSRF tokens on state-changing forms
- Prepared statements for database queries and output escaped with `htmlspecialchars`
- Email-based password reset (PHPMailer over SMTP) that falls back to an on-screen link when no mail server is set up
- `.htaccess` rules block direct access to the database, backup, include and tools folders

## Tech stack

PHP 8 (mysqli) · MySQL / MariaDB · HTML · CSS · JavaScript · PHPMailer · FPDF/FPDI · Apache (XAMPP)

## Setup

Follow `INSTALLATION_GUIDE.txt`. In short: import `backend/database/stock_management_system.sql`, set the database details in `backend/includes/db.php`, open `backend/authentication/login.php`, sign in with the setup account in `CUSTOMER_DELIVERY_LOGIN.txt`, and create your real admin account. The setup account then disables itself.

> **Portfolio note.** This public copy contains no business data or backups. The logo and PDF letterhead are neutral placeholders. The code is source-available for review only; see `LICENSE.txt`.
