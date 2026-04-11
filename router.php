<?php
// Router for PHP built-in server: php -S localhost:8000 router.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // serve the file as-is
}

$routes = [
    '/' => '/index.php',
    '/index' => '/index.php',
    '/register' => '/register.php',
    '/qr-image' => '/qr-image.php',
    '/gallery' => '/gallery.php',
    '/gifts' => '/gifts.php',
    '/well-wishes' => '/well-wishes.php',
    '/admin' => '/admin/index.php',
    '/admin/dashboard' => '/admin/dashboard.php',
    '/admin/guests' => '/admin/guests.php',
    '/admin/scan' => '/admin/scan.php',
    '/admin/guest-card' => '/admin/guest-card.php',
    '/admin/guest-edit' => '/admin/guest-edit.php',
    '/admin/gifts' => '/admin/gifts.php',
    '/admin/gift-edit' => '/admin/gift-edit.php',
    '/admin/receipts' => '/admin/receipts.php',
    '/admin/well-wishes' => '/admin/well-wishes.php',
    '/admin/gallery' => '/admin/gallery.php',
    '/admin/logout' => '/admin/logout.php',
];

if (isset($routes[$uri])) {
    $_SERVER['SCRIPT_NAME'] = $routes[$uri];
    require __DIR__ . $routes[$uri];
    return true;
}

// Admin with query string (e.g. /admin/gift-edit?id=1)
if (preg_match('#^/admin/gift-edit(?:\?|$)#', $uri)) {
    $_SERVER['SCRIPT_NAME'] = '/admin/gift-edit.php';
    require __DIR__ . '/admin/gift-edit.php';
    return true;
}

http_response_code(404);
echo '404 Not Found';
