<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$receipts = $pdo->query("
    SELECT r.*, g.title AS gift_title
    FROM receipts r
    LEFT JOIN gift_items g ON g.id = r.gift_item_id
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipts — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Uploaded receipts</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/guests">Guests</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/admin/well-wishes">Well wishes</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <div class="table-wrap">
                <table class="responsive-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Guest</th>
                            <th>Email</th>
                            <th>Gift</th>
                            <th>Message</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receipts as $r): ?>
                            <tr>
                                <td data-label="Date"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></td>
                                <td data-label="Guest"><?= htmlspecialchars($r['guest_name']) ?></td>
                                <td data-label="Email"><?= htmlspecialchars($r['guest_email']) ?></td>
                                <td data-label="Gift"><?= $r['gift_title'] ? htmlspecialchars($r['gift_title']) : '—' ?></td>
                                <td data-label="Message"><?= $r['message'] ? htmlspecialchars(mb_substr($r['message'], 0, 40)) . (mb_strlen($r['message']) > 40 ? '…' : '') : '—' ?></td>
                                <td data-label="Receipt">
                                    <a href="../<?= htmlspecialchars($r['receipt_path']) ?>" target="_blank" class="btn-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
