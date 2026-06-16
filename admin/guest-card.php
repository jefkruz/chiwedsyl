<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access card — <?= htmlspecialchars((string) ($guest['name'] ?? 'Guest')) ?> — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Guest access card', 'guests'); ?>
        <div class="admin-card">
            <p><strong><?= htmlspecialchars((string) $guest['name']) ?></strong> — <?= htmlspecialchars((string) $guest['email']) ?></p>
            <div class="admin-guest-card-preview register-access-card-wrap">
                <?= render_guest_access_card($guest, BASE) ?>
            </div>
            <div class="admin-guest-card-actions register-access-card-actions">
                <button type="button" class="btn-submit admin-btn-download" data-access-card-download aria-label="Download pass as PNG">Download pass (PNG)</button>
            </div>
        </div>
    <script src="<?= htmlspecialchars(BASE) ?>/assets/js/access-card-download.js" defer></script>
<?php admin_lte_layout_end(); ?>
