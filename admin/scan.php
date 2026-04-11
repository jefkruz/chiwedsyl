<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$codeParam = trim((string) ($_GET['code'] ?? ''));

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($codeParam !== '' && guest_qr_secret_looks_valid($codeParam)) {
        $_SESSION['admin_scan_pending_code'] = strtoupper($codeParam);
    }
    header('Location: ' . BASE . '/admin');
    exit;
}

require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();

$pageTitle = 'Scan check-in';
$result = null;
/** @var array<string, mixed>|null $guest */
$guest = null;

if ($codeParam !== '') {
    if (!guest_qr_secret_looks_valid($codeParam)) {
        $result = ['type' => 'error', 'message' => 'That code does not look like a guest pass QR.'];
    } else {
        $codeNorm = strtoupper($codeParam);
        $stmt = $pdo->prepare('SELECT * FROM guests WHERE UPPER(qr_code) = ? LIMIT 1');
        $stmt->execute([$codeNorm]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($guest === null) {
            $result = ['type' => 'error', 'message' => 'No guest found for this code.'];
        } elseif (!empty($guest['checked_in'])) {
            $when = trim((string) ($guest['checked_in_at'] ?? ''));
            $result = [
                'type' => 'already',
                'message' => 'This pass was already checked in.',
                'when' => $when,
            ];
        } else {
            $pdo->prepare("UPDATE guests SET checked_in = 1, checked_in_at = datetime('now') WHERE id = ? AND (checked_in = 0 OR checked_in IS NULL)")
                ->execute([(int) $guest['id']]);
            $stmt2 = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
            $stmt2->execute([(int) $guest['id']]);
            $guest = $stmt2->fetch(PDO::FETCH_ASSOC) ?: $guest;
            $result = ['type' => 'success', 'message' => 'Checked in successfully.'];
        }
    }
}

require_once __DIR__ . '/../includes/guest-access-card.php';
$displayName = $guest ? guest_display_name($guest) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Check-in scan</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/guests">Guests</a>
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>

        <div class="admin-card admin-scan-card">
            <p class="admin-scan-lead">Scan a guest’s pass QR with your phone camera, or paste the code from under the QR if the link does not open.</p>

            <?php if ($result !== null): ?>
                <?php if ($result['type'] === 'success'): ?>
                    <div class="alert alert-success admin-scan-banner"><?= htmlspecialchars($result['message']) ?></div>
                    <?php if ($guest): ?>
                        <p class="admin-scan-guest-name"><strong><?= htmlspecialchars($displayName) ?></strong></p>
                        <p class="admin-scan-meta"><?= htmlspecialchars((string) ($guest['email'] ?? '')) ?></p>
                        <p class="admin-scan-meta">Party size: <?= (int) ($guest['num_guests'] ?? 1) ?></p>
                    <?php endif; ?>
                <?php elseif ($result['type'] === 'already'): ?>
                    <div class="admin-scan-banner admin-scan-banner--warn" role="status"><?= htmlspecialchars($result['message']) ?></div>
                    <?php if ($guest): ?>
                        <p class="admin-scan-guest-name"><strong><?= htmlspecialchars($displayName) ?></strong></p>
                        <?php if ($result['when'] !== ''): ?>
                            <p class="admin-scan-meta">Recorded: <?= htmlspecialchars($result['when']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-error"><?= htmlspecialchars($result['message']) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <p class="admin-scan-hint">No code in the URL yet. Scan a pass QR (opens this page while you are logged in) or enter the code manually below.</p>
            <?php endif; ?>

            <form method="get" action="<?= BASE ?>/admin/scan" class="admin-form-narrow--sm admin-scan-form">
                <div class="form-group">
                    <label for="scan-code">Pass code (16 characters)</label>
                    <input type="text" id="scan-code" name="code" value="<?= htmlspecialchars($codeParam) ?>" maxlength="32" autocomplete="off" autocapitalize="characters" placeholder="e.g. A1B2C3D4E5F6789">
                </div>
                <button type="submit" class="btn-submit">Check in</button>
            </form>
        </div>
    </div>
</body>
</html>
