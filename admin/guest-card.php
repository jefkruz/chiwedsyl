<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';
require_once __DIR__ . '/../includes/guest-access-card-image.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: ' . BASE . '/admin/guests');
    exit;
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ?');
$stmt->execute([$id]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guest) {
    header('Location: ' . BASE . '/admin/guests');
    exit;
}

if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!guest_access_card_send_png_download($guest)) {
        header('Location: ' . BASE . '/admin/guest-card?id=' . $id . '&download_error=1');
        exit;
    }
    exit;
}

$download_error = isset($_GET['download_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access card — <?= htmlspecialchars((string) ($guest['name'] ?? 'Guest')) ?> — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Guest access card</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/guests">← Guests</a>
                <a href="<?= BASE ?>/admin/guest-edit?id=<?= (int) $guest['id'] ?>">Edit details</a>
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <p><strong><?= htmlspecialchars((string) $guest['name']) ?></strong> — <?= htmlspecialchars((string) $guest['email']) ?></p>
            <?php if ($download_error): ?>
                <p class="alert alert-error">PNG export failed. Confirm PHP GD is enabled and <code>assets/fonts/Lora-Regular.ttf</code> is deployed.</p>
            <?php endif; ?>
            <div class="register-access-card-wrap" style="padding-top:1rem;">
                <?= render_guest_access_card($guest, BASE) ?>
            </div>
            <div class="register-access-card-actions" style="margin-bottom:0;">
                <a class="btn-submit" style="width:auto;padding:0.75rem 1.5rem;text-decoration:none;display:inline-block;" href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $guest['id'] ?>&amp;download=1">Download pass (PNG)</a>
            </div>
        </div>
    </div>
</body>
</html>
