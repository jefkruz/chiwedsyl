<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/guest-access-card.php';

$current_page = 'register';
$page_title = 'RSVP — ' . SITE_NAME;

$max_guest_photo_bytes = 10 * 1024 * 1024;

if (isset($_GET['new']) && $_GET['new'] === '1') {
    unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
    header('Location: ' . BASE . '/register');
    exit;
}

// Download access card (confirmed guest, same session as card view)
if (isset($_GET['download_card']) && $_GET['download_card'] === '1') {
    if (($_SESSION['register_phase'] ?? '') !== 'card') {
        header('Location: ' . BASE . '/register');
        exit;
    }
    $gid = (int) ($_SESSION['register_phase_data']['guest_id'] ?? 0);
    if ($gid < 1) {
        header('Location: ' . BASE . '/register');
        exit;
    }
    $pdo = getDb();
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? AND registration_confirmed = 1 LIMIT 1');
    $stmt->execute([$gid]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$g) {
        header('Location: ' . BASE . '/register');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . guest_access_card_download_filename($g) . '"');
    echo render_guest_access_card_document($g, BASE);
    exit;
}

$pdo = getDb();
$error = '';
$email_error = '';
$form_error = '';
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
                $confirmed = (int) ($row['registration_confirmed'] ?? 0) === 1;
                if ($confirmed) {
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

        $photo_path = null;
        $photo_error = null;
        $fu = $_FILES['photo'] ?? null;
        $fileErr = is_array($fu) ? (int) ($fu['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $fileName = is_array($fu) ? trim((string) ($fu['name'] ?? '')) : '';

        if ($fileErr === UPLOAD_ERR_NO_FILE || $fileName === '') {
            $photo_error = 'Please upload a photo for your guest pass (JPG, PNG, GIF or WebP, max 10 MB).';
        } elseif ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE) {
            $photo_error = 'Your photo must be 10 MB or smaller.';
        } elseif ($fileErr !== UPLOAD_ERR_OK) {
            $photo_error = 'Photo upload failed. Please try again.';
        } elseif ((int) ($fu['size'] ?? 0) > $max_guest_photo_bytes) {
            $photo_error = 'Your photo must be 10 MB or smaller.';
        } else {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $photo_error = 'Please use a JPG, PNG, GIF or WebP image.';
            } else {
                $uploadDir = UPLOAD_PATH . '/guests/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = uniqid('guest_') . '.' . $ext;
                if (move_uploaded_file($fu['tmp_name'], $uploadDir . $filename)) {
                    $photo_path = 'uploads/guests/' . $filename;
                } else {
                    $photo_error = 'Could not save your photo. Please try again.';
                }
            }
        }

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
if ($phase === 'card' && !empty($phaseData['guest_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE id = ? AND registration_confirmed = 1 LIMIT 1');
    $stmt->execute([(int) $phaseData['guest_id']]);
    $cardGuest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$cardGuest) {
        unset($_SESSION['register_phase'], $_SESSION['register_phase_data']);
        $phase = null;
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
        <p class="register-access-card-lead">Save or print this pass for the celebration. You can download a copy to your device.</p>
        <?= render_guest_access_card($cardGuest, BASE) ?>
        <div class="register-access-card-actions">
            <button type="button" class="btn-submit" style="width:auto;padding:0.75rem 1.5rem;" onclick="window.print()">Print</button>
            <a class="btn-submit" style="width:auto;padding:0.75rem 1.5rem;text-decoration:none;display:inline-block;" href="<?= htmlspecialchars(BASE) ?>/register?download_card=1">Download</a>
            <a class="btn-secondary" href="<?= htmlspecialchars(BASE) ?>/register?new=1">Use another email</a>
        </div>
    </section>
<?php
elseif ($phase === 'pending'):
?>
    <section class="register-success register-success-simple">
        <h1>Awaiting confirmation</h1>
        <p class="register-success-lead">We already have an RSVP for <strong><?= htmlspecialchars($phaseData['email'] ?? '') ?></strong>. Your registration is being reviewed and is not confirmed yet.</p>
        <p class="register-success-detail">You will be able to view and download your access card here after we confirm your registration. If you have questions, please use the RSVP numbers on the home page.</p>
        <p class="register-success-cta"><a href="<?= BASE ?>/register?new=1" class="btn">Try a different email</a> <a href="<?= BASE ?>/" class="btn btn-secondary" style="margin-left:0.5rem;">Home</a></p>
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
