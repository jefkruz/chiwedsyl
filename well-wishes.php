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

if (!empty($_SESSION['well_wish_flash_ok'])) {
    unset($_SESSION['well_wish_flash_ok']);
    $message = 'Thank you! Your well wish has been posted.';
    $messageType = 'success';
}

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
        $_SESSION['well_wish_flash_ok'] = 1;
        header('Location: ' . BASE . '/well-wishes');
        exit;
    }
}

$pdo = getDb();
$wishes = $pdo->query("SELECT * FROM well_wishes ORDER BY created_at ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

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

<?php
$show_well_wish_fab = true;
include __DIR__ . '/includes/well-wish-modal.php';
?>

<?php include __DIR__ . '/includes/footer.php'; ?>
