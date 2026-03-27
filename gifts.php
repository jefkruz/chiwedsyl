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
                    <button type="button" class="gift-get-btn" data-gift-id="" data-gift-name="Cash gift">Get</button>
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
                        <button type="button" class="gift-get-btn" data-gift-id="<?= (int) $g['id'] ?>" data-gift-name="<?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>">Get</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

<div class="gift-modal-overlay" id="gift-modal" aria-hidden="true">
    <div class="gift-modal">
        <div class="gift-modal-header">
            <h2 id="gift-modal-title">Get this gift</h2>
            <button type="button" class="gift-modal-close" id="gift-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="gift-modal-body">
            <div class="gift-modal-bank">
                <h3>Pay to this account</h3>
                <p><strong>Bank:</strong> <?= htmlspecialchars($bank_details['bank_name']) ?></p>
                <p><strong>Account name:</strong> <?= htmlspecialchars($bank_details['account_name']) ?></p>
                <p class="account-no"><?= htmlspecialchars($bank_details['account_no']) ?></p>
                <?php if (!empty($bank_details['sort_code'])): ?>
                    <p><strong>Sort code:</strong> <?= htmlspecialchars($bank_details['sort_code']) ?></p>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= BASE ?>/gifts" class="gift-modal-form" id="gift-modal-form">
                <input type="hidden" name="confirm_transfer" value="1">
                <input type="hidden" name="gift_item_id" id="gift-modal-item-id" value="">
                <div class="form-group">
                    <label for="gift-modal-name">Your name *</label>
                    <input type="text" id="gift-modal-name" name="guest_name" required placeholder="Your full name">
                </div>
                <div class="form-group">
                    <label for="gift-modal-amount">Amount *</label>
                    <input type="text" id="gift-modal-amount" name="amount" required placeholder="e.g. ₦20,000">
                </div>
                <button type="submit" class="btn-submit">I have made the transfer</button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('gift-modal');
    var closeBtn = document.getElementById('gift-modal-close');
    var itemIdInput = document.getElementById('gift-modal-item-id');
    var modalTitle = document.getElementById('gift-modal-title');
    var form = document.getElementById('gift-modal-form');

    function openModal(giftId, giftName) {
        if (!modal) return;
        if (itemIdInput) itemIdInput.value = giftId || '';
        if (modalTitle) modalTitle.textContent = giftName ? 'Get: ' + giftName : 'Get this gift';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.gift-get-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-gift-id');
            var name = this.getAttribute('data-gift-name') || '';
            openModal(id, name);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(e); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal(e);
    });
    if (form) {
        form.addEventListener('submit', function() {
            alert('Thank you! Your transfer confirmation has been received.');
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
