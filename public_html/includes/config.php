<?php
/*
 * Power Plumbing Services Ltd — site settings.
 * Edit the values marked CHANGE before going live.
 */
$config = [
    'site_name'     => 'Power Plumbing Services Ltd',
    'site_url'      => 'https://powerplumbing.co.ke',   // CHANGE if you use another domain
    'phone_display' => '0721 814 678',
    'phone_intl'    => '+254721814678',
    'whatsapp'      => '254721814678',                 // digits only, country code first
    'email'         => 'info@powerplumbing.top',       // shown on the site
    'notify_email'  => 'info@powerplumbing.top',       // where new quote requests are emailed
    'mail_from'     => 'info@powerplumbing.top',       // must be a mailbox on this hosting account
    'mail_from_name'=> 'Power Plumbing Services Ltd',
    'auto_reply'    => true,                           // email customers a "we got your request" note

    // Outgoing mail through the Hostinger mailbox (hPanel > Emails > info@ > Configuration)
    'smtp' => [
        'enabled' => true,
        'host'    => 'smtp.hostinger.com',
        'port'    => 465,
        'secure'  => 'ssl',                // 'ssl' for 465, 'tls' for 587
        'user'    => 'info@powerplumbing.top',
        'pass'    => 'CHANGE_ME',          // CHANGE: the info@ mailbox password
    ],
    'po_box'        => 'P.O. Box 8557-00100, Nairobi, Kenya',
    'kra_pin'       => '',                              // CHANGE: company KRA PIN, printed on invoices
    // Printed on invoices under "How to pay". CHANGE to the real details.
    'payment_details' => "M-Pesa Paybill: XXXXXX  Account: invoice number\nBank: XXXX Bank, Branch XXXX\nAccount name: Power Plumbing Services Ltd  Account no.: XXXXXXXXXX",

    'db' => [
        'driver' => 'mysql',          // 'mysql' on Hostinger
        'host'   => 'localhost',
        'name'   => 'u000000000_power',   // CHANGE: hPanel > Databases
        'user'   => 'u000000000_power',   // CHANGE
        'pass'   => 'CHANGE_ME',          // CHANGE
        'sqlite' => __DIR__ . '/../data/site.sqlite', // only used when driver = sqlite
    ],

    'max_upload_mb' => 8,     // per photo
    'max_video_mb'  => 60,    // per video (also raise upload_max_filesize in hPanel > PHP Configuration)
    'debug'         => false,
];

// Optional local overrides (not shipped): includes/config.local.php returning an array.
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/config.local.php');
}

return $config;
