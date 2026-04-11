<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/guest-access-card.php';

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

$validTitles = guest_valid_titles();
$max_guest_photo_bytes = 10 * 1024 * 1024;
$error = '';
$saved = isset($_GET['saved']);

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
    if ($num_guests > 5) {
        $num_guests = 5;
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
        $dup = $pdo->prepare('SELECT id FROM guests WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) AND id != ?');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) {
            $error = 'Another guest is already registered with this email.';
        }
    }

    $photo_path = (string) ($guest['guest_photo_path'] ?? '');
    $fu = $_FILES['photo'] ?? null;
    if ($error === '' && is_array($fu) && (int) ($fu['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $fileErr = (int) ($fu['error'] ?? UPLOAD_ERR_NO_FILE);
        $fileName = trim((string) ($fu['name'] ?? ''));
        if ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE) {
            $error = 'Replacement photo must be 10 MB or smaller.';
        } elseif ($fileErr !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed.';
        } elseif ((int) ($fu['size'] ?? 0) > $max_guest_photo_bytes) {
            $error = 'Replacement photo must be 10 MB or smaller.';
        } else {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $error = 'Use JPG, PNG, GIF or WebP for the photo.';
            } else {
                $uploadDir = UPLOAD_PATH . '/guests/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = uniqid('guest_') . '.' . $ext;
                if (move_uploaded_file($fu['tmp_name'], $uploadDir . $filename)) {
                    $photo_path = 'uploads/guests/' . $filename;
                } else {
                    $error = 'Could not save the new photo.';
                }
            }
        }
    }

    if ($error === '') {
        $name = guest_composed_full_name($title, $first, $last);
        if ($name === '') {
            $name = trim((string) $guest['name']);
        }
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
            $photo_path !== '' ? $photo_path : null,
            $id,
        ]);
        header('Location: ' . BASE . '/admin/guest-edit?id=' . $id . '&saved=1');
        exit;
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
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Edit guest</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/guests">← Guests</a>
                <a href="<?= BASE ?>/admin/guest-card?id=<?= (int) $id ?>">Access card</a>
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>
        <div class="admin-card">
            <?php if ($saved): ?>
                <p class="alert alert-success">Guest saved.</p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p>Changes here update the guest list and their downloadable access pass. The QR code is not changed.</p>
            <form method="post" action="<?= BASE ?>/admin/guest-edit?id=<?= (int) $id ?>" enctype="multipart/form-data" class="admin-guest-edit-form admin-form-narrow">
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
                    <label for="num_guests">Number of people (including guest)</label>
                    <select id="num_guests" name="num_guests">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= (int) ($guest['num_guests'] ?? 1) === $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="registration_confirmed" value="1" <?= (int) ($guest['registration_confirmed'] ?? 0) === 1 ? 'checked' : '' ?>> Registration confirmed</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="checked_in" value="1" <?= !empty($guest['checked_in']) ? 'checked' : '' ?>> Checked in at venue</label>
                </div>
                <div class="form-group">
                    <label for="photo">Replace pass photo (optional)</label>
                    <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">JPG, PNG, GIF or WebP, max 10 MB. Leave empty to keep the current photo.</span>
                </div>
                <p><button type="submit" class="btn-submit btn-submit--inline">Save changes</button></p>
            </form>
        </div>
    </div>
</body>
</html>
