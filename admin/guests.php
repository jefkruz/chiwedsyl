<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';
require_once __DIR__ . '/../includes/admin-delete-guest.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';

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

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    $guests = $pdo->query('SELECT * FROM guests ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE name LIKE ? OR email LIKE ? OR IFNULL(phone,'') LIKE ? OR IFNULL(invited_by,'') LIKE ? OR IFNULL(first_name,'') LIKE ? OR IFNULL(last_name,'') LIKE ? OR IFNULL(title,'') LIKE ? ORDER BY created_at DESC");
    $stmt->execute([$like, $like, $like, $like, $like, $like, $like]);
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'registrations-' . date('Y-m-d-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        exit('Could not generate CSV.');
    }

    // UTF-8 BOM improves compatibility with Excel.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'ID',
        'Guest',
        'Title',
        'Email',
        'Gender',
        'Phone',
        'Invited by',
        'Party size',
        'RSVP status',
        'Check-in status',
        'Checked in count',
        'Created at',
        'Checked in at',
    ]);

    foreach ($guests as $g) {
        $scanLimit = guest_party_scan_limit($g);
        $scanCount = guest_check_in_count($g);
        $regOk = (int) ($g['registration_confirmed'] ?? 0) === 1;
        if (guest_pass_fully_checked_in($g)) {
            $checkInStatus = 'In (' . $scanLimit . '/' . $scanLimit . ')';
        } elseif ($scanCount > 0) {
            $checkInStatus = 'Partial (' . $scanCount . '/' . $scanLimit . ')';
        } else {
            $checkInStatus = 'Not checked in';
        }

        $gender = (string) ($g['gender'] ?? '');
        if ($gender === 'male') {
            $gender = 'Male';
        } elseif ($gender === 'female') {
            $gender = 'Female';
        } else {
            $gender = '';
        }

        fputcsv($out, [
            (int) ($g['id'] ?? 0),
            (string) ($g['name'] ?? ''),
            (string) ($g['title'] ?? ''),
            (string) ($g['email'] ?? ''),
            $gender,
            (string) ($g['phone'] ?? ''),
            (string) ($g['invited_by'] ?? ''),
            $scanLimit,
            $regOk ? 'Confirmed' : 'Pending',
            $checkInStatus,
            $scanCount . '/' . $scanLimit,
            (string) ($g['created_at'] ?? ''),
            (string) ($g['checked_in_at'] ?? ''),
        ]);
    }

    fclose($out);
    exit;
}

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
            <?php if (isset($_GET['confirmed'])): ?>
                <p class="alert alert-success">Registration confirmed. The guest can now retrieve their access card from the public RSVP page.</p>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <p class="alert alert-success">Registration removed from the list.</p>
            <?php endif; ?>
            <?php if (isset($_GET['delete_error'])): ?>
                <p class="alert alert-error">Could not remove that registration (it may have already been deleted).</p>
            <?php endif; ?>
            <p>The pass QR on each guest’s access card opens check-in when scanned (stay logged in on the device at the door). Scan once per person in the party — e.g. a pass for three people needs three scans. Use <strong>Check in</strong> below to admit the full party at once, or <a href="<?= BASE ?>/admin/scan">scan / enter code manually</a>. <strong>Delete</strong> permanently removes a registration and their pass photo file. Search by name or email, confirm new RSVPs, and open each guest’s access card to view or download.</p>
            <form method="get" action="<?= BASE ?>/admin/guests" class="admin-search-form">
                <label for="guest-search" class="visually-hidden">Search guests</label>
                <input type="search" id="guest-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, email, phone…" autocomplete="off">
                <button type="submit" class="btn-small">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="<?= BASE ?>/admin/guests" class="btn-small">Clear</a>
                <?php endif; ?>
                <a href="<?= BASE ?>/admin/guests?export=csv<?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>" class="btn-small">Export CSV</a>
            </form>
            <div class="table-wrap">
                <table class="js-datatable responsive-table table table-striped table-bordered table-sm">
                    <thead>
                        <tr>
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
                                        <?php if (!guest_pass_fully_checked_in($g)): ?>
                                            <form class="check-in-form" method="post">
                                                <input type="hidden" name="check_in_id" value="<?= (int) $g['id'] ?>">
                                                <button type="submit" class="btn-small">Check in</button>
                                            </form>
                                        <?php elseif ($regOk): ?>
                                            <span class="admin-action-done">Done</span>
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
<?php admin_lte_layout_end(); ?>
