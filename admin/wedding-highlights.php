<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';
require_once __DIR__ . '/../includes/wedding-highlight-upload.php';

$pdo = getDb();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visible_id'])) {
    $toggleId = (int) $_POST['toggle_visible_id'];
    if ($toggleId > 0) {
        $pdo->prepare(
            'UPDATE wedding_highlights SET is_visible = CASE WHEN COALESCE(is_visible, 1) = 1 THEN 0 ELSE 1 END WHERE id = ?'
        )->execute([$toggleId]);
    }
    header('Location: ' . BASE . '/admin/wedding-highlights?updated=1');
    exit;
}

if (isset($_GET['delete']) && ctype_digit((string) $_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $row = $pdo->prepare('SELECT image_path FROM wedding_highlights WHERE id = ?');
    $row->execute([$id]);
    $image = $row->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare('DELETE FROM wedding_highlights WHERE id = ?')->execute([$id]);
    if ($image) {
        wedding_highlight_delete_file($image['image_path'] ?? null);
    }
    header('Location: ' . BASE . '/admin/wedding-highlights?deleted=1');
    exit;
}

if (isset($_GET['deleted'])) {
    $message = 'Highlight removed.';
    $messageType = 'success';
} elseif (isset($_GET['updated'])) {
    $message = 'Highlight updated.';
    $messageType = 'success';
}

$highlights = $pdo->query('SELECT * FROM wedding_highlights ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding highlights — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/css/responsive.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Wedding highlights', 'wedding-highlights'); ?>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="admin-card">
            <p>Guest photos from the wedding day. Hide a photo to remove it from the home page and public gallery without deleting the file.</p>
            <?php if ($highlights === []): ?>
                <p>No guest highlights yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="js-datatable responsive-table table table-striped table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Note</th>
                                <th>Hashtags</th>
                                <th>Date</th>
                                <th>Visible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($highlights as $h): ?>
                                <?php
                                $visible = (int) ($h['is_visible'] ?? 1) === 1;
                                $imgPath = trim((string) ($h['image_path'] ?? ''));
                                $imgUrl = $imgPath !== '' ? BASE . '/' . ltrim($imgPath, '/') : '';
                                ?>
                                <tr>
                                    <td data-label="Photo">
                                        <?php if ($imgUrl !== '' && wedding_highlight_image_exists($h)): ?>
                                            <a href="<?= htmlspecialchars($imgUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="admin-highlight-thumb">
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Name"><?= htmlspecialchars((string) ($h['author_name'] ?? '—')) ?></td>
                                    <td data-label="Note"><?= htmlspecialchars((string) ($h['note'] ?? '')) ?></td>
                                    <td data-label="Hashtags"><?= htmlspecialchars((string) ($h['hashtags'] ?? '')) ?></td>
                                    <td data-label="Date"><?= htmlspecialchars((string) ($h['created_at'] ?? '')) ?></td>
                                    <td data-label="Visible"><?= $visible ? 'Yes' : 'Hidden' ?></td>
                                    <td data-label="Actions">
                                        <form method="post" class="admin-inline-form" style="display:inline;">
                                            <input type="hidden" name="toggle_visible_id" value="<?= (int) $h['id'] ?>">
                                            <button type="submit" class="btn-small"><?= $visible ? 'Hide' : 'Show' ?></button>
                                        </form>
                                        <a href="<?= BASE ?>/admin/wedding-highlights?delete=<?= (int) $h['id'] ?>" class="btn-small danger" onclick="return confirm('Delete this highlight permanently?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
<?php admin_lte_layout_end(); ?>
