<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_id'])) {
    $id = (int) $_POST['check_in_id'];
    $pdo->prepare('UPDATE guests SET checked_in = 1 WHERE id = ?')->execute([$id]);
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

$qrBase = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Guests &amp; QR codes</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/gifts">Gifts</a>
                <a href="<?= BASE ?>/admin/well-wishes">Well wishes</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <?php if (isset($_GET['checked'])): ?>
                <p class="alert alert-success" style="margin-bottom:1rem;">Guest marked as checked in.</p>
            <?php endif; ?>
            <?php if (isset($_GET['confirmed'])): ?>
                <p class="alert alert-success" style="margin-bottom:1rem;">Registration confirmed. The guest can now retrieve their access card from the public RSVP page.</p>
            <?php endif; ?>
            <p>Use these QR codes to check in guests at the venue. Search by name or email, confirm new RSVPs, and open each guest’s access card to print or save.</p>
            <form method="get" action="<?= BASE ?>/admin/guests" class="admin-search-form" style="margin:1rem 0 1.25rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
                <label for="guest-search" class="visually-hidden">Search guests</label>
                <input type="search" id="guest-search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, email, phone…" style="flex:1;min-width:200px;padding:0.5rem 0.75rem;border:1px solid var(--chocolate);border-radius:6px;">
                <button type="submit" class="btn-small">Search</button>
                <?php if ($q !== ''): ?>
                    <a href="<?= BASE ?>/admin/guests" class="btn-small" style="text-decoration:none;display:inline-block;">Clear</a>
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
                            <th>Photo</th>
                            <th>QR code</th>
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
                                <td data-label="Photo">
                                    <?php if (!empty($g['guest_photo_path']) && file_exists('../' . $g['guest_photo_path'])): ?>
                                        <a href="../<?= htmlspecialchars($g['guest_photo_path']) ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="guest-qr" data-label="QR code">
                                    <img src="<?= htmlspecialchars($qrBase . urlencode($g['qr_code'])) ?>" alt="QR">
                                </td>
                                <td data-label="RSVP"><?= $regOk ? 'Confirmed' : 'Pending' ?></td>
                                <td data-label="Check-in"><?= $g['checked_in'] ? '✓ In' : '—' ?></td>
                                <td data-label="Access card">
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $g['id'] ?>">View</a>
                                    ·
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-card?id=<?= (int) $g['id'] ?>&amp;download=1">Download</a>
                                </td>
                                <td data-label="Action">
                                    <a href="<?= htmlspecialchars(BASE) ?>/admin/guest-edit?id=<?= (int) $g['id'] ?>" class="btn-small" style="text-decoration:none;display:inline-block;margin-bottom:0.35rem;">Edit</a><br>
                                    <?php if (!$regOk): ?>
                                        <form class="check-in-form" method="post" style="margin-bottom:0.35rem;">
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
                                        <span style="color:var(--chocolate-light);font-size:0.9rem;">Done</span>
                                    <?php endif; ?>
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
