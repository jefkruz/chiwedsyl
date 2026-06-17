<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/guest-access-card.php';
require_once __DIR__ . '/includes/guest-access-card-image.php';
require_once __DIR__ . '/includes/guest-whatsapp-invite.php';

$current_page = 'guest-pass';

$code = trim((string) ($_GET['code'] ?? ''));
$pdo = getDb();
$guest = guest_fetch_confirmed_by_pass_code($pdo, $code);

if ($guest === null) {
    http_response_code(404);
    $page_title = 'Pass not found — ' . SITE_NAME;
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="form-page">
        <h1>Guest pass unavailable</h1>
        <p>This pass link is invalid or your registration has not been confirmed yet.</p>
        <p><a href="<?= BASE ?>/register" class="btn">Go to RSVP</a></p>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (isset($_GET['download']) && (string) $_GET['download'] === 'png') {
    if (!guest_access_card_send_png_download($guest)) {
        http_response_code(500);
        echo 'Could not generate pass image.';
    }
    exit;
}

$page_title = 'Your access pass — ' . SITE_NAME;
include __DIR__ . '/includes/header.php';
?>
<section class="register-access-card-wrap">
    <h1>Your access pass</h1>
    <p class="register-access-card-lead">Save this pass to your phone and present it at the venue. Do not share it with anyone else.</p>
    <?= render_guest_access_card($guest, BASE) ?>
    <div class="register-access-card-actions">
        <a class="btn-submit" style="display:inline-block;width:auto;padding:0.75rem 1.5rem;text-decoration:none;" href="<?= htmlspecialchars(guest_pass_png_download_url($guest), ENT_QUOTES, 'UTF-8') ?>">Download pass (PNG)</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
