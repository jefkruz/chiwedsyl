<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';

$pdo = getDb();
$uploadDir = rtrim(UPLOAD_PATH, '/') . '/gallery/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$message = '';
$messageType = '';

if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $row = $pdo->query("SELECT image_path FROM gallery_images WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("DELETE FROM gallery_images WHERE id = ?")->execute([$id]);
    if ($row && $row['image_path'] && file_exists('../' . $row['image_path'])) {
        @unlink('../' . $row['image_path']);
    }
    header('Location: ' . BASE . '/admin/gallery?deleted=1');
    exit;
}

if (isset($_GET['deleted'])) {
    $message = 'Image deleted.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['image']['name'])) {
    $caption = trim($_POST['caption'] ?? '');
    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $filename = uniqid('gallery_') . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $path = 'uploads/gallery/' . $filename;
                $pdo->prepare("INSERT INTO gallery_images (image_path, caption) VALUES (?, ?)")->execute([$path, $caption ?: null]);
                $message = 'Image uploaded.';
                $messageType = 'success';
            } else {
                $message = 'Upload failed.';
                $messageType = 'error';
            }
        } else {
            $message = 'Use JPG, PNG, GIF or WebP.';
            $messageType = 'error';
        }
    }
}

$images = $pdo->query("SELECT * FROM gallery_images ORDER BY sort_order, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Gallery', 'gallery'); ?>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="admin-card">
            <h2>Upload image</h2>
            <form method="post" action="<?= BASE ?>/admin/gallery" enctype="multipart/form-data" class="admin-form-narrow--sm">
                <div class="form-group">
                    <label for="image">Image *</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                </div>
                <div class="form-group">
                    <label for="caption">Caption (optional)</label>
                    <input type="text" id="caption" name="caption">
                </div>
                <button type="submit" class="btn-submit">Upload</button>
            </form>
        </div>
        <div class="admin-card">
            <h2>Gallery images</h2>
            <div class="gallery-admin-grid">
                <?php foreach ($images as $img): ?>
                    <div class="gallery-admin-item">
                        <?php if ($img['image_path'] && file_exists('../' . $img['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($img['image_path']) ?>" alt="">
                        <?php else: ?>
                            <div class="gallery-admin-placeholder">Missing</div>
                        <?php endif; ?>
                        <p class="gallery-admin-caption"><?= htmlspecialchars($img['caption'] ?? '—') ?></p>
                        <a href="<?= BASE ?>/admin/gallery?delete=<?= (int) $img['id'] ?>" class="btn-small danger" onclick="return confirm('Delete this image?');">Delete</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
<?php admin_lte_layout_end(); ?>
