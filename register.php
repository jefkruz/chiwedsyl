<?php
require_once __DIR__ . '/config.php';
$current_page = 'register';
$page_title = 'RSVP — ' . SITE_NAME;

$success = false;
$guest = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $invited_by = trim($_POST['invited_by'] ?? '');
    $num_guests = (int) ($_POST['num_guests'] ?? 1);
    if ($num_guests < 1) $num_guests = 1;
    if ($num_guests > 5) $num_guests = 5;

    $photo_path = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $uploadDir = UPLOAD_PATH . '/guests/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid('guest_') . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photo_path = 'uploads/guests/' . $filename;
            }
        }
    }

    if ($name === '' || $email === '') {
        $error = 'Please enter your name and email.';
    } else {
        $pdo = getDb();
        $qr_code = strtoupper(bin2hex(random_bytes(8)));
        try {
            $stmt = $pdo->prepare("INSERT INTO guests (name, email, phone, num_guests, qr_code, invited_by, guest_photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $num_guests, $qr_code, $invited_by ?: null, $photo_path]);
            $guest = [
                'name' => $name,
                'email' => $email,
                'qr_code' => $qr_code,
                'photo_path' => $photo_path,
                'num_guests' => $num_guests,
            ];
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again or call us to RSVP.';
        }
    }
}

include __DIR__ . '/includes/header.php';

if ($success && $guest):
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($guest['qr_code']);
    $guestPhotoRel = $guest['photo_path'] ?? '';
    $guestPhotoFull = $guestPhotoRel !== '' ? __DIR__ . '/' . ltrim(str_replace('\\', '/', $guestPhotoRel), '/') : '';
    $guestPhotoUrl = ($guestPhotoRel !== '' && is_file($guestPhotoFull)) ? (BASE . '/' . $guestPhotoRel) : '';
    $nameTrim = trim($guest['name']);
    if (function_exists('mb_substr')) {
        $guestInitial = mb_strtoupper(mb_substr($nameTrim, 0, 1, 'UTF-8'), 'UTF-8');
    } else {
        $guestInitial = strtoupper(substr($nameTrim, 0, 1));
    }
    if ($guestInitial === '') {
        $guestInitial = '?';
    }
?>
    <section class="register-success">
        <h1>You're on the list!</h1>
        <p class="register-success-lead">Thank you, <?= htmlspecialchars($guest['name']) ?>. We can't wait to celebrate with you.</p>

        <div class="guest-pass-card" aria-label="Your event access pass">
            <div class="guest-pass-card-inner">
                <header class="guest-pass-header">
                    <span class="guest-pass-event">OmaSyl 2026</span>
                    <span class="guest-pass-type">Guest access</span>
                </header>
                <div class="guest-pass-body">
                    <div class="guest-pass-photo-frame">
                        <?php if ($guestPhotoUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($guestPhotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="guest-pass-photo" width="140" height="140">
                        <?php else: ?>
                            <div class="guest-pass-photo guest-pass-photo--placeholder" aria-hidden="true"><?= htmlspecialchars($guestInitial, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="guest-pass-identity">
                        <p class="guest-pass-name"><?= htmlspecialchars($guest['name']) ?></p>
                        <?php if (($guest['num_guests'] ?? 1) > 1): ?>
                            <p class="guest-pass-party">Party of <?= (int) $guest['num_guests'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="guest-pass-qr-block">
                    <img src="<?= htmlspecialchars($qr_url, ENT_QUOTES, 'UTF-8') ?>" alt="Check-in QR code" class="guest-pass-qr-img" width="200" height="200" loading="lazy" decoding="async">
                    <p class="guest-pass-scan">Scan at check-in</p>
                </div>
                <footer class="guest-pass-footer">
                    <span class="guest-pass-chip"><?= htmlspecialchars($guest['qr_code']) ?></span>
                </footer>
            </div>
        </div>

        <p class="qr-note">Save a screenshot or show this pass on your phone at the venue.</p>
        <p><a href="<?= BASE ?>/" class="btn">Back to home</a></p>
    </section>
<?php
else:
?>
    <section class="form-page">
        <h1>Register to attend</h1>
        <p style="text-align: center; margin-bottom: 1.5rem; color: var(--chocolate-light);">Fill in your details so we can save you a seat.</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= BASE ?>/register" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Full name *</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="invited_by">Invited by</label>
                <input type="text" id="invited_by" name="invited_by" placeholder="Name of person who invited you" value="<?= htmlspecialchars($_POST['invited_by'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="num_guests">Number of guests (including you)</label>
                <select id="num_guests" name="num_guests">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_POST['num_guests'] ?? 1) == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="photo">Upload a photo (optional)</label>
                <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp">
                <span class="form-hint">JPG, PNG, GIF or WebP. Optional.</span>
            </div>
            <button type="submit" class="btn-submit">Submit RSVP</button>
        </form>
    </section>
<?php
endif;
include __DIR__ . '/includes/footer.php';
