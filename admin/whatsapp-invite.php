<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-whatsapp-invite.php';

$pdo = getDb();
$id = (int) ($_GET['id'] ?? 0);
$returnPath = admin_safe_return_path($_GET['return'] ?? null);

if ($id < 1) {
    header('Location: ' . $returnPath);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$guest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guest || !guest_whatsapp_eligible($guest)) {
    header('Location: ' . $returnPath);
    exit;
}

guest_mark_whatsapp_invite_sent($pdo, $id);

$waUrl = guest_whatsapp_invite_url($guest);
if ($waUrl === '') {
    header('Location: ' . $returnPath);
    exit;
}

$pageTitle = 'Opening WhatsApp — Admin — ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body class="admin-whatsapp-bridge">
    <main class="admin-whatsapp-bridge-card">
        <h1>WhatsApp invite</h1>
        <p>Opening WhatsApp for <strong><?= htmlspecialchars(guest_display_name($guest)) ?></strong>…</p>
        <p><a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" rel="noopener noreferrer">Open WhatsApp manually</a> if a new tab did not appear.</p>
        <p><a href="<?= htmlspecialchars($returnPath) ?>">Back to guest list</a></p>
    </main>
    <script>
        (function () {
            const waUrl = <?= json_encode($waUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const returnUrl = <?= json_encode($returnPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const popup = window.open(waUrl, '_blank', 'noopener,noreferrer');
            if (!popup) {
                window.location.href = waUrl;
                return;
            }
            window.setTimeout(function () {
                window.location.href = returnUrl;
            }, 400);
        })();
    </script>
</body>
</html>
