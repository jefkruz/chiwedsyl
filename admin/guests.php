<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';
require_once __DIR__ . '/../includes/admin-delete-guest.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';
require_once __DIR__ . '/../includes/guest-whatsapp-invite.php';
require_once __DIR__ . '/../includes/admin-guest-export.php';

$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_guest_id'])) {
    $delId = (int) $_POST['delete_guest_id'];
    if (admin_delete_guest_registration($pdo, $delId)) {
        header('Location: ' . BASE . '/admin/guests?deleted=1');
    } else {
        header('Location: ' . BASE . '/admin/guests?delete_error=1');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_id'])) {
    $id = (int) $_POST['check_in_id'];
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $limit = guest_party_scan_limit($row);
        $pdo->prepare("UPDATE guests SET checked_in = 1, check_in_count = ?, checked_in_at = COALESCE(checked_in_at, datetime('now')) WHERE id = ?")
            ->execute([$limit, $id]);
    }
    header('Location: ' . BASE . '/admin/guests?checked=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_registration_id'])) {
    $id = (int) $_POST['confirm_registration_id'];
    if ($id > 0) {
        $pdo->prepare('UPDATE guests SET registration_confirmed = 1 WHERE id = ?')->execute([$id]);
    }
    header('Location: ' . BASE . '/admin/guests?confirmed=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_confirm_ids']) && is_array($_POST['bulk_confirm_ids'])) {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $_POST['bulk_confirm_ids']),
        static fn(int $id): bool => $id > 0
    )));
    $confirmedCount = 0;
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "UPDATE guests SET registration_confirmed = 1 WHERE id IN ({$placeholders}) AND COALESCE(registration_confirmed, 0) = 0"
        );
        $stmt->execute($ids);
        $confirmedCount = $stmt->rowCount();
    }
    $redirectQs = ['bulk_confirmed=' . $confirmedCount];
    $returnStatus = trim((string) ($_POST['return_status'] ?? ''));
    if (in_array($returnStatus, ['pending', 'confirmed'], true)) {
        $redirectQs[] = 'status=' . urlencode($returnStatus);
    }
    $returnQ = trim((string) ($_POST['return_q'] ?? ''));
    if ($returnQ !== '') {
        $redirectQs[] = 'q=' . urlencode($returnQ);
    }
    header('Location: ' . BASE . '/admin/guests?' . implode('&', $redirectQs));
    exit;
}

