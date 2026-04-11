<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/guest-access-card.php';
/**
 * @return array{path: ?string, error: ?string}
 */
function register_process_guest_photo_upload(?array $fu, int $max_bytes, bool $required): array {
    if (!is_array($fu)) {
        return ['path' => null, 'error' => $required ? 'Please upload a photo for your guest pass.' : null];
    }
    $fileErr = (int) ($fu['error'] ?? UPLOAD_ERR_NO_FILE);
    $fileName = trim((string) ($fu['name'] ?? ''));
    if ($fileErr === UPLOAD_ERR_NO_FILE || $fileName === '') {
        return ['path' => null, 'error' => $required ? 'Please upload a photo for your guest pass (JPG, PNG, GIF or WebP, max 10 MB).' : null];
    }
    if ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => 'Your photo must be 10 MB or smaller.'];
    }
    if ($fileErr !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Photo upload failed. Please try again.'];
    }
    if ((int) ($fu['size'] ?? 0) > $max_bytes) {
        return ['path' => null, 'error' => 'Your photo must be 10 MB or smaller.'];
    }
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['path' => null, 'error' => 'Please use a JPG, PNG, GIF or WebP image.'];
    }
    $uploadDir = UPLOAD_PATH . '/guests/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid('guest_') . '.' . $ext;
    if (!move_uploaded_file($fu['tmp_name'], $uploadDir . $filename)) {
        return ['path' => null, 'error' => 'Could not save your photo. Please try again.'];
    }
    return ['path' => 'uploads/guests/' . $filename, 'error' => null];
}

$current_page = 'register';
$page_title = 'RSVP — ' . SITE_NAME;

$max_guest_photo_bytes = 10 * 1024 * 1024;

if (isset($_GET['new']) && $_GET['new'] === '1') {
    unset($_SESSION['register_phase'], $_SESSION['register_phase_data'], $_SESSION['register_profile_saved']);
    header('Location: ' . BASE . '/register');
    exit;
}

