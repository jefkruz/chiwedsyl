<?php
declare(strict_types=1);

/**
 * Shared AdminLTE wrapper for all admin pages.
 *
 * Usage:
 *  - In the page <head>, include the AdminLTE + Bootstrap CSS links.
 *  - In the page <body>, after opening <body>, call:
 *      require_once __DIR__ . '/../includes/admin-lte-layout.php';
 *      admin_lte_layout_begin($pageHeaderTitle, $activeSectionKey);
 *    then output the page content.
 *  - Before closing <body>, call:
 *      admin_lte_layout_end();
 */

function admin_lte_layout_begin(string $pageHeaderTitle, string $activeSection): void {
    $activeSection = strtolower(trim($activeSection));
    ?>
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
                        <?php
                        $navItems = [
                            'dashboard' => ['href' => BASE . '/admin/dashboard', 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
                            'guests' => ['href' => BASE . '/admin/guests', 'icon' => 'fas fa-user-friends', 'label' => 'Guests'],
                            'scan' => ['href' => BASE . '/admin/scan', 'icon' => 'fas fa-qrcode', 'label' => 'Scan check-in'],
                            'gifts' => ['href' => BASE . '/admin/gifts', 'icon' => 'fas fa-gift', 'label' => 'Gifts'],
                            'well-wishes' => ['href' => BASE . '/admin/well-wishes', 'icon' => 'fas fa-heart', 'label' => 'Well wishes'],
                            'gallery' => ['href' => BASE . '/admin/gallery', 'icon' => 'fas fa-images', 'label' => 'Gallery'],
                        ];

                        foreach ($navItems as $key => $item):
                            $isActive = $activeSection === $key ? 'active' : '';
                            ?>
                            <li class="nav-item">
                                <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-link <?= $isActive ?>">
                                    <i class="nav-icon <?= htmlspecialchars($item['icon']) ?>"></i>
                                    <p><?= htmlspecialchars($item['label']) ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>

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
                            <h1 class="m-0 text-dark"><?= htmlspecialchars($pageHeaderTitle) ?></h1>
                        </div>
                        <div class="col-sm-6 text-right">
                            <p class="text-muted mb-0">OmaSyl administration</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
    <?php
}

function admin_lte_layout_end(?string $extraFooterScripts = null): void {
    ?>
                </div>
            </section>
        </div>
    </div>

    <!-- AdminLTE requires jQuery/Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <!-- DataTables (responsive) -->
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs4@2.5.0/js/responsive.bootstrap4.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery || !jQuery.fn.DataTable) return;

            jQuery('table.js-datatable').each(function () {
                const $table = jQuery(this);

                // Avoid double-init if the page is re-rendered.
                if ($.fn.dataTable.isDataTable($table)) return;

                const noSortFirst = $table.hasClass('js-datatable-no-sort-first');
                const nonSortTargets = noSortFirst ? [0, -1] : [-1];

                $table.DataTable({
                    responsive: true,
                    autoWidth: false,
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100],
                    order: [],
                    columnDefs: [{ orderable: false, targets: nonSortTargets }]
                });
            });
        });
    </script>
    <?php if ($extraFooterScripts !== null && $extraFooterScripts !== ''): ?>
    <script><?= $extraFooterScripts ?></script>
    <?php endif; ?>
    </body>
    </html>
    <?php
}

