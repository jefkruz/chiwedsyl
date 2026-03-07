<?php
require_once __DIR__ . '/config.php';
$current_page = 'gallery';
$page_title = 'Gallery — ' . SITE_NAME;

$pdo = getDb();
$images = $pdo->query("SELECT * FROM gallery_images ORDER BY sort_order, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="gallery-intro">
    <h1 class="section-title">Gallery</h1>
    <p class="gallery-tagline">Moments from our journey</p>
</section>

<?php if (empty($images)): ?>
    <section class="gallery-empty">
        <p>Photos will be shared here soon.</p>
    </section>
<?php else: ?>
    <section class="gallery-grid-wrap">
        <div class="gallery-grid">
            <?php foreach ($images as $img): ?>
                <?php if (!empty($img['image_path']) && file_exists(__DIR__ . '/' . $img['image_path'])): ?>
                    <div class="gallery-item">
                        <div class="img-frame img-frame-gallery">
                            <a href="<?= BASE ?>/<?= htmlspecialchars($img['image_path']) ?>" target="_blank" rel="noopener">
                                <img src="<?= BASE ?>/<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['caption'] ?? 'Gallery photo') ?>">
                            </a>
                        </div>
                        <?php if (!empty($img['caption'])): ?>
                            <p class="gallery-caption"><?= htmlspecialchars($img['caption']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
