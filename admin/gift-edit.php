<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pdo = getDb();
$item = $pdo->prepare("SELECT * FROM gift_items WHERE id = ?");
$item->execute([$id]);
$item = $item->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    header('Location: ' . BASE . '/admin/gifts');
    exit;
}

$uploadDir = rtrim(UPLOAD_PATH, '/') . '/gifts/';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $price = trim($_POST['price'] ?? '');
    if ($title === '') {
        $message = 'Name is required.';
        $messageType = 'error';
    } else {
        $image_path = $item['image_path'];
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $filename = uniqid('gift_') . '.' . $ext;
                $fullPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $fullPath)) {
                    if ($item['image_path'] && file_exists('../' . $item['image_path'])) {
                        unlink('../' . $item['image_path']);
                    }
                    $image_path = 'uploads/gifts/' . $filename;
                }
            }
        }
        $pdo->prepare("UPDATE gift_items SET title = ?, price = ?, image_path = ? WHERE id = ?")
            ->execute([$title, $price ?: null, $image_path, $id]);
        $message = 'Gift updated.';
        $messageType = 'success';
        $item = array_merge($item, ['title' => $title, 'price' => $price, 'image_path' => $image_path]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit gift — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Edit gift</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/gifts">← Back to gifts</a>
            </nav>
        </div>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="admin-card">
            <form method="post" action="<?= BASE ?>/admin/gift-edit?id=<?= $id ?>" enctype="multipart/form-data" style="max-width: 480px;">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="form-group">
                    <label for="title">Name *</label>
                    <input type="text" id="title" name="title" required value="<?= htmlspecialchars($item['title']) ?>">
                </div>
                <div class="form-group">
                    <label>Current image</label>
                    <?php if ($item['image_path'] && file_exists('../' . $item['image_path'])): ?>
                        <img src="../<?= htmlspecialchars($item['image_path']) ?>" alt="" style="max-width: 200px; display: block; margin-bottom: 0.5rem;">
                    <?php else: ?>
                        <p>None</p>
                    <?php endif; ?>
                    <label for="image">Replace image</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
                </div>
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="text" id="price" name="price" value="<?= htmlspecialchars($item['price'] ?? '') ?>" placeholder="e.g. ₦15,000">
                </div>
                <button type="submit" class="btn-submit">Save changes</button>
            </form>
        </div>
    </div>
</body>
</html>
