<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$message = '';
$messageType = '';

if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM well_wishes WHERE id = ?")->execute([$id]);
    header('Location: ' . BASE . '/admin/well-wishes?deleted=1');
    exit;
}

if (isset($_GET['deleted'])) {
    $message = 'Well wish removed.';
    $messageType = 'success';
}

$wishes = $pdo->query("SELECT * FROM well_wishes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$allowed_msg_tags = '<p><br><b><strong><i><em><u>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Well wishes — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Well wishes</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/guests">Guests</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/well-wishes">View public page</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="admin-card">
            <h2>Messages left for the couple</h2>
            <?php if (empty($wishes)): ?>
                <p>No well wishes yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Message</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wishes as $w): ?>
                                <tr>
                                    <td><?= htmlspecialchars($w['author_name']) ?></td>
                                    <td><?= htmlspecialchars($w['created_at'] ?? '') ?></td>
                                    <td class="admin-wish-cell">
                                        <div class="admin-wish-preview"><?= strip_tags($w['message'] ?? '', $allowed_msg_tags) ?></div>
                                    </td>
                                    <td>
                                        <a href="<?= BASE ?>/admin/well-wishes?delete=<?= (int) $w['id'] ?>" class="btn-small danger" onclick="return confirm('Remove this well wish?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
