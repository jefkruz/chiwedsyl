<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$guestCount = (int) $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn();
$giftCount = (int) $pdo->query("SELECT COUNT(*) FROM gift_items")->fetchColumn();
$receiptCount = (int) $pdo->query("SELECT COUNT(*) FROM receipts")->fetchColumn();
$galleryCount = (int) $pdo->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn();

$sections = [
    [
        'href' => BASE . '/admin/guests',
        'title' => 'Guests &amp; QR',
        'count' => $guestCount,
        'count_label' => 'guests registered',
        'hint' => 'Check-in, QR codes, and invite list.',
    ],
    [
        'href' => BASE . '/admin/gifts',
        'title' => 'Gifts',
        'count' => $giftCount,
        'count_label' => 'gift items',
        'hint' => 'Edit catalogue and prices for the public shop.',
    ],
    [
        'href' => BASE . '/admin/receipts',
        'title' => 'Receipts',
        'count' => $receiptCount,
        'count_label' => 'receipts uploaded',
        'hint' => 'Review and manage guest uploads.',
    ],
    [
        'href' => BASE . '/admin/gallery',
        'title' => 'Gallery',
        'count' => $galleryCount,
        'count_label' => 'images live',
        'hint' => 'Upload, caption, and remove homepage gallery photos.',
    ],
];
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

        <p class="admin-dashboard-lead">Pick a section to manage. Counts update from your live data.</p>

        <section class="admin-dashboard" aria-label="Admin sections">
            <div class="admin-dashboard-grid">
                <?php foreach ($sections as $s): ?>
                    <a href="<?= htmlspecialchars($s['href']) ?>" class="admin-dashboard-card">
                        <span class="admin-dashboard-card-eyebrow">Open</span>
                        <h2 class="admin-dashboard-card-title"><?= $s['title'] ?></h2>
                        <p class="admin-dashboard-card-stat">
                            <span class="admin-dashboard-card-num"><?= $s['count'] ?></span>
                            <span class="admin-dashboard-card-label"><?= htmlspecialchars($s['count_label']) ?></span>
                        </p>
                        <p class="admin-dashboard-card-hint"><?= htmlspecialchars($s['hint']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</body>
</html>
