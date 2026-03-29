<?php
require_once __DIR__ . '/config.php';
$current_page = 'gifts';
$page_title = 'Gifts — ' . SITE_NAME;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_transfer'])) {
    $guest_name = trim($_POST['guest_name'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $gift_item_id = !empty($_POST['gift_item_id']) ? (int) $_POST['gift_item_id'] : null;

    if ($guest_name === '' || $amount === '') {
        $message = 'Please enter your name and amount.';
        $messageType = 'error';
    } else {
        $pdo = getDb();
        $stmt = $pdo->prepare("INSERT INTO gift_transfers (gift_item_id, guest_name, amount) VALUES (?, ?, ?)");
        $stmt->execute([$gift_item_id ?: null, $guest_name, $amount]);
        $message = 'Thank you! We appreciate your love and support.';
        $messageType = 'success';
    }
}

$pdo = getDb();
$gifts = $pdo->query("SELECT * FROM gift_items ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="gifts-intro">
    <h2 class="section-title">Gift the couple</h2>
    <p>Your presence is our greatest gift. Choose an item below, pay to our account, then confirm the transfer.</p>
</section>

<?php if ($message): ?>
    <div class="gifts-alert-wrap">
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    </div>
<?php endif; ?>

<section class="gift-shop-section">
        <div class="gift-shop-grid">
            <article class="gift-product-card gift-product-card-cash">
                <div class="gift-product-image">
                    <div class="gift-product-cash-icon" aria-hidden="true">₦</div>
                </div>
                <div class="gift-product-body">
                    <h3 class="gift-product-title">Cash gift</h3>
                    <p class="gift-product-price gift-product-price-any">Any amount</p>
                    <button type="button" class="gift-get-btn gift-modal-trigger" data-gift-id="" data-gift-name="Cash gift">Get</button>
                </div>
            </article>
            <?php foreach ($gifts as $g): ?>
                <article class="gift-product-card">
                    <div class="gift-product-image">
                        <?php if (!empty($g['image_path']) && file_exists($g['image_path'])): ?>
                            <img src="<?= BASE ?>/<?= htmlspecialchars($g['image_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>">
                        <?php else: ?>
                            <div class="gift-product-no-image">No image</div>
                        <?php endif; ?>
                    </div>
                    <div class="gift-product-body">
                        <h3 class="gift-product-title"><?= htmlspecialchars($g['title']) ?></h3>
                        <?php if (isset($g['price']) && $g['price'] !== ''): ?>
                            <p class="gift-product-price"><?= htmlspecialchars(format_gift_price($g['price'] ?? '')) ?></p>
                        <?php endif; ?>
                        <button type="button" class="gift-get-btn gift-modal-trigger" data-gift-id="<?= (int) $g['id'] ?>" data-gift-name="<?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>">Get</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

<?php include __DIR__ . '/includes/gift-modal.php'; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
