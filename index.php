<?php
require_once __DIR__ . '/config.php';
$current_page = 'home';
$page_title = SITE_NAME . ' — We\'re Getting Married';

// Wedding date for calendar
$weddingTs = strtotime(WEDDING_DATE);
$weddingDay = (int) date('j', $weddingTs);
$weddingMonth = date('F', $weddingTs);
$weddingYear = date('Y', $weddingTs);
$firstDay = date('w', strtotime($weddingYear . '-' . date('n', $weddingTs) . '-01'));
$daysInMonth = date('t', $weddingTs);

// Image paths (place your photos in assets/images/)
$heroImage = 'assets/images/DSC02343.jpg';
$photo2 = 'assets/images/DSC02354.jpg';
$photo3 = 'assets/images/DSC02162.jpg';

$pdo = getDb();
$homeGifts = $pdo->query("SELECT * FROM gift_items ORDER BY sort_order, id LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$homeGallery = $pdo->query("SELECT * FROM gallery_images ORDER BY created_at DESC LIMIT 16")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <?php if (file_exists($heroImage)): ?>
        <div class="img-frame img-frame-hero">
            <img src="<?= BASE ?>/<?= htmlspecialchars($heroImage) ?>" alt="Omasyl 2026" class="hero-image">
        </div>
    <?php else: ?>
        <div class="img-frame img-frame-hero hero-image hero-image-placeholder">
            <span class="hero-badge" style="color: var(--cream);">Omasyl 2026</span>
        </div>
    <?php endif; ?>
    <!-- <h1 class="hero-badge">Omasyl 2026</h1> -->
    <p class="hero-sub">We're Getting Married</p>
</section>

<section class="countdown-section" id="countdown-section">
    <h2>Counting the days</h2>
    <div class="countdown-runner" id="countdown" data-date="<?= htmlspecialchars(WEDDING_DATE) ?>">
        <div class="countdown-box"><span class="countdown-num">—</span><span class="countdown-label">Days</span></div>
        <span class="countdown-sep" aria-hidden="true"></span>
        <div class="countdown-box"><span class="countdown-num">—</span><span class="countdown-label">Hours</span></div>
        <span class="countdown-sep" aria-hidden="true"></span>
        <div class="countdown-box"><span class="countdown-num">—</span><span class="countdown-label">Mins</span></div>
        <span class="countdown-sep" aria-hidden="true"></span>
        <div class="countdown-box"><span class="countdown-num">—</span><span class="countdown-label">Secs</span></div>
    </div>
</section>

<section class="calendar-section">
    <h2 class="section-title">Save the date</h2>
    <div class="calendar-wrap">
        <div class="calendar-month"><?= htmlspecialchars($weddingMonth . ' ' . $weddingYear) ?></div>
        <div class="calendar-grid">
            <span class="weekday">Sun</span><span class="weekday">Mon</span><span class="weekday">Tue</span><span class="weekday">Wed</span><span class="weekday">Thu</span><span class="weekday">Fri</span><span class="weekday">Sat</span>
            <?php
            $blank = $firstDay;
            for ($i = 0; $i < $blank; $i++) echo '<span></span>';
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $cls = $d === $weddingDay ? 'wedding-day' : '';
                echo '<span class="' . $cls . '">' . $d . '</span>';
            }
            ?>
        </div>
    </div>
</section>



<section class="story-section" id="our-story">
    <h2 class="section-title">Our Story</h2>
    <div class="story-content">
        <p>Our journey together began with a simple hello and grew into a love we never knew we were waiting for. Through every season, we've chosen each other—and we can't wait to say "I do" in front of the people who matter most.</p>
        <p>Thank you for being part of our story. We look forward to seeing you in our big day</p>
        <p>We would love to read your well wishes</p>
    </div>
    <p class="story-cta">
        <a href="<?= BASE ?>/well-wishes" class="btn">Leave a well wish</a>
    </p>
</section>

<section class="details-section" id="details">
    <h2 class="section-title">Wedding & Reception</h2>
    <div class="detail-card">
        <h3>Ceremony</h3>
        <p>Excel Centre, Billings Way, Ikeja, Lagos State</p>
        <p class="time">10:00 AM</p>
    </div>
</section>

<section class="photo-section">
    <?php if (file_exists($photo2)): ?>
        <div class="img-frame img-frame-full">
            <img src="<?= BASE ?>/<?= htmlspecialchars($photo2) ?>" alt="Omasyl">
        </div>
    <?php endif; ?>
</section>

