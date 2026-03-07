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
            ];
            $success = true;
        } catch (PDOException $e) {
            $error = 'Something went wrong. Please try again or call us to RSVP.';
        }
    }
}

include __DIR__ . '/includes/header.php';

if ($success && $guest):
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($guest['qr_code']);
?>
    <section class="register-success">
        <h1>You're on the list!</h1>
        <p>Thank you, <?= htmlspecialchars($guest['name']) ?>. We can't wait to celebrate with you.</p>
        <div class="qr-wrap">
            <img src="<?= htmlspecialchars($qr_url) ?>" alt="Your RSVP QR code" width="220" height="220">
        </div>
        <p class="qr-note">Please show this QR code at the venue for a smooth check-in.</p>
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
