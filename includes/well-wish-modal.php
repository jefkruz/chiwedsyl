<?php
$show_well_wish_fab = $show_well_wish_fab ?? true;
?>
<?php if ($show_well_wish_fab): ?>
<button type="button" class="tribute-fab" id="tribute-fab" aria-label="Add a well wish">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</button>
<?php endif; ?>

<div class="tribute-modal-overlay" id="tribute-modal" role="dialog" aria-modal="true" aria-labelledby="tribute-modal-title" aria-hidden="true">
    <div class="tribute-modal">
        <div class="tribute-modal-header">
            <h2 id="tribute-modal-title">Add a well wish</h2>
            <button type="button" class="tribute-modal-close" id="tribute-modal-close" aria-label="Close">&times;</button>
        </div>
        <form method="post" action="<?= BASE ?>/well-wishes" class="tribute-modal-form">
            <div class="form-group">
                <label for="tribute-author_name">Your name *</label>
                <input type="text" id="tribute-author_name" name="author_name" required placeholder="Your name">
            </div>
            <div class="form-group">
                <label>Your message *</label>
                <div class="rich-text-toolbar">
                    <button type="button" class="rich-btn" data-cmd="bold" aria-label="Bold" title="Bold"><b>B</b></button>
                    <button type="button" class="rich-btn" data-cmd="italic" aria-label="Italic" title="Italic"><i>I</i></button>
                    <button type="button" class="rich-btn" data-cmd="underline" aria-label="Underline" title="Underline"><u>U</u></button>
                </div>
                <div class="rich-text-editor" id="tribute-message-editor" contenteditable="true" data-placeholder="Write your well wish here…"></div>
                <input type="hidden" name="message" id="tribute-message-input">
            </div>
            <div class="tribute-modal-actions">
                <button type="button" class="btn-cancel" id="tribute-cancel">Cancel</button>
                <button type="submit" class="btn-submit">Post wish</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('tribute-modal');
    if (!modal) return;
    var fab = document.getElementById('tribute-fab');
    var closeBtn = document.getElementById('tribute-modal-close');
    var cancelBtn = document.getElementById('tribute-cancel');
    var editor = document.getElementById('tribute-message-editor');
    var messageInput = document.getElementById('tribute-message-input');
    var form = modal.querySelector('.tribute-modal-form');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var nameEl = document.getElementById('tribute-author_name');
        if (nameEl) nameEl.focus();
    }

    function closeModal(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (fab) fab.addEventListener('click', openModal);

    document.querySelectorAll('.js-open-well-wish-modal').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            openModal();
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', function (e) { closeModal(e); });
    if (cancelBtn) cancelBtn.addEventListener('click', function (e) { closeModal(e); });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal(e);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(e);
    });

    if (form) {
        form.addEventListener('submit', function () {
            if (messageInput && editor) messageInput.value = editor.innerHTML;
        });
    }

    modal.querySelectorAll('.rich-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var cmd = btn.getAttribute('data-cmd');
            document.execCommand(cmd, false, null);
            if (editor) editor.focus();
        });
    });
})();
</script>
