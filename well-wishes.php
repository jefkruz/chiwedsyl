<?php
require_once __DIR__ . '/config.php';
$current_page = 'well-wishes';
$page_title = 'Well Wishes — ' . SITE_NAME;

function sanitize_wish_html(string $html): string {
    $allowed = '<p><br><b><strong><i><em><u>';
    return strip_tags($html, $allowed);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author_name = trim($_POST['author_name'] ?? '');
    $message_raw = trim($_POST['message'] ?? '');

    if ($author_name === '') {
        $message = 'Please enter your name.';
        $messageType = 'error';
    } elseif ($message_raw === '') {
        $message = 'Please enter your message.';
        $messageType = 'error';
    } else {
        $message_html = sanitize_wish_html($message_raw);
        if ($message_html === '') {
            $message_html = nl2br(htmlspecialchars($message_raw));
        }
        $pdo = getDb();
        $stmt = $pdo->prepare("INSERT INTO well_wishes (author_name, message) VALUES (?, ?)");
        $stmt->execute([$author_name, $message_html]);
        $message = 'Thank you! Your well wish has been posted.';
        $messageType = 'success';
    }
}

$pdo = getDb();
$wishes = $pdo->query("SELECT * FROM well_wishes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="tributes-hero">
    <h1 class="tributes-title">Well Wishes</h1>
    <p class="tributes-subtitle">Messages for the couple</p>
</section>

<?php if ($message): ?>
    <div class="tributes-alert-wrap">
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    </div>
<?php endif; ?>

<section class="tributes-list-section">
    <?php if (empty($wishes)): ?>
        <div class="tributes-empty">
            <p>No well wishes yet.</p>
            <p>Tap the button below to leave a message for the couple.</p>
        </div>
    <?php else: ?>
        <div class="tributes-grid">
            <?php foreach ($wishes as $w): ?>
                <article class="tribute-card">
                    <div class="tribute-message"><?= $w['message'] ?></div>
                    <footer class="tribute-meta">
                        <span class="tribute-author"><?= htmlspecialchars($w['author_name']) ?></span>
                        <time class="tribute-date" datetime="<?= htmlspecialchars($w['created_at']) ?>"><?= date('M j, Y', strtotime($w['created_at'])) ?></time>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<button type="button" class="tribute-fab" id="tribute-fab" aria-label="Add a well wish">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</button>

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
    var fab = document.getElementById('tribute-fab');
    var modal = document.getElementById('tribute-modal');
    var closeBtn = document.getElementById('tribute-modal-close');
    var cancelBtn = document.getElementById('tribute-cancel');
    var editor = document.getElementById('tribute-message-editor');
    var messageInput = document.getElementById('tribute-message-input');
    var form = document.querySelector('.tribute-modal-form');

    function openModal() {
        if (!modal) return;
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
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (fab) fab.addEventListener('click', openModal);

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) { closeModal(e); });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) { closeModal(e); });
    }

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal(e);
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) closeModal(e);
    });

    if (form) {
        form.addEventListener('submit', function() {
            if (messageInput && editor) messageInput.value = editor.innerHTML;
        });
    }

    var richBtns = document.querySelectorAll('.rich-btn');
    richBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var cmd = btn.getAttribute('data-cmd');
            document.execCommand(cmd, false, null);
            if (editor) editor.focus();
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
