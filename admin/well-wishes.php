<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-lte-layout.php';

$pdo = getDb();
$message = '';
$messageType = '';

if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM well_wishes WHERE id = ?")->execute([$id]);
    header('Location: ' . BASE . '/admin/well-wishes?deleted=1');
    exit;
}

if (isset($_GET['deleted'])) {
    $message = 'Well wish removed.';
    $messageType = 'success';
}

$wishes = $pdo->query("SELECT * FROM well_wishes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$allowed_msg_tags = '<p><br><b><strong><i><em><u>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Well wishes — Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<?php admin_lte_layout_begin('Well wishes', 'well-wishes'); ?>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <div class="admin-card">
            <h2>Messages left for the couple</h2>
            <?php if (empty($wishes)): ?>
                <p>No well wishes yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wishes as $w): ?>
                                <?php
                                $wishTs = strtotime($w['created_at'] ?? '');
                                $wishDate = $wishTs ? date('M j, Y g:i a', $wishTs) : '';
                                ?>
                                <tr>
                                    <td data-label="Name"><?= htmlspecialchars($w['author_name']) ?></td>
                                    <td data-label="Date"><?= htmlspecialchars($wishDate) ?></td>
                                    <td class="admin-wish-cell" data-label="Message">
                                        <div class="admin-wish-preview"><?= strip_tags($w['message'] ?? '', $allowed_msg_tags) ?></div>
                                    </td>
                                    <td data-label="Actions">
                                        <a href="<?= BASE ?>/admin/well-wishes?delete=<?= (int) $w['id'] ?>" class="btn-small danger" onclick="return confirm('Remove this well wish?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
<?php admin_lte_layout_end(); ?>
