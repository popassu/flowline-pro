<?php
/*
 * Copy this file to config.local.php ON THE SERVER ONLY and fill in real values.
 * config.local.php is git-ignored and overrides includes/config.php.
 * Never commit real passwords.
 */
return [
    'smtp' => [
        'pass' => 'your-info-mailbox-password',
    ],
    'db' => [
        'name' => 'u123456789_power',
        'user' => 'u123456789_power',
        'pass' => 'your-database-password',
    ],
    'kra_pin' => 'P000000000X',
    'payment_details' => "M-Pesa Paybill: XXXXXX  Account: invoice number\nBank: XXXX Bank, Branch XXXX\nAccount name: Power Plumbing Services Ltd  Account no.: XXXXXXXXXX",
];
