<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/wedding-highlight-upload.php';

$current_page = 'wedding-highlights';
$page_title = 'Wedding Highlights — ' . SITE_NAME;
$maxHighlightBytes = 10 * 1024 * 1024;

$message = '';
$messageType = '';

if (!empty($_SESSION['wedding_highlight_flash_ok'])) {
    unset($_SESSION['wedding_highlight_flash_ok']);
    $message = 'Thank you! Your photo has been shared.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authorName = trim($_POST['author_name'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $hashtags = wedding_highlight_normalize_hashtags(trim($_POST['hashtags'] ?? ''));

    if (mb_strlen($note) > 280) {
        $message = 'Please keep your note to 280 characters or fewer.';
        $messageType = 'error';
    } elseif (mb_strlen($hashtags) > 200) {
        $message = 'Please use fewer hashtags.';
        $messageType = 'error';
    } elseif (mb_strlen($authorName) > 80) {
        $message = 'Please use a shorter name.';
        $messageType = 'error';
    } else {
        $upload = wedding_highlight_process_upload($_FILES['photo'] ?? null, $maxHighlightBytes);
        if ($upload['error'] !== null) {
            $message = $upload['error'];
            $messageType = 'error';
        } else {
            $pdo = getDb();
            $stmt = $pdo->prepare(
                'INSERT INTO wedding_highlights (image_path, note, hashtags, author_name, is_visible) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $upload['path'],
                $note !== '' ? $note : null,
                $hashtags !== '' ? $hashtags : null,
                $authorName !== '' ? $authorName : null,
            ]);
            $_SESSION['wedding_highlight_flash_ok'] = 1;
            header('Location: ' . BASE . '/wedding-highlights');
            exit;
        }
    }
}

$pdo = getDb();
$highlights = wedding_highlight_fetch_visible($pdo);

include __DIR__ . '/includes/header.php';
?>

<section class="wedding-highlights-hero">
    <h1 class="section-title">Wedding Highlights</h1>
    <p class="wedding-highlights-tagline">Share your favourite moments from the celebration</p>
</section>

<?php if ($message): ?>
    <div class="tributes-alert-wrap">
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    </div>
<?php endif; ?>

<section class="wedding-highlights-upload-section form-page">
    <h2 class="wedding-highlights-form-title">Upload a photo</h2>
    <form method="post" action="<?= BASE ?>/wedding-highlights" enctype="multipart/form-data" class="wedding-highlights-form">
        <div class="form-group">
            <label for="highlight-photo">Photo *</label>
            <input type="file" id="highlight-photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
            <p class="form-hint">JPG, PNG, GIF or WebP. Max 10 MB.</p>
        </div>
        <div class="form-group">
            <label for="highlight-author">Your name (optional)</label>
            <input type="text" id="highlight-author" name="author_name" maxlength="80" value="<?= htmlspecialchars($_POST['author_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="highlight-note">Short note (optional)</label>
            <textarea id="highlight-note" name="note" rows="3" maxlength="280" placeholder="A quick caption from the day…"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="highlight-hashtags">Hashtags (optional)</label>
            <input type="text" id="highlight-hashtags" name="hashtags" maxlength="200" placeholder="#OmaSyl2026 #RhapsodyOfEndlesslove" value="<?= htmlspecialchars($_POST['hashtags'] ?? '') ?>">
            <p class="form-hint">Separate with spaces. The # is added for you if you leave it out.</p>
        </div>
        <button type="submit" class="btn-submit">Share photo</button>
    </form>
</section>

<section class="wedding-highlights-grid-section">
    <h2 class="section-title">From our guests</h2>
    <?php if ($highlights === []): ?>
        <div class="wedding-highlights-empty">
            <p>No photos shared yet. Be the first to upload a moment from the wedding.</p>
        </div>
    <?php else: ?>
        <div class="wedding-highlights-grid">
            <?php foreach ($highlights as $h): ?>
                <?php
                $imgUrl = BASE . '/' . ltrim((string) $h['image_path'], '/');
                $note = trim((string) ($h['note'] ?? ''));
                $tags = trim((string) ($h['hashtags'] ?? ''));
                $author = trim((string) ($h['author_name'] ?? ''));
                ?>
                <article class="wedding-highlight-card">
                    <a href="<?= htmlspecialchars($imgUrl) ?>" class="wedding-highlight-image-link" target="_blank" rel="noopener noreferrer">
                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="<?= $author !== '' ? htmlspecialchars('Photo by ' . $author) : 'Wedding highlight' ?>">
                    </a>
                    <div class="wedding-highlight-card-body">
                        <?php if ($note !== ''): ?>
                            <p class="wedding-highlight-note"><?= nl2br(htmlspecialchars($note)) ?></p>
                        <?php endif; ?>
                        <?php if ($tags !== ''): ?>
                            <p class="wedding-highlight-tags"><?= htmlspecialchars($tags) ?></p>
                        <?php endif; ?>
                        <?php if ($author !== ''): ?>
                            <p class="wedding-highlight-author"><?= htmlspecialchars($author) ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
