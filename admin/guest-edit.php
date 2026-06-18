<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';
require_once __DIR__ . '/../includes/guest-photo-upload.php';
require_once __DIR__ . '/../includes/admin-delete-guest.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_guest') {
    $delId = (int) ($_POST['delete_guest_id'] ?? 0);
    if ($delId === $id && $delId > 0) {
        if (admin_delete_guest_registration($pdo, $delId)) {
            header('Location: ' . BASE . '/admin/guests?deleted=1');
            exit;
        }
        header('Location: ' . BASE . '/admin/guests?delete_error=1');
        exit;
    }
    header('Location: ' . BASE . '/admin/guest-edit?id=' . $id . '&delete_error=1');
    exit;
}

$validTitles = guest_valid_titles();
$maxGuestPhotoBytes = 10 * 1024 * 1024;
$error = '';
$saved = isset($_GET['saved']);
$deleteError = isset($_GET['delete_error']);

$defaultFirst = trim((string) ($guest['first_name'] ?? ''));
$defaultLast = trim((string) ($guest['last_name'] ?? ''));
if ($defaultFirst === '' && $defaultLast === '' && trim((string) ($guest['name'] ?? '')) !== '') {
    $parts = preg_split('/\s+/', trim((string) $guest['name']), 2);
    $defaultFirst = $parts[0] ?? '';
    $defaultLast = $parts[1] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_guest') {
    $title = trim($_POST['title'] ?? '');
    if ($title !== '' && !isset($validTitles[$title])) {
        $error = 'Please choose a valid title, or leave title empty.';
        $title = '';
    }
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $invited_by = trim($_POST['invited_by'] ?? '');
    $num_guests = (int) ($_POST['num_guests'] ?? 1);
    if ($num_guests < 1) {
        $num_guests = 1;
    }
    if ($num_guests > 2) {
        $num_guests = 2;
    }
    $registration_confirmed = isset($_POST['registration_confirmed']) ? 1 : 0;
    $checked_in = isset($_POST['checked_in']) ? 1 : 0;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } elseif ($gender !== '' && !in_array($gender, ['male', 'female'], true)) {
        $error = 'Invalid gender selection.';
    } elseif ($first === '' && $last === '') {
        $error = 'Enter at least a first name or a last name.';
    } else {
        $guestId = (int) ($guest['id'] ?? 0);
        $currentNorm = strtolower(trim((string) ($guest['email'] ?? '')));
        $submittedNorm = strtolower(trim($email));
        if ($submittedNorm !== $currentNorm) {
            $dup = $pdo->prepare('SELECT id FROM guests WHERE LOWER(TRIM(COALESCE(email, \'\'))) = LOWER(TRIM(?)) AND id != ?');
            $dup->execute([$email, $guestId]);
            if ($dup->fetch()) {
                $error = 'Another guest is already registered with this email.';
            }
        }
    }

    if ($error === '') {
        $name = guest_composed_full_name($title, $first, $last);
        if ($name === '') {
            $name = trim((string) $guest['name']);
        }

        $oldPhotoPath = trim((string) ($guest['guest_photo_path'] ?? ''));
        $guestPhotoPath = guest_has_valid_pass_photo_on_disk($guest) ? $oldPhotoPath : null;
        $removePhoto = isset($_POST['remove_guest_photo']);
        $photoResult = guest_process_photo_upload($_FILES['guest_photo'] ?? null, $maxGuestPhotoBytes, false);

        if ($photoResult['error'] !== null) {
            $error = $photoResult['error'];
        } elseif ($photoResult['path'] !== null) {
            if ($guestPhotoPath !== null && $guestPhotoPath !== $photoResult['path']) {
                guest_delete_stored_photo_file($guestPhotoPath);
            }
            $guestPhotoPath = $photoResult['path'];
        } elseif ($removePhoto) {
            guest_delete_stored_photo_file($guestPhotoPath);
            $guestPhotoPath = null;
        }

        if ($error === '') {
            $stmt = $pdo->prepare('UPDATE guests SET title = ?, first_name = ?, last_name = ?, name = ?, email = ?, phone = ?, gender = ?, invited_by = ?, num_guests = ?, registration_confirmed = ?, checked_in = ?, guest_photo_path = ? WHERE id = ?');
            $stmt->execute([
                $title !== '' ? $title : null,
                $first !== '' ? $first : null,
                $last !== '' ? $last : null,
                $name,
                $email,
                $phone !== '' ? $phone : null,
                $gender !== '' ? $gender : null,
                $invited_by !== '' ? $invited_by : null,
                $num_guests,
                $registration_confirmed,
                $checked_in,
                $guestPhotoPath,
                $id,
            ]);
            header('Location: ' . BASE . '/admin/guest-edit?id=' . $id . '&saved=1');
            exit;
        }
    }

    $guest = array_merge($guest, [
        'title' => $title,
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'phone' => $phone,
        'gender' => $gender,
        'invited_by' => $invited_by,
        'num_guests' => $num_guests,
        'registration_confirmed' => $registration_confirmed,
        'checked_in' => $checked_in,
    ]);
} else {
    $guest['first_name'] = $defaultFirst;
    $guest['last_name'] = $defaultLast;
}

