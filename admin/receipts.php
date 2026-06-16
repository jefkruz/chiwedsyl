<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';

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
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Uploaded receipts', 'dashboard'); ?>
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
                                    <a href="<?= BASE ?>/<?= htmlspecialchars(ltrim($r['receipt_path'], '/')) ?>" target="_blank" rel="noopener" class="btn-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php admin_lte_layout_end(); ?>