$confirmedGuest = null;
if (isset($_GET['confirmed'])) {
    $confirmedId = (int) $_GET['confirmed'];
    if ($confirmedId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
        $stmt->execute([$confirmedId]);
        $confirmedGuest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$q = trim($_GET['q'] ?? '');
$status = strtolower(trim($_GET['status'] ?? ''));
if (!in_array($status, ['pending', 'confirmed'], true)) {
    $status = '';
}

$where = [];
$params = [];
if ($status === 'pending') {
    $where[] = 'COALESCE(registration_confirmed, 0) = 0';
} elseif ($status === 'confirmed') {
    $where[] = 'registration_confirmed = 1';
}
if ($q !== '') {
    $where[] = "(name LIKE ? OR email LIKE ? OR IFNULL(phone,'') LIKE ? OR IFNULL(invited_by,'') LIKE ? OR IFNULL(first_name,'') LIKE ? OR IFNULL(last_name,'') LIKE ? OR IFNULL(title,'') LIKE ?)";
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

$sql = 'SELECT * FROM guests';
if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

if ($params === []) {
    $guests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $export = admin_guests_export_dataset($guests);
    admin_send_xlsx_download(
        $export['headers'],
        $export['rows'],
        'registrations-' . date('Y-m-d-His') . '.xlsx'
    );
}

$pendingGuestIds = [];
$whatsappUnsentIds = [];
foreach ($guests as $guestRow) {
    if ((int) ($guestRow['registration_confirmed'] ?? 0) !== 1) {
        $pendingGuestIds[] = (int) $guestRow['id'];
    } elseif (guest_whatsapp_eligible($guestRow) && !guest_whatsapp_invite_was_sent($guestRow)) {
        $whatsappUnsentIds[] = (int) $guestRow['id'];
    }
}

$guestsReturnPath = BASE . '/admin/guests';
if ($status !== '') {
    $guestsReturnPath .= '?status=' . urlencode($status);
    if ($q !== '') {
        $guestsReturnPath .= '&q=' . urlencode($q);
    }
} elseif ($q !== '') {
    $guestsReturnPath .= '?q=' . urlencode($q);
}

$guestsBulkScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    function initBulkBar(barId, formId, checkboxName, selectAllId) {
        const bulkBar = document.getElementById(barId);
        const bulkForm = document.getElementById(formId);
        const selectAll = document.getElementById(selectAllId);
        if (!bulkBar || !bulkForm) return;

        const selector = 'input[name="' + checkboxName + '"][form="' + formId + '"]';

        function boxes() {
            return document.querySelectorAll(selector);
        }

        function refreshBulkBar() {
            const checked = document.querySelectorAll(selector + ':checked').length;
            bulkBar.hidden = checked === 0;
            const countEl = bulkBar.querySelector('.js-bulk-selected-count');
            if (countEl) {
                countEl.textContent = checked === 1 ? '1 guest selected' : checked + ' guests selected';
            }
            if (selectAll) {
                const allBoxes = boxes();
                const checkedBoxes = document.querySelectorAll(selector + ':checked');
                selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allBoxes.length;
                selectAll.checked = allBoxes.length > 0 && checkedBoxes.length === allBoxes.length;
            }
        }

        document.addEventListener('change', function (event) {
            if (!(event.target instanceof HTMLInputElement)) return;
            if (event.target.name === checkboxName || event.target.id === selectAllId) {
                refreshBulkBar();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                const checked = selectAll.checked;
                const tableId = selectAll.getAttribute('data-table-id');
                if (tableId && window.jQuery && jQuery.fn.DataTable && jQuery.fn.dataTable.isDataTable('#' + tableId)) {
                    const table = jQuery('#' + tableId).DataTable();
                    table.rows({ page: 'current' }).nodes().to$().find(selector).prop('checked', checked);
                } else {
                    boxes().forEach(function (box) {
                        box.checked = checked;
                    });
                }
                refreshBulkBar();
            });
        }

        refreshBulkBar();
    }

    initBulkBar('guest-bulk-confirm-bar', 'guest-bulk-confirm-form', 'bulk_confirm_ids[]', 'guest-bulk-select-all');
    initBulkBar('guest-bulk-wa-bar', 'guest-bulk-wa-form', 'bulk_wa_ids[]', 'guest-bulk-wa-select-all');
});
JS;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests &amp; check-in — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Guests', 'guests'); ?>
        <div class="admin-card">
            <?php if (isset($_GET['checked'])): ?>
                <p class="alert alert-success">Guest marked as checked in.</p>
            <?php endif; ?>
            <?php if (isset($_GET['bulk_confirmed'])): ?>
                <p class="alert alert-success"><?= (int) $_GET['bulk_confirmed'] === 1 ? '1 registration confirmed.' : (int) $_GET['bulk_confirmed'] . ' registrations confirmed.' ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['confirmed'])): ?>
                <p class="alert alert-success">
                    Registration confirmed. The guest can now retrieve their access card from the public RSVP page.
                    <?php if ($confirmedGuest): ?>
                        <?php if (guest_whatsapp_eligible($confirmedGuest)): ?>
                            <br><a href="<?= htmlspecialchars(guest_admin_whatsapp_invite_href((int) $confirmedGuest['id'], BASE . '/admin/guests?confirmed=' . (int) $confirmedGuest['id'])) ?>" class="btn-small" style="margin-top:0.75rem;display:inline-block;">Send invite on WhatsApp</a>
                        <?php else: ?>
                            <br><span class="admin-scan-meta">Add a phone number on the guest record to send the invite on WhatsApp.</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <p class="alert alert-success">Registration removed from the list.</p>
            <?php endif; ?>
            <?php if (isset($_GET['delete_error'])): ?>
                <p class="alert alert-error">Could not remove that registration (it may have already been deleted).</p>
            <?php endif; ?>
            <p>The pass QR on each guest’s access card opens check-in when scanned (stay logged in on the device at the door). Scan once per person in the party — e.g. a pass for three people needs three scans. Use <strong>Check in</strong> below to admit the full party at once, or <a href="<?= BASE ?>/admin/scan">scan / enter code manually</a>. <strong>Delete</strong> permanently removes a registration and their pass photo file. Search by name or email, confirm new RSVPs, and open each guest’s access card to view or download.</p>
            <p class="admin-guest-filters" style="margin-bottom:1rem;">
                <a href="<?= BASE ?>/admin/guests" class="btn-small<?= $status === '' ? ' active' : '' ?>">All</a>
                <a href="<?= BASE ?>/admin/guests?status=pending" class="btn-small<?= $status === 'pending' ? ' active' : '' ?>">Pending</a>
                <a href="<?= BASE ?>/admin/guests?status=confirmed" class="btn-small<?= $status === 'confirmed' ? ' active' : '' ?>">Confirmed</a>
            </p>
            <form method="get" action="<?= BASE ?>/admin/guests" class="admin-search-form">
                <?php if ($status !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                <label for="guest-search" class="visually-hidden">Search guests</label>
                <input type="search" id="guest-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, email, phone…" autocomplete="off">
                <button type="submit" class="btn-small">Search</button>
                <?php if ($q !== '' || $status !== ''): ?>
                    <a href="<?= BASE ?>/admin/guests" class="btn-small">Clear</a>
                <?php endif; ?>
                <?php
                $exportQs = ['export=excel'];
                if ($q !== '') {
                    $exportQs[] = 'q=' . urlencode($q);
                }
                if ($status !== '') {
                    $exportQs[] = 'status=' . urlencode($status);
                }
                ?>
                <a href="<?= BASE ?>/admin/guests?<?= implode('&amp;', $exportQs) ?>" class="btn-small">Export Excel</a>
            </form>
            <?php if ($pendingGuestIds !== [] || $whatsappUnsentIds !== []): ?>
                <div class="admin-bulk-actions" style="margin-bottom:1rem;">
                    <?php if ($pendingGuestIds !== []): ?>
                    <form id="guest-bulk-confirm-form" method="post" class="admin-bulk-confirm-selected-form" style="display:inline;">
                        <?php if ($status !== ''): ?>
                            <input type="hidden" name="return_status" value="<?= htmlspecialchars($status) ?>">
                        <?php endif; ?>
                        <?php if ($q !== ''): ?>
                            <input type="hidden" name="return_q" value="<?= htmlspecialchars($q) ?>">
                        <?php endif; ?>
                        <span id="guest-bulk-confirm-bar" class="admin-bulk-confirm-bar" hidden>
                            <span class="admin-bulk-meta js-bulk-selected-count"></span>
                            <button type="submit" class="btn-small" onclick="return confirm('Confirm the selected registrations?');">Confirm selected</button>
                        </span>
                    </form>
                    <form method="post" class="admin-bulk-confirm-all-form" style="display:inline;" onsubmit="return confirm('Confirm all <?= count($pendingGuestIds) ?> pending guest<?= count($pendingGuestIds) === 1 ? '' : 's' ?> in this list?');">
                        <?php if ($status !== ''): ?>
                            <input type="hidden" name="return_status" value="<?= htmlspecialchars($status) ?>">
                        <?php endif; ?>
                        <?php if ($q !== ''): ?>
                            <input type="hidden" name="return_q" value="<?= htmlspecialchars($q) ?>">
                        <?php endif; ?>
                        <?php foreach ($pendingGuestIds as $pendingId): ?>
                            <input type="hidden" name="bulk_confirm_ids[]" value="<?= $pendingId ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn-small">Confirm all pending (<?= count($pendingGuestIds) ?>)</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($whatsappUnsentIds !== []): ?>
                    <form id="guest-bulk-wa-form" method="get" action="<?= BASE ?>/admin/whatsapp-bulk" class="admin-bulk-wa-selected-form" style="display:inline;">
                        <span id="guest-bulk-wa-bar" class="admin-bulk-confirm-bar" hidden>
                            <span class="admin-bulk-meta js-bulk-selected-count"></span>
                            <button type="submit" class="btn-small">Send WhatsApp to selected</button>
                        </span>
                    </form>
                    <a href="<?= BASE ?>/admin/whatsapp-bulk?ids=<?= implode(',', $whatsappUnsentIds) ?>" class="btn-small">Send all unsent WhatsApp (<?= count($whatsappUnsentIds) ?>)</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="table-wrap">
                <table id="guests-admin-table" class="js-datatable js-datatable-no-sort-first responsive-table table table-striped table-bordered table-sm">
                    <thead>
                        <tr>
                            <th class="admin-bulk-check-col">
                                <?php if ($pendingGuestIds !== []): ?>
                                    <input type="checkbox" id="guest-bulk-select-all" data-table-id="guests-admin-table" aria-label="Select all pending on this page">
                                <?php elseif ($whatsappUnsentIds !== []): ?>
                                    <input type="checkbox" id="guest-bulk-wa-select-all" data-table-id="guests-admin-table" aria-label="Select all unsent WhatsApp invites on this page">
                                <?php endif; ?>
                            </th>
                            <th>Guest</th>
                            <th>Title</th>
                            <th>Email</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Invited by</th>
                            <th># Guests</th>
                            <th>RSVP</th>
                            <th>Check-in</th>
                            <th>Access card</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $g): ?>
                            <?php
                            $regOk = (int) ($g['registration_confirmed'] ?? 0) === 1;
                            $waEligible = guest_whatsapp_eligible($g);
                            $waSent = guest_whatsapp_invite_was_sent($g);
                            $genderLabel = $g['gender'] ?? '';
                            if ($genderLabel === 'male') {
                                $genderLabel = 'Male';
                            } elseif ($genderLabel === 'female') {
                                $genderLabel = 'Female';
                            } else {
                                $genderLabel = '—';
                            }
                            ?>
                            <tr>
                                <td data-label="Select" class="admin-bulk-check-col">
                                    <?php if (!$regOk): ?>
                                        <input type="checkbox" name="bulk_confirm_ids[]" value="<?= (int) $g['id'] ?>" form="guest-bulk-confirm-form" aria-label="Select <?= htmlspecialchars($g['name']) ?>">
                                    <?php elseif ($waEligible && !$waSent): ?>
                                        <input type="checkbox" name="bulk_wa_ids[]" value="<?= (int) $g['id'] ?>" form="guest-bulk-wa-form" aria-label="Select <?= htmlspecialchars($g['name']) ?> for WhatsApp">
                                    <?php elseif ($waSent): ?>
                                        <span class="admin-bulk-sent-mark" title="WhatsApp sent">✓</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Guest"><?= htmlspecialchars($g['name']) ?></td>
                                <td data-label="Title"><?= htmlspecialchars($g['title'] ?? '—') ?></td>
                                <td data-label="Email"><?= htmlspecialchars($g['email']) ?></td>
                                <td data-label="Gender"><?= htmlspecialchars($genderLabel) ?></td>
                                <td data-label="Phone"><?= htmlspecialchars($g['phone'] ?? '') ?></td>
                                <td data-label="Invited by"><?= htmlspecialchars($g['invited_by'] ?? '') ?></td>
                                <td data-label="# Guests"><?= guest_party_scan_limit($g) ?></td>
                                <td data-label="RSVP"><?= $regOk ? 'Confirmed' : 'Pending' ?></td>
                                <td data-label="Check-in"><?php
                                    $scanLimit = guest_party_scan_limit($g);
                                    $scanCount = guest_check_in_count($g);
                                    if (guest_pass_fully_checked_in($g)):
                                        ?>✓ In (<?= $scanLimit ?>/<?= $scanLimit ?>)<?php if (!empty($g['checked_in_at'])): ?><br><span class="admin-checked-when"><?= htmlspecialchars((string) $g['checked_in_at']) ?></span><?php endif;
                                    elseif ($scanCount > 0):
                                        ?>Partial (<?= $scanCount ?>/<?= $scanLimit ?>)<?php if (!empty($g['checked_in_at'])): ?><br><span class="admin-checked-when"><?= htmlspecialchars((string) $g['checked_in_at']) ?></span><?php endif;
                                    else:
                                        ?>—<?php
                                    endif; ?></td>
                                <td data-label="Access card">
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $g['id'] ?>">View / save PNG</a>
                                </td>
                                <td data-label="Action">
                                    <div class="admin-action-stack">
                                        <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-edit?id=<?= (int) $g['id'] ?>" class="btn-small admin-edit-link">Edit</a>
                                        <?php if (!$regOk): ?>
                                            <form class="check-in-form" method="post">
                                                <input type="hidden" name="confirm_registration_id" value="<?= (int) $g['id'] ?>">
                                                <button type="submit" class="btn-small">Confirm RSVP</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($regOk): ?>
                                            <?php if ($waEligible): ?>
                                                <?php if ($waSent): ?>
                                                    <span class="admin-action-done">WhatsApp sent</span>
                                                    <?php if (!empty($g['whatsapp_invite_sent_at'])): ?>
                                                        <span class="admin-checked-when"><?= htmlspecialchars((string) $g['whatsapp_invite_sent_at']) ?></span>
                                                    <?php endif; ?>
                                                    <a href="<?= htmlspecialchars(guest_admin_whatsapp_invite_href((int) $g['id'], $guestsReturnPath)) ?>" class="btn-small">Resend</a>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars(guest_admin_whatsapp_invite_href((int) $g['id'], $guestsReturnPath)) ?>" class="btn-small">WhatsApp invite</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!guest_pass_fully_checked_in($g)): ?>
                                            <form class="check-in-form" method="post">
                                                <input type="hidden" name="check_in_id" value="<?= (int) $g['id'] ?>">
                                                <button type="submit" class="btn-small">Check in</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="admin-action-done">Checked in</span>
                                        <?php endif; ?>
                                        <form class="check-in-form" method="post" onsubmit="return confirm('Remove this registration permanently? This cannot be undone.');">
                                            <input type="hidden" name="delete_guest_id" value="<?= (int) $g['id'] ?>">
                                            <button type="submit" class="btn-small danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php admin_lte_layout_end($guestsBulkScripts); ?>
