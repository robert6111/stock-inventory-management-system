<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

/*
 |--------------------------------------------------------------------------
 | Email settings
 |--------------------------------------------------------------------------
 | For local/offline XAMPP installations, password reset can still work by
 | showing the reset link on the screen. To also send emails, fill these SMTP
 | values with the customer's email settings.
 */
if (!defined('SMTP_HOST')) define('SMTP_HOST', '');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', '');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Stock Management System');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'tls');

function isEmailConfigured(): bool {
    return SMTP_HOST !== '' && SMTP_USERNAME !== '' && SMTP_PASSWORD !== '' && SMTP_FROM_EMAIL !== '';
}

function sendEmail($to, $subject, $body) {
    if (!isEmailConfigured()) {
        return 'Email is not configured. Use the reset link shown on the screen, or configure SMTP in backend/includes/send_email.php.';
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
        $mail->send();
        return true;
    } catch (Exception $e) {
        return 'Email could not be sent. Please check SMTP settings.';
    }
}
