<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_id'])) {
    $id = (int) $_POST['check_in_id'];
    $pdo->prepare("UPDATE guests SET checked_in = 1, checked_in_at = datetime('now') WHERE id = ?")->execute([$id]);
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests &amp; check-in — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Guests</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/scan">Scan check-in</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/well-wishes">Well wishes</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <?php if (isset($_GET['checked'])): ?>
                <p class="alert alert-success">Guest marked as checked in.</p>
            <?php endif; ?>
            <?php if (isset($_GET['confirmed'])): ?>
                <p class="alert alert-success">Registration confirmed. The guest can now retrieve their access card from the public RSVP page.</p>
            <?php endif; ?>
            <p>The pass QR on each guest’s access card opens check-in when scanned (stay logged in on the device at the door). A pass can only be checked in once; use <strong>Check in</strong> below or <a href="<?= BASE ?>/admin/scan">scan / enter code manually</a>. Search by name or email, confirm new RSVPs, and open each guest’s access card to view or download.</p>
            <form method="get" action="<?= BASE ?>/admin/guests" class="admin-search-form">
                <label for="guest-search" class="visually-hidden">Search guests</label>
                <input type="search" id="guest-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, email, phone…" autocomplete="off">
                <button type="submit" class="btn-small">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="<?= BASE ?>/admin/guests" class="btn-small">Clear</a>
                <?php endif; ?>
            </form>
            <div class="table-wrap">
                <table class="responsive-table">
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
                                <td data-label="# Guests"><?= (int) $g['num_guests'] ?></td>
                                <td data-label="RSVP"><?= $regOk ? 'Confirmed' : 'Pending' ?></td>
                                <td data-label="Check-in"><?php if (!empty($g['checked_in'])): ?>✓ In<?php if (!empty($g['checked_in_at'])): ?><br><span class="admin-checked-when"><?= htmlspecialchars((string) $g['checked_in_at']) ?></span><?php endif; else: ?>—<?php endif; ?></td>
                                <td data-label="Access card">
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $g['id'] ?>">View</a>
                                    ·
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $g['id'] ?>&amp;download=1">Download</a>
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
                                        <?php if (!$g['checked_in']): ?>
                                            <form class="check-in-form" method="post">
                                                <input type="hidden" name="check_in_id" value="<?= (int) $g['id'] ?>">
                                                <button type="submit" class="btn-small">Check in</button>
                                            </form>
                                        <?php elseif ($regOk): ?>
                                            <span class="admin-action-done">Done</span>
                                        <?php endif; ?>
                                    </div>
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
