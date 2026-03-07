<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();

// Check-in by QR code (for scanner / form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_id'])) {
    $id = (int) $_POST['check_in_id'];
    $pdo->prepare("UPDATE guests SET checked_in = 1 WHERE id = ?")->execute([$id]);
    header('Location: ' . BASE . '/admin/guests?checked=' . $id);
    exit;
}

$guests = $pdo->query("SELECT * FROM guests ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$qrBase = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Guests &amp; QR codes</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/receipts">Receipts</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <p>Use these QR codes to check in guests at the venue. Scan their code or mark them checked in below.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Guest</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Invited by</th>
                            <th># Guests</th>
                            <th>Photo</th>
                            <th>QR code</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $g): ?>
                            <tr>
                                <td><?= htmlspecialchars($g['name']) ?></td>
                                <td><?= htmlspecialchars($g['email']) ?></td>
                                <td><?= htmlspecialchars($g['phone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($g['invited_by'] ?? '') ?></td>
                                <td><?= (int) $g['num_guests'] ?></td>
                                <td>
                                    <?php if (!empty($g['guest_photo_path']) && file_exists('../' . $g['guest_photo_path'])): ?>
                                        <a href="../<?= htmlspecialchars($g['guest_photo_path']) ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="guest-qr">
                                    <img src="<?= htmlspecialchars($qrBase . urlencode($g['qr_code'])) ?>" alt="QR">
                                </td>
                                <td><?= $g['checked_in'] ? '✓ Checked in' : '—' ?></td>
                                <td>
                                    <?php if (!$g['checked_in']): ?>
                                        <form class="check-in-form" method="post">
                                            <input type="hidden" name="check_in_id" value="<?= (int) $g['id'] ?>">
                                            <button type="submit" class="btn-small">Check in</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
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
