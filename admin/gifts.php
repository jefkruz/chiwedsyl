<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$uploadDir = rtrim(UPLOAD_PATH, '/') . '/gifts/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$message = '';
$messageType = '';

// Delete
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $row = $pdo->query("SELECT image_path FROM gift_items WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("DELETE FROM gift_items WHERE id = ?")->execute([$id]);
    if ($row && $row['image_path'] && file_exists('../' . $row['image_path'])) {
        unlink('../' . $row['image_path']);
    }
    header('Location: ' . BASE . '/admin/gifts?deleted=1');
    exit;
}

if (isset($_GET['deleted'])) {
    $message = 'Gift item deleted.';
    $messageType = 'success';
}

// Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($title === '') {
        $message = 'Title is required.';
        $messageType = 'error';
    } else {
        $image_path = null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $filename = uniqid('gift_') . '.' . $ext;
                $fullPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $fullPath)) {
                    $image_path = 'uploads/gifts/' . $filename;
                }
            }
        }

        if ($id > 0) {
            $row = $pdo->query("SELECT image_path FROM gift_items WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            $path = $image_path ?: ($row['image_path'] ?? null);
            $pdo->prepare("UPDATE gift_items SET title = ?, description = ?, image_path = ? WHERE id = ?")
                ->execute([$title, $description, $path, $id]);
            $message = 'Gift updated.';
        } else {
            $pdo->prepare("INSERT INTO gift_items (title, description, image_path) VALUES (?, ?, ?)")
                ->execute([$title, $description, $image_path]);
            $message = 'Gift added.';
        }
        $messageType = 'success';
    }
}

$gifts = $pdo->query("SELECT * FROM gift_items ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gifts — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="admin-wrap">
        <div class="admin-header">
            <h1>Gift items</h1>
            <nav class="admin-nav">
                <a href="<?= BASE ?>/admin/dashboard">Dashboard</a>
                <a href="<?= BASE ?>/admin/guests">Guests</a>
                <a href="<?= BASE ?>/admin/receipts">Receipts</a>
                <a href="<?= BASE ?>/admin/gallery">Gallery</a>
                <a href="<?= BASE ?>/">View site</a>
                <a href="<?= BASE ?>/admin/logout">Log out</a>
            </nav>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="admin-card">
            <h2>Add new gift</h2>
            <form method="post" action="<?= BASE ?>/admin/gifts" enctype="multipart/form-data" style="max-width: 480px;">
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="image">Image</label>
                    <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif,.webp">
                </div>
                <button type="submit" class="btn-submit">Add gift</button>
            </form>
        </div>

        <div class="admin-card">
            <h2>Current gifts</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gifts as $g): ?>
                            <tr>
                                <td>
                                    <?php if ($g['image_path'] && file_exists('../' . $g['image_path'])): ?>
                                        <img src="../<?= htmlspecialchars($g['image_path']) ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($g['title']) ?></td>
                                <td><?= nl2br(htmlspecialchars(mb_substr($g['description'] ?? '', 0, 80))) ?><?= mb_strlen($g['description'] ?? '') > 80 ? '…' : '' ?></td>
                                <td>
                                    <a href="<?= BASE ?>/admin/gift-edit?id=<?= (int) $g['id'] ?>" class="btn-small">Edit</a>
                                    <a href="<?= BASE ?>/admin/gifts?delete=<?= (int) $g['id'] ?>" class="btn-small danger" onclick="return confirm('Delete this gift?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
