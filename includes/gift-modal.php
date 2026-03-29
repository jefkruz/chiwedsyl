<?php
// Requires config (BASE, $bank_details). Opens from .gift-modal-trigger[data-gift-id][data-gift-name]
?>
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
    if (!modal) return;
    var closeBtn = document.getElementById('gift-modal-close');
    var itemIdInput = document.getElementById('gift-modal-item-id');
    var modalTitle = document.getElementById('gift-modal-title');
    var form = document.getElementById('gift-modal-form');

    function openModal(giftId, giftName) {
        if (itemIdInput) itemIdInput.value = giftId || '';
        if (modalTitle) modalTitle.textContent = giftName ? 'Get: ' + giftName : 'Get this gift';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.gift-modal-trigger').forEach(function(el) {
        el.addEventListener('click', function() {
            var id = this.getAttribute('data-gift-id') || '';
            var name = this.getAttribute('data-gift-name') || '';
            openModal(id, name);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(e); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(e);
    });
    if (form) {
        form.addEventListener('submit', function() {
            alert('Thank you! Your transfer confirmation has been received.');
        });
    }
})();
</script>
