<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/admin-auth.php';

$pdo = getDb();
$pendingGuestCount = (int) $pdo->query('SELECT COUNT(*) FROM guests WHERE COALESCE(registration_confirmed, 0) = 0')->fetchColumn();
$confirmedGuestCount = (int) $pdo->query('SELECT COUNT(*) FROM guests WHERE registration_confirmed = 1')->fetchColumn();
$giftCount = (int) $pdo->query("SELECT COUNT(*) FROM gift_items")->fetchColumn();
$wishCount = (int) $pdo->query("SELECT COUNT(*) FROM well_wishes")->fetchColumn();
$galleryCount = (int) $pdo->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn();

$sections = [
    [
        'href' => BASE . '/admin/guests?status=pending',
        'title' => 'Pending guests',
        'count' => $pendingGuestCount,
        'count_label' => 'awaiting confirmation',
        'hint' => 'Review new RSVPs and confirm registrations.',
        'icon' => 'fas fa-user-clock',
        'color' => 'bg-warning',
    ],
    [
        'href' => BASE . '/admin/guests?status=confirmed',
        'title' => 'Confirmed guests',
        'count' => $confirmedGuestCount,
        'count_label' => 'confirmed registrations',
        'hint' => 'Send passes, WhatsApp invites, and check-in.',
        'icon' => 'fas fa-user-check',
        'color' => 'bg-success',
    ],
    [
        'href' => BASE . '/admin/gifts',
        'title' => 'Gifts',
        'count' => $giftCount,
        'count_label' => 'gift items',
        'hint' => 'Edit catalogue and prices for the public shop.',
        'icon' => 'fas fa-gift',
        'color' => 'bg-warning',
    ],
    [
        'href' => BASE . '/admin/well-wishes',
        'title' => 'Well wishes',
        'count' => $wishCount,
        'count_label' => 'messages posted',
        'hint' => 'Read and remove messages from the public well wishes page.',
        'icon' => 'fas fa-heart',
        'color' => 'bg-danger',
    ],
    [
        'href' => BASE . '/admin/gallery',
        'title' => 'Gallery',
        'count' => $galleryCount,
        'count_label' => 'images live',
        'hint' => 'Upload, caption, and remove homepage gallery photos.',
        'icon' => 'fas fa-images',
        'color' => 'bg-success',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars(SITE_NAME) ?></title>
    <!-- AdminLTE (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle menu">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE ?>/admin/logout" role="button" aria-label="Log out">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <a href="<?= BASE ?>/admin/dashboard" class="brand-link">
                <span class="brand-text font-weight-light">Admin</span>
            </a>

            <div class="sidebar">
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                        <li class="nav-item">
                            <a href="<?= BASE ?>/admin/guests" class="nav-link">
                                <i class="nav-icon fas fa-user-friends"></i>
                                <p>Guests</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE ?>/admin/scan" class="nav-link">
                                <i class="nav-icon fas fa-qrcode"></i>
                                <p>Scan check-in</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE ?>/admin/gifts" class="nav-link">
                                <i class="nav-icon fas fa-gift"></i>
                                <p>Gifts</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE ?>/admin/well-wishes" class="nav-link">
                                <i class="nav-icon fas fa-heart"></i>
                                <p>Well wishes</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE ?>/admin/gallery" class="nav-link">
                                <i class="nav-icon fas fa-images"></i>
                                <p>Gallery</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= BASE ?>/" class="nav-link">
                                <i class="nav-icon fas fa-home"></i>
                                <p>View site</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0 text-dark">Dashboard</h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            <p class="text-muted mb-0">Pick a section to manage. Counts update live.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <?php foreach ($sections as $s): ?>
                            <div class="col-12 col-sm-6 col-lg-3">
                                <a href="<?= htmlspecialchars($s['href']) ?>" class="info-box text-reset" style="text-decoration: none;">
                                    <span class="info-box-icon <?= htmlspecialchars($s['color']) ?> elevation-1">
                                        <i class="<?= htmlspecialchars($s['icon']) ?>"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text"><?= htmlspecialchars($s['title']) ?></span>
                                        <span class="info-box-number"><?= (int) $s['count'] ?></span>
                                        <span class="info-box-text" style="font-size: 0.85rem; opacity: 0.85;"><?= htmlspecialchars($s['count_label']) ?></span>
                                    </div>
                                </a>
                                <div class="px-2 pb-3" style="font-size: 0.9rem;">
                                    <div class="text-muted"><?= htmlspecialchars($s['hint']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- AdminLTE requires jQuery/Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
