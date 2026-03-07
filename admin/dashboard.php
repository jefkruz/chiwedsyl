<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$guestCount = $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn();
$giftCount = $pdo->query("SELECT COUNT(*) FROM gift_items")->fetchColumn();
$receiptCount = $pdo->query("SELECT COUNT(*) FROM receipts")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Admin</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/guests">Guests &amp; QR</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/receipts">Receipts</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <h2>Overview</h2>
            <p><strong><?= (int) $guestCount ?></strong> guests registered</p>
            <p><strong><?= (int) $giftCount ?></strong> gift items</p>
            <p><strong><?= (int) $receiptCount ?></strong> receipts uploaded</p>
        </div>
    </div>
</body>
</html>