$v = function (string $field) use ($guest) {
    return htmlspecialchars((string) ($guest[$field] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit guest — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Edit guest', 'guests'); ?>
        <div class="admin-card">
            <?php if ($saved): ?>
                <p class="alert alert-success">Guest saved.</p>
            <?php endif; ?>
            <?php if ($deleteError): ?>
                <p class="alert alert-error">Could not delete this registration. Try again from the guest list.</p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p>Changes here update the guest list and their downloadable access pass. You can replace or remove the guest’s pass photo below.</p>
            <form method="post" action="<?= BASE ?>/admin/guest-edit?id=<?= (int) $id ?>" class="admin-guest-edit-form admin-form-narrow" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_guest">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <div class="form-group">
                    <label for="title">Title</label>
                    <select id="title" name="title">
                        <option value="">—</option>
                        <?php foreach ($validTitles as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= (($guest['title'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= $v('first_name') ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" value="<?= $v('last_name') ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?= $v('email') ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" value="<?= $v('phone') ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="" <?= ($guest['gender'] ?? '') === '' ? 'selected' : '' ?>>—</option>
                        <option value="male" <?= ($guest['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($guest['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="invited_by">Invited by</label>
                    <input type="text" id="invited_by" name="invited_by" value="<?= $v('invited_by') ?>">
                </div>
                <div class="form-group">
                    <label for="num_guests">How many people are in the party?</label>
                    <select id="num_guests" name="num_guests">
                        <?php $editNum = min(2, max(1, (int) ($guest['num_guests'] ?? 1))); ?>
                        <option value="1" <?= $editNum === 1 ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= $editNum === 2 ? 'selected' : '' ?>>2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="registration_confirmed" value="1" <?= (int) ($guest['registration_confirmed'] ?? 0) === 1 ? 'checked' : '' ?>> Registration confirmed</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="checked_in" value="1" <?= !empty($guest['checked_in']) ? 'checked' : '' ?>> Checked in at venue</label>
                </div>
                <div class="form-group admin-guest-photo-field">
                    <label for="guest_photo">Pass photo</label>
                    <?php if (guest_has_valid_pass_photo_on_disk($guest)): ?>
                        <?php $photoUrl = htmlspecialchars(BASE . '/' . ltrim((string) $guest['guest_photo_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>
                        <div class="admin-guest-photo-preview-wrap">
                            <img src="<?= $photoUrl ?>" alt="Current pass photo for <?= $v('name') ?>" class="admin-guest-photo-preview">
                        </div>
                        <label class="admin-guest-photo-remove">
                            <input type="checkbox" name="remove_guest_photo" value="1"> Remove current photo
                        </label>
                    <?php else: ?>
                        <p class="admin-scan-meta">No pass photo on file.</p>
                    <?php endif; ?>
                    <input type="file" id="guest_photo" name="guest_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                    <p class="admin-scan-meta">JPG, PNG, GIF or WebP. Max 10 MB. Uploading a new image replaces the current one.</p>
                </div>
                <p><button type="submit" class="btn-submit btn-submit--inline">Save changes</button></p>
            </form>
            <form method="post" action="<?= BASE ?>/admin/guest-edit?id=<?= (int) $id ?>" class="admin-form-narrow admin-guest-delete-form" onsubmit="return confirm('Remove this registration permanently? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_guest">
                <input type="hidden" name="delete_guest_id" value="<?= (int) $id ?>">
                <button type="submit" class="btn-small danger">Delete registration</button>
            </form>
        </div>
<?php admin_lte_layout_end(); ?>
