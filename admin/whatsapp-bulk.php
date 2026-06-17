<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';
require_once __DIR__ . '/../includes/guest-whatsapp-invite.php';

$pdo = getDb();

$rawIds = $_GET['ids'] ?? ($_POST['bulk_wa_ids'] ?? []);
if (is_string($rawIds)) {
    $rawIds = array_filter(explode(',', $rawIds));
}
if (!is_array($rawIds)) {
    $rawIds = [];
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', $rawIds),
    static fn(int $id): bool => $id > 0
)));

$guests = [];
if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT * FROM guests WHERE id IN ({$placeholders}) AND registration_confirmed = 1 ORDER BY name COLLATE NOCASE ASC"
    );
    $stmt->execute($ids);
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$returnPath = BASE . '/admin/whatsapp-bulk?' . http_build_query(['ids' => implode(',', array_map('intval', array_column($guests, 'id')))]);
$sentCount = 0;
$unsentCount = 0;
foreach ($guests as $guestRow) {
    if (guest_whatsapp_invite_was_sent($guestRow)) {
        $sentCount++;
    } elseif (guest_whatsapp_eligible($guestRow)) {
        $unsentCount++;
    }
}

$bulkScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const openBtn = document.getElementById('wa-bulk-open-unsent');
    if (!openBtn) return;
    openBtn.addEventListener('click', function (event) {
        event.preventDefault();
        const links = Array.from(document.querySelectorAll('[data-wa-unsent-invite]'));
        if (links.length === 0) return;
        let index = 0;
        function openNext() {
            if (index >= links.length) return;
            const link = links[index++];
            window.open(link.href, '_blank', 'noopener,noreferrer');
            if (index < links.length) {
                window.setTimeout(openNext, 900);
            }
        }
        openNext();
    });
});
JS;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk WhatsApp invites — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('WhatsApp invites', 'guests'); ?>
        <div class="admin-card">
            <p><a href="<?= BASE ?>/admin/guests?status=confirmed" class="btn-small">← Back to guests</a></p>
            <?php if ($guests === []): ?>
                <p class="alert alert-error">No confirmed guests were selected.</p>
            <?php else: ?>
                <p>
                    <?= count($guests) ?> guest<?= count($guests) === 1 ? '' : 's' ?> selected —
                    <strong><?= $unsentCount ?></strong> not yet sent,
                    <strong><?= $sentCount ?></strong> already sent.
                </p>
                <p class="admin-scan-meta">
                    Click <strong>Send</strong> on each row to open WhatsApp in a new tab and mark the invite as sent.
                    Use <strong>Open all unsent</strong> to launch each unsent invite in sequence (your browser may block many pop-ups).
                </p>
                <?php if ($unsentCount > 0): ?>
                    <p style="margin-bottom:1rem;">
                        <button type="button" class="btn-small" id="wa-bulk-open-unsent">Open all unsent (<?= $unsentCount ?>)</button>
                    </p>
                <?php endif; ?>
                <div class="table-wrap">
                    <table class="table table-striped table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Phone</th>
                                <th>WhatsApp</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guests as $g): ?>
                                <?php
                                $eligible = guest_whatsapp_eligible($g);
                                $sent = guest_whatsapp_invite_was_sent($g);
                                $inviteHref = guest_admin_whatsapp_invite_href((int) $g['id'], $returnPath);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($g['name']) ?></td>
                                    <td><?= htmlspecialchars((string) ($g['phone'] ?? '')) ?></td>
                                    <td>
                                        <?php if (!$eligible): ?>
                                            <span class="admin-scan-meta">No valid phone</span>
                                        <?php elseif ($sent): ?>
                                            <span class="admin-action-done">Sent</span>
                                            <?php if (!empty($g['whatsapp_invite_sent_at'])): ?>
                                                <br><span class="admin-checked-when"><?= htmlspecialchars((string) $g['whatsapp_invite_sent_at']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Not sent
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($eligible): ?>
                                            <a href="<?= htmlspecialchars($inviteHref) ?>"
                                               class="btn-small<?= $sent ? '' : '' ?>"
                                               <?= !$sent ? 'data-wa-unsent-invite' : '' ?>>
                                                <?= $sent ? 'Resend' : 'Send' ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
<?php admin_lte_layout_end($bulkScripts); ?>