<section class="colors-section">
    <h2 class="section-title">Colour of the day</h2>
    <p class="color-tagline">Chocolate brown, gold, cream & navy blue</p>
    <div class="color-row">
        <div class="color-item">
            <div class="color-swatch chocolate" title="Chocolate brown"></div>
            <span>Chocolate brown</span>
        </div>
        <div class="color-item">
            <div class="color-swatch gold" title="Gold"></div>
            <span>Gold</span>
        </div>
        <div class="color-item">
            <div class="color-swatch cream" title="Cream"></div>
            <span>Cream</span>
        </div>
        <div class="color-item">
            <div class="color-swatch navy" title="Navy blue"></div>
            <span>Navy blue</span>
        </div>
    </div>
</section>

<section class="home-gifts-section">
    <h2 class="section-title">Gift Ideas</h2>
    <div class="home-carousel-wrap">
        <button type="button" class="carousel-btn prev" data-target="home-gifts-carousel" aria-label="Previous gifts">‹</button>
        <div class="home-carousel" id="home-gifts-carousel">
            <div class="home-carousel-track">
                <?php foreach ($homeGifts as $g): ?>
                    <article class="home-product-card">
                        <div class="home-product-image">
                            <?php if (!empty($g['image_path']) && file_exists($g['image_path'])): ?>
                                <button type="button" class="home-product-image-link gift-modal-trigger" data-gift-id="<?= (int) $g['id'] ?>" data-gift-name="<?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Get <?= htmlspecialchars($g['title']) ?>">
                                    <img src="<?= BASE ?>/<?= htmlspecialchars($g['image_path']) ?>" alt="<?= htmlspecialchars($g['title']) ?>">
                                </button>
                            <?php else: ?>
                                <div class="home-product-no-image">No image</div>
                            <?php endif; ?>
                        </div>
                        <div class="home-product-body">
                            <h3><?= htmlspecialchars($g['title']) ?></h3>
                            <?php if (!empty($g['price'])): ?>
                                <p class="home-product-price"><?= htmlspecialchars(format_gift_price($g['price'])) ?></p>
                            <?php endif; ?>
                            <button type="button" class="home-product-get-btn gift-modal-trigger" data-gift-id="<?= (int) $g['id'] ?>" data-gift-name="<?= htmlspecialchars($g['title'], ENT_QUOTES, 'UTF-8') ?>">Get</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="carousel-btn next" data-target="home-gifts-carousel" aria-label="Next gifts">›</button>
    </div>
    <p class="home-carousel-link">
        <a href="<?= BASE ?>/gifts" class="btn">View more gifts</a>
    </p>
</section>

<section class="home-gallery-section">
    <h2 class="section-title">Gallery Highlights</h2>
    <div class="home-carousel-wrap">
        <button type="button" class="carousel-btn prev" data-target="home-gallery-carousel" aria-label="Previous gallery images">‹</button>
        <div class="home-carousel" id="home-gallery-carousel">
            <div class="home-carousel-track">
                <?php if (file_exists($photo3)): ?>
                    <article class="home-gallery-slide">
                        <img src="<?= BASE ?>/<?= htmlspecialchars($photo3) ?>" alt="Omasyl">
                    </article>
                <?php endif; ?>
                <?php foreach ($homeGallery as $img): ?>
                    <?php if (!empty($img['image_path']) && file_exists($img['image_path'])): ?>
                        <article class="home-gallery-slide">
                            <img src="<?= BASE ?>/<?= htmlspecialchars($img['image_path']) ?>" alt="<?= htmlspecialchars($img['caption'] ?? 'Gallery image') ?>">
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="carousel-btn next" data-target="home-gallery-carousel" aria-label="Next gallery images">›</button>
    </div>
</section>

<section class="rsvp-cta-section">
    <h2 class="section-title">Why you need to RSVP</h2>
    <p>So we can reserve your seat and make sure everything is perfect for you. Please register below or reach out to us.</p>
    <a href="<?= BASE ?>/register" class="btn">Click here to RSVP</a>
    <p class="rsvp-phones">Or call <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_EHI) ?>">Ehi <?= preg_replace('/\s+/', ' ', RSVP_PHONE_EHI) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_ONYINYE) ?>">Onyinye <?= preg_replace('/\s+/', ' ', RSVP_PHONE_ONYINYE) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_BECKY) ?>">Becky <?= preg_replace('/\s+/', ' ', RSVP_PHONE_BECKY) ?></a> or <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_PRECIOUS) ?>">Precious <?= preg_replace('/\s+/', ' ', RSVP_PHONE_PRECIOUS) ?></a></p>
</section>

<?php include __DIR__ . '/includes/gift-modal.php'; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
