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
require_once __DIR__ . '/../includes/admin-lte-layout.php';

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
        } elseif (guest_pass_fully_checked_in($guest)) {
            $limit = guest_party_scan_limit($guest);
            $when = trim((string) ($guest['checked_in_at'] ?? ''));
            $result = [
                'type' => 'already',
                'message' => 'This pass is fully checked in (' . $limit . ' of ' . $limit . ').',
                'when' => $when,
            ];
        } else {
            $gid = (int) $guest['id'];
            $limit = guest_party_scan_limit($guest);
            $count = guest_check_in_count($guest);
            $newCount = $count + 1;
            if ($count === 0) {
                $pdo->prepare("UPDATE guests SET check_in_count = ?, checked_in_at = datetime('now') WHERE id = ?")
                    ->execute([$newCount, $gid]);
            } else {
                $pdo->prepare('UPDATE guests SET check_in_count = ? WHERE id = ?')
                    ->execute([$newCount, $gid]);
            }
            if ($newCount >= $limit) {
                $pdo->prepare('UPDATE guests SET checked_in = 1 WHERE id = ?')->execute([$gid]);
            }
            $stmt2 = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
            $stmt2->execute([$gid]);
            $guest = $stmt2->fetch(PDO::FETCH_ASSOC) ?: $guest;
            if ($newCount >= $limit) {
                $result = [
                    'type' => 'success',
                    'message' => 'Party fully checked in (' . $newCount . ' of ' . $limit . ').',
                    'count' => $newCount,
                    'limit' => $limit,
                ];
            } else {
                $result = [
                    'type' => 'partial',
                    'message' => 'Checked in ' . $newCount . ' of ' . $limit . ' for this pass. Scan again for each remaining guest.',
                    'count' => $newCount,
                    'limit' => $limit,
                ];
            }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Check-in scan', 'scan'); ?>

        <div class="admin-card admin-scan-card">
            <p class="admin-scan-lead">Scan a guest’s pass QR with your phone camera, or paste the code from under the QR if the link does not open.</p>

            <?php if ($result !== null): ?>
                <?php if ($result['type'] === 'success' || $result['type'] === 'partial'): ?>
                    <div class="alert alert-success admin-scan-banner"><?= htmlspecialchars($result['message']) ?></div>
                    <?php if ($guest): ?>
                        <p class="admin-scan-guest-name"><strong><?= htmlspecialchars($displayName) ?></strong></p>
                        <p class="admin-scan-meta"><?= htmlspecialchars((string) ($guest['email'] ?? '')) ?></p>
                        <p class="admin-scan-meta">Party size: <?= (int) ($result['limit'] ?? guest_party_scan_limit($guest)) ?> · Checked in: <?= (int) ($result['count'] ?? guest_check_in_count($guest)) ?></p>
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
<?php admin_lte_layout_end(); ?>
