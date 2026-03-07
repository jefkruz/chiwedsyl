<?php
require_once __DIR__ . '/config.php';
$current_page = 'gifts';
$page_title = 'Gifts — ' . SITE_NAME;

$message = '';
$messageType = '';

// Handle receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_receipt'])) {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $guest_email = trim($_POST['guest_email'] ?? '');
    $gift_item_id = !empty($_POST['gift_item_id']) ? (int) $_POST['gift_item_id'] : null;
    $msg = trim($_POST['message'] ?? '');

    if ($guest_name === '' || $guest_email === '') {
        $message = 'Please enter your name and email.';
        $messageType = 'error';
    } elseif (empty($_FILES['receipt']['name']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please select a valid receipt image.';
        $messageType = 'error';
    } else {
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'], true)) {
            $message = 'Allowed formats: JPG, PNG, GIF, PDF.';
            $messageType = 'error';
        } else {
            $uploadDir = UPLOAD_PATH . '/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid('receipt_') . '.' . $ext;
            $path = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $path)) {
                $pdo = getDb();
                $stmt = $pdo->prepare("INSERT INTO receipts (guest_name, guest_email, gift_item_id, receipt_path, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$guest_name, $guest_email, $gift_item_id ?: null, 'uploads/receipts/' . $filename, $msg]);
                $message = 'Thank you! Your receipt has been uploaded successfully.';
                $messageType = 'success';
            } else {
                $message = 'Upload failed. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$pdo = getDb();
$gifts = $pdo->query("SELECT * FROM gift_items ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="gifts-intro">
    <h2 class="section-title">Gift the couple</h2>
    <p>Your presence is our greatest gift. If you wish to give something more, you can choose from our gift list below or send a contribution to our account. After payment, please upload your receipt.</p>
</section>

<div class="bank-details">
    <h3>Bank details</h3>
    <p><strong>Bank:</strong> <?= htmlspecialchars($bank_details['bank_name']) ?></p>
    <p><strong>Account name:</strong> <?= htmlspecialchars($bank_details['account_name']) ?></p>
    <p class="account-no"><?= htmlspecialchars($bank_details['account_no']) ?></p>
    <?php if (!empty($bank_details['sort_code'])): ?>
        <p><strong>Sort code:</strong> <?= htmlspecialchars($bank_details['sort_code']) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($gifts)): ?>
    <h2 class="section-title" style="padding-top: 1rem;">Gift ideas</h2>
    <div class="gift-grid">
        <?php foreach ($gifts as $g): ?>
            <div class="gift-card">
                <?php if (!empty($g['image_path']) && file_exists($g['image_path'])): ?>
                    <img src="<?= BASE ?>/<?= htmlspecialchars($g['image_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>">
                <?php else: ?>
                    <div style="height: 200px; background: var(--cream-dark); display: flex; align-items: center; justify-content: center; color: var(--chocolate-light);">No image</div>
                <?php endif; ?>
                <div class="gift-card-body">
                    <h3><?= htmlspecialchars($g['title']) ?></h3>
                    <?php if (!empty($g['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($g['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="upload-receipt-form">
    <h3>Upload your receipt</h3>
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= BASE ?>/gifts" enctype="multipart/form-data">
        <input type="hidden" name="upload_receipt" value="1">
        <div class="form-group">
            <label for="guest_name">Your name *</label>
            <input type="text" id="guest_name" name="guest_name" required value="<?= htmlspecialchars($_POST['guest_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="guest_email">Your email *</label>
            <input type="email" id="guest_email" name="guest_email" required value="<?= htmlspecialchars($_POST['guest_email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="gift_item_id">Gift (optional)</label>
            <select id="gift_item_id" name="gift_item_id">
                <option value="">— General gift —</option>
                <?php foreach ($gifts as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="receipt">Receipt (image or PDF) *</label>
            <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.gif,.pdf" required>
        </div>
        <div class="form-group">
            <label for="message">Message (optional)</label>
            <textarea id="message" name="message"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn-submit">Upload receipt</button>
    </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
