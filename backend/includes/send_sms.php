<?php
/*
 * Stock Inventory Management System
 * Copyright (c) 2026 Robert. All rights reserved.
 * Source published for portfolio review only. See LICENSE.txt.
 */
require_once __DIR__ . '/../vendor/autoload.php'; // Twilio autoload

use Twilio\Rest\Client;

function sendSMS($toNumber, $message) {
    $sid = 'YOUR_TWILIO_SID';
    $token = 'YOUR_TWILIO_AUTH_TOKEN';
    $fromNumber = 'YOUR_TWILIO_PHONE_NUMBER';

    $client = new Client($sid, $token);

    try {
        $client->messages->create(
            $toNumber, // e.g., +15555555555
            [
                'from' => $fromNumber,
                'body' => $message
            ]
        );
        return true;
    } catch (\Exception $e) {
        return "SMS could not be sent: " . $e->getMessage();
    }
}