$pdo = getDb();
$error = '';
$email_error = '';
$form_error = '';
$profile_error = '';
$thanks = isset($_GET['thanks']) && !empty($_SESSION['register_thanks']);
if ($thanks) {
    unset($_SESSION['register_thanks']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'check_email') {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_error = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM guests WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (guest_profile_needs_completion($row)) {
                    $_SESSION['register_phase'] = 'update_profile';
                    $_SESSION['register_phase_data'] = [
                        'guest_id' => (int) $row['id'],
                        'email' => trim((string) $row['email']),
                    ];
                } elseif ((int) ($row['registration_confirmed'] ?? 0) === 1) {
                    $_SESSION['register_phase'] = 'card';
                    $_SESSION['register_phase_data'] = ['guest_id' => (int) $row['id']];
                } else {
                    $_SESSION['register_phase'] = 'pending';
                    $_SESSION['register_phase_data'] = ['email' => $email];
                }
            } else {
                $_SESSION['register_phase'] = 'new';
                $_SESSION['register_phase_data'] = ['email' => $email];
            }
            header('Location: ' . BASE . '/register');
            exit;
        }
    } elseif ($action === 'update_guest_profile') {
        if (($_SESSION['register_phase'] ?? '') !== 'update_profile' || empty($_SESSION['register_phase_data']['guest_id'])) {
            header('Location: ' . BASE . '/register');
            exit;
        }
        $gid = (int) $_SESSION['register_phase_data']['guest_id'];
        $sessEmail = $_SESSION['register_phase_data']['email'] ?? '';
        $postEmail = trim($_POST['email'] ?? '');
        if ($gid < 1 || strcasecmp(trim($sessEmail), $postEmail) !== 0) {
            header('Location: ' . BASE . '/register');
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
        $stmt->execute([$gid]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
            header('Location: ' . BASE . '/register');
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $invited_by = trim($_POST['invited_by'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $num_guests = (int) ($_POST['num_guests'] ?? 1);
        if ($num_guests < 1) {
            $num_guests = 1;
        }
        if ($num_guests > 5) {
            $num_guests = 5;
        }

        $photoRequired = !guest_has_valid_pass_photo_on_disk($existing);
        $photoResult = register_process_guest_photo_upload($_FILES['photo'] ?? null, $max_guest_photo_bytes, $photoRequired);
        $newPhotoPath = $photoResult['path'];
        $photoErr = $photoResult['error'];
        $guest_photo_path = guest_has_valid_pass_photo_on_disk($existing)
            ? (string) $existing['guest_photo_path']
            : null;
        if ($newPhotoPath !== null) {
            $guest_photo_path = $newPhotoPath;
        }

        if (!isset(guest_valid_titles()[$title])) {
            $profile_error = 'Please select your title.';
        } elseif ($first === '' || $last === '') {
            $profile_error = 'Please enter your first and last name.';
        } elseif (!in_array($gender, ['male', 'female'], true)) {
            $profile_error = 'Please select your gender.';
        } elseif ($photoErr !== null) {
            $profile_error = $photoErr;
        } elseif ($guest_photo_path === null || $guest_photo_path === '') {
            $profile_error = 'Please upload a photo for your guest pass.';
        } else {
            $name = guest_composed_full_name($title, $first, $last);
            if ($name === '') {
                $name = trim((string) $existing['name']);
            }
            try {
                $upd = $pdo->prepare('UPDATE guests SET title = ?, first_name = ?, last_name = ?, name = ?, email = ?, phone = ?, gender = ?, invited_by = ?, num_guests = ?, guest_photo_path = ? WHERE id = ?');
                $upd->execute([
                    $title,
                    $first,
                    $last,
                    $name,
                    trim((string) $existing['email']),
                    $phone !== '' ? $phone : null,
                    $gender,
                    $invited_by !== '' ? $invited_by : null,
                    $num_guests,
                    $guest_photo_path,
                    $gid,
                ]);
                $_SESSION['register_profile_saved'] = true;
                if ((int) ($existing['registration_confirmed'] ?? 0) === 1) {
                    $_SESSION['register_phase'] = 'card';
                    $_SESSION['register_phase_data'] = ['guest_id' => $gid];
                } else {
                    $_SESSION['register_phase'] = 'pending';
                    $_SESSION['register_phase_data'] = ['email' => trim((string) $existing['email'])];
                }
                header('Location: ' . BASE . '/register');
                exit;
            } catch (PDOException $e) {
                $profile_error = 'Something went wrong. Please try again.';
            }
        }
    } elseif ($action === 'complete_registration') {
        if (($_SESSION['register_phase'] ?? '') !== 'new' || empty($_SESSION['register_phase_data']['email'])) {
            header('Location: ' . BASE . '/register');
            exit;
        }
        $sessionEmail = $_SESSION['register_phase_data']['email'];
        $postEmail = trim($_POST['email'] ?? '');
        if (strcasecmp($sessionEmail, $postEmail) !== 0) {
            header('Location: ' . BASE . '/register');
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $invited_by = trim($_POST['invited_by'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $num_guests = (int) ($_POST['num_guests'] ?? 1);
        if ($num_guests < 1) {
            $num_guests = 1;
        }
        if ($num_guests > 5) {
            $num_guests = 5;
        }

        $photoResult = register_process_guest_photo_upload($_FILES['photo'] ?? null, $max_guest_photo_bytes, true);
        $photo_path = $photoResult['path'];
        $photo_error = $photoResult['error'];

        if (!isset(guest_valid_titles()[$title])) {
            $form_error = 'Please select your title.';
        } elseif ($first === '' || $last === '') {
            $form_error = 'Please enter your first and last name.';
        } elseif (!in_array($gender, ['male', 'female'], true)) {
            $form_error = 'Please select your gender.';
        } elseif ($photo_error !== null) {
            $form_error = $photo_error;
        } elseif ($photo_path === null) {
            $form_error = 'Please upload a photo for your guest pass.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM guests WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1');
            $dup->execute([$postEmail]);
            if ($dup->fetch()) {
                $form_error = 'This email is already registered. Go back and enter your email to see your status or pass.';
            } else {
                $name = guest_composed_full_name($title, $first, $last);
                $qr_code = strtoupper(bin2hex(random_bytes(8)));
                try {
                    $stmt = $pdo->prepare('INSERT INTO guests (name, email, phone, num_guests, qr_code, invited_by, guest_photo_path, registration_confirmed, first_name, last_name, gender, title) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)');
                    $stmt->execute([
                        $name,
                        $postEmail,
                        $phone,
                        $num_guests,
                        $qr_code,
                        $invited_by !== '' ? $invited_by : null,
                        $photo_path,
                        $first,
                        $last,
                        $gender,
                        $title,
                    ]);
                    unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
                    $_SESSION['register_thanks'] = true;
                    header('Location: ' . BASE . '/register?thanks=1');
                    exit;
                } catch (PDOException $e) {
                    $form_error = 'Something went wrong. Please try again or call us to RSVP.';
                }
            }
        }
    }
}

$phase = $_SESSION['register_phase'] ?? null;
$phaseData = $_SESSION['register_phase_data'] ?? [];
$cardGuest = null;
$profileGuest = null;
$profileSavedFlash = !empty($_SESSION['register_profile_saved']);
if ($profileSavedFlash) {
    unset($_SESSION['register_profile_saved']);
}

if ($phase === 'card' && !empty($phaseData['guest_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? AND registration_confirmed = 1 LIMIT 1');
    $stmt->execute([(int) $phaseData['guest_id']]);
    $cardGuest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$cardGuest) {
        unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
        $phase = null;
    } elseif (guest_profile_needs_completion($cardGuest)) {
        $_SESSION['register_phase'] = 'update_profile';
        $_SESSION['register_phase_data'] = [
            'guest_id' => (int) $cardGuest['id'],
            'email' => trim((string) $cardGuest['email']),
        ];
        header('Location: ' . BASE . '/register');
        exit;
    }
}

if ($phase === 'update_profile' && !empty($phaseData['guest_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $phaseData['guest_id']]);
    $profileGuest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$profileGuest || strcasecmp(trim((string) $profileGuest['email']), trim((string) ($phaseData['email'] ?? ''))) !== 0) {
        unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
        $phase = null;
        $profileGuest = null;
    } elseif (!guest_profile_needs_completion($profileGuest)) {
        if ((int) ($profileGuest['registration_confirmed'] ?? 0) === 1) {
            $_SESSION['register_phase'] = 'card';
            $_SESSION['register_phase_data'] = ['guest_id' => (int) $profileGuest['id']];
            header('Location: ' . BASE . '/register');
            exit;
        }
        $_SESSION['register_phase'] = 'pending';
        $_SESSION['register_phase_data'] = ['email' => trim((string) $profileGuest['email'])];
        header('Location: ' . BASE . '/register');
        exit;
    }
}

include __DIR__ . '/includes/header.php';

if ($thanks):
?>
    <section class="register-success register-success-simple">
        <h1>Registration received</h1>
        <p class="register-success-lead">Thank you for your RSVP. Your details are with us and we will confirm your registration shortly.</p>
        <p class="register-success-detail">Once confirmed, you can return to this page, enter your email, and view or download your access card.</p>
        <p class="register-success-cta"><a href="<?= BASE ?>/register" class="btn">Back to RSVP</a> <a href="<?= BASE ?>/" class="btn btn-secondary" style="margin-left:0.5rem;">Home</a></p>
    </section>
<?php
elseif ($phase === 'card' && $cardGuest):
?>
    <section class="register-access-card-wrap">
        <h1>Your access card</h1>
        <p class="register-access-card-lead">Save the pass below exactly as it appears — same layout and size as on your screen.</p>
        <?= render_guest_access_card($cardGuest, BASE) ?>
        <div class="register-access-card-actions">
            <button type="button" class="btn-submit" style="width:auto;padding:0.75rem 1.5rem;" data-access-card-download aria-label="Download pass as PNG">Download pass (PNG)</button>
            <a class="btn-secondary" href="<?= htmlspecialchars(BASE) ?>/register?new=1">Use another email</a>
        </div>
        <script src="<?= htmlspecialchars(BASE) ?>/assets/js/access-card-download.js" defer></script>
    </section>
<?php
elseif ($phase === 'pending'):
?>
    <section class="register-success register-success-simple">
        <?php if ($profileSavedFlash): ?>
            <p class="alert alert-success" style="max-width:420px;margin:0 auto 1.25rem;text-align:center;">Your profile was updated. Thank you.</p>
        <?php endif; ?>
        <h1>Awaiting confirmation</h1>
        <p class="register-success-lead">We already have an RSVP for <strong><?= htmlspecialchars($phaseData['email'] ?? '') ?></strong>. Your registration is being reviewed and is not confirmed yet.</p>
        <p class="register-success-detail">You will be able to view and download your access card here after we confirm your registration. If you have questions, please use the RSVP numbers on the home page.</p>
        <p class="register-success-cta"><a href="<?= BASE ?>/register?new=1" class="btn">Try a different email</a> <a href="<?= BASE ?>/" class="btn btn-secondary" style="margin-left:0.5rem;">Home</a></p>
    </section>
<?php
elseif ($phase === 'update_profile' && $profileGuest):
    [$defFn, $defLn] = guest_default_first_last_for_form($profileGuest);
    $post = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_guest_profile' ? $_POST : [];
    $pfTitle = trim((string) ($post['title'] ?? $profileGuest['title'] ?? ''));
    $pfFirst = trim((string) ($post['first_name'] ?? $defFn));
    $pfLast = trim((string) ($post['last_name'] ?? $defLn));
    $pfGender = (string) ($post['gender'] ?? $profileGuest['gender'] ?? '');
    $pfInvited = trim((string) ($post['invited_by'] ?? $profileGuest['invited_by'] ?? ''));
    $pfPhone = trim((string) ($post['phone'] ?? $profileGuest['phone'] ?? ''));
    $pfNum = (int) ($post['num_guests'] ?? $profileGuest['num_guests'] ?? 1);
    if ($pfNum < 1) {
        $pfNum = 1;
    }
    if ($pfNum > 5) {
        $pfNum = 5;
    }
    $missing = guest_profile_missing_labels($profileGuest);
    $missingText = $missing === [] ? '' : implode(', ', $missing);
?>
    <section class="form-page">
        <h1>Update your profile</h1>
        <p style="text-align: center; margin-bottom: 1rem; color: var(--chocolate-light);">Signed in as <strong><?= htmlspecialchars((string) $profileGuest['email']) ?></strong>. Please complete the missing details so your guest pass stays accurate.</p>
        <?php if ($missingText !== ''): ?>
            <p class="register-profile-missing-note" style="text-align:center;margin-bottom:1.25rem;font-size:0.95rem;color:var(--chocolate);">We still need: <strong><?= htmlspecialchars($missingText) ?></strong>.</p>
        <?php endif; ?>
        <?php if ($profile_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($profile_error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= BASE ?>/register" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_guest_profile">
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) $profileGuest['email']) ?>">
            <div class="form-group">
                <label for="up_title">Title *</label>
                <select id="up_title" name="title" required>
                    <option value="" disabled <?= $pfTitle === '' ? 'selected' : '' ?>>Select…</option>
                    <?php foreach (guest_valid_titles() as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $pfTitle === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="up_first_name">First name *</label>
                <input type="text" id="up_first_name" name="first_name" required value="<?= htmlspecialchars($pfFirst) ?>">
            </div>
            <div class="form-group">
                <label for="up_last_name">Last name *</label>
                <input type="text" id="up_last_name" name="last_name" required value="<?= htmlspecialchars($pfLast) ?>">
            </div>
            <div class="form-group">
                <label for="up_gender">Gender *</label>
                <select id="up_gender" name="gender" required>
                    <option value="" disabled <?= ($pfGender !== 'male' && $pfGender !== 'female') ? 'selected' : '' ?>>Select…</option>
                    <option value="male" <?= $pfGender === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $pfGender === 'female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="up_invited_by">Who invited you?</label>
                <input type="text" id="up_invited_by" name="invited_by" placeholder="Name of person who invited you" value="<?= htmlspecialchars($pfInvited) ?>">
            </div>
            <div class="form-group">
                <label for="up_num_guests">Number of people (including you)</label>
                <select id="up_num_guests" name="num_guests">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= $pfNum === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="up_phone">Phone</label>
                <input type="tel" id="up_phone" name="phone" value="<?= htmlspecialchars($pfPhone) ?>">
            </div>
            <div class="form-group">
                <label for="up_photo">Your photo <?= guest_has_valid_pass_photo_on_disk($profileGuest) ? '' : '*' ?></label>
                <?php if (guest_has_valid_pass_photo_on_disk($profileGuest)): ?>
                    <?php $thumb = htmlspecialchars(BASE . '/' . ltrim((string) $profileGuest['guest_photo_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>
                    <p class="form-hint" style="margin-bottom:0.5rem;">Current pass photo:</p>
                    <p style="margin-bottom:0.75rem;"><img src="<?= $thumb ?>" alt="" style="max-width:120px;border-radius:8px;border:1px solid var(--cream-dark);"></p>
                    <input type="file" id="up_photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
                    <span class="form-hint">Optional — upload only if you want to replace it (JPG, PNG, GIF or WebP, max 10 MB).</span>
                <?php else: ?>
                    <input type="file" id="up_photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" required>
                    <span class="form-hint">Required for your guest pass. JPG, PNG, GIF or WebP. Maximum size 10 MB.</span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-submit">Save profile</button>
        </form>
        <p style="text-align:center;margin-top:1.25rem;"><a href="<?= htmlspecialchars(BASE) ?>/register?new=1">Use a different email</a></p>
        <script>
        (function () {
            var input = document.getElementById('up_photo');
            if (!input) return;
            var maxBytes = <?= (int) $max_guest_photo_bytes ?>;
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) return;
                if (f.size > maxBytes) {
                    alert('This file is larger than 10 MB. Please choose a smaller photo.');
                    input.value = '';
                }
            });
        })();
        </script>
    </section>
<?php
elseif ($phase === 'new' && !empty($phaseData['email'])):
    $lockedEmail = $phaseData['email'];
?>
    <section class="form-page">
        <h1>Complete your RSVP</h1>
        <p style="text-align: center; margin-bottom: 1.5rem; color: var(--chocolate-light);">We will use <strong><?= htmlspecialchars($lockedEmail) ?></strong> for your invitation. Please add the rest of your details.</p>
        <?php if ($form_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($form_error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= BASE ?>/register" enctype="multipart/form-data">
            <input type="hidden" name="action" value="complete_registration">
            <input type="hidden" name="email" value="<?= htmlspecialchars($lockedEmail) ?>">
            <div class="form-group">
                <label for="title">Title *</label>
                <select id="title" name="title" required>
                    <option value="" disabled <?= empty($_POST['title']) ? 'selected' : '' ?>>Select…</option>
                    <?php foreach (guest_valid_titles() as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= ($_POST['title'] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="first_name">First name *</label>
                <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last name *</label>
                <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select…</option>
                    <option value="male" <?= ($_POST['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="invited_by">Who invited you?</label>
                <input type="text" id="invited_by" name="invited_by" placeholder="Name of person who invited you" value="<?= htmlspecialchars($_POST['invited_by'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="num_guests">Number of people (including you)</label>
                <select id="num_guests" name="num_guests">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= (int) ($_POST['num_guests'] ?? 1) === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="photo">Your photo *</label>
                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp" required>
                <span class="form-hint">Required for your guest pass. JPG, PNG, GIF or WebP. Maximum size 10 MB.</span>
            </div>
            <button type="submit" class="btn-submit">Submit RSVP</button>
        </form>
        <p style="text-align:center;margin-top:1.25rem;"><a href="<?= htmlspecialchars(BASE) ?>/register?new=1">Start over with a different email</a></p>
        <script>
        (function () {
            var input = document.getElementById('photo');
            if (!input) return;
            var maxBytes = <?= (int) $max_guest_photo_bytes ?>;
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) return;
                if (f.size > maxBytes) {
                    alert('This file is larger than 10 MB. Please choose a smaller photo.');
                    input.value = '';
                }
            });
        })();
        </script>
    </section>
<?php
else:
?>
    <section class="form-page">
        <h1>Register to attend</h1>
        <p style="text-align: center; margin-bottom: 1.5rem; color: var(--chocolate-light);">Enter the email you would like us to use for your invitation. We will either show your access card or guide you through the next step.</p>
        <?php if ($email_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($email_error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= BASE ?>/register">
            <input type="hidden" name="action" value="check_email">
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn-submit">Continue</button>
        </form>
    </section>
<?php
endif;
include __DIR__ . '/includes/footer.php';
