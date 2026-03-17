<?php
session_start();

define('SITE_NAME', 'Omasyl');
define('WEDDING_DATE', '2026-06-20'); // Change to your wedding date (YYYY-MM-DD)
define('DB_PATH', __DIR__ . '/data/wedding.db');
define('UPLOAD_PATH', __DIR__ . '/uploads');
define('ADMIN_USER', 'admin');
define('ADMIN_PASS_PLAIN', 'chiandsyl2026'); // Change this password!

// Bank details (admin can change in admin panel later)
$bank_details = [
    'bank_name'    => 'Stanbic IBTC',
    'account_name' => 'Uchu Chioma',
    'account_no'   => '0037302983',
    'sort_code'    => '00-00-00',
];
// Contact for RSVP (names only shown; numbers used for tel: links)
define('RSVP_PHONE_EHI', '09028333290');
define('RSVP_PHONE_ONYINYE', '09028315081');
define('RSVP_PHONE_BECKY', '07030911452');
define('RSVP_PHONE_PRECIOUS', '08100175880');
define('BASE', ''); // No .php in URLs; use /register, /gifts, etc.

function format_gift_price($price) {
    if ($price === null || $price === '') return '';
    $digits = preg_replace('/[^0-9]/', '', $price);
    if ($digits !== '') {
        return '₦' . number_format((float) $digits, 0);
    }
    return $price;
}

require_once __DIR__ . '/includes/db.php';
