<?php
require_once __DIR__ . '/config.php';
$current_page = 'well-wishes';
$page_title = 'Well Wishes — ' . SITE_NAME;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $author_name = trim($_POST['author_name'] ?? '');
    $message_text = trim($_POST['message'] ?? '');

    if ($author_name === '' || $message_text === '') {
        $message = 'Please enter your name and your wish.';
        $messageType = 'error';
    } else {
        $pdo = getDb();
        $stmt = $pdo->prepare("INSERT INTO well_wishes (author_name, message) VALUES (?, ?)");
        $stmt->execute([$author_name, $message_text]);
        $message = 'Thank you! Your well wish has been posted.';
        $messageType = 'success';
    }
}

$pdo = getDb();
$wishes = $pdo->query("SELECT * FROM well_wishes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="wishes-intro">
    <h1 class="section-title">Well Wishes</h1>
    <p class="wishes-tagline">Leave a message for the couple</p>
</section>

<section class="wish-form-section">
    <div class="wish-form-card">
        <h2>Share your wish</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= BASE ?>/well-wishes">
            <div class="form-group">
                <label for="author_name">Your name *</label>
                <input type="text" id="author_name" name="author_name" required placeholder="e.g. Ada" value="<?= htmlspecialchars($_POST['author_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="message">Your message *</label>
                <textarea id="message" name="message" required placeholder="Write your well wish here…" rows="4"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-submit">Post wish</button>
        </form>
    </div>
</section>

<section class="wishes-list-section">
    <h2 class="wishes-list-title">Messages for the couple</h2>
    <?php if (empty($wishes)): ?>
        <p class="wishes-empty">No well wishes yet. Be the first to leave one above.</p>
    <?php else: ?>
        <div class="wishes-grid">
            <?php foreach ($wishes as $w): ?>
                <article class="wish-card">
                    <blockquote class="wish-message"><?= nl2br(htmlspecialchars($w['message'])) ?></blockquote>
                    <footer class="wish-author">— <?= htmlspecialchars($w['author_name']) ?></footer>
                    <time class="wish-date" datetime="<?= htmlspecialchars($w['created_at']) ?>"><?= date('M j, Y', strtotime($w['created_at'])) ?></time>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
