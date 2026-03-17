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
    <h1 class="hero-badge">Omasyl 2026</h1>
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

<section class="invite-section">
    <p class="invite-text">We would love to have you celebrate this special day with us.</p>
</section>

<section class="story-section" id="our-story">
    <h2 class="section-title">Our Story</h2>
    <div class="story-content">
        <p>Our journey together began with a simple hello and grew into a love we never knew we were waiting for. Through every season, we've chosen each other—and we can't wait to say "I do" in front of the people who matter most.</p>
        <p>Thank you for being part of our story. We look forward to seeing you in our big day</p>
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
    <div class="detail-card">
        <h3>Reception</h3>
        <p>Waterfalls Event Center, Billings Way, Ikeja, Lagos State</p>
        <p class="time">2:00 PM</p>
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

<section class="photo-section">
    <?php if (file_exists($photo3)): ?>
        <div class="img-frame img-frame-full">
            <img src="<?= BASE ?>/<?= htmlspecialchars($photo3) ?>" alt="Omasyl">
        </div>
    <?php endif; ?>
</section>

<section class="rsvp-cta-section">
    <h2 class="section-title">Why you need to RSVP</h2>
    <p>So we can reserve your seat and make sure everything is perfect for you. Please register below or reach out to us.</p>
    <a href="<?= BASE ?>/register" class="btn">Click here to RSVP</a>
    <p class="rsvp-phones">Or call <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_EHI) ?>">Ehi <?= preg_replace('/\s+/', ' ', RSVP_PHONE_EHI) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_ONYINYE) ?>">Onyinye <?= preg_replace('/\s+/', ' ', RSVP_PHONE_ONYINYE) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_BECKY) ?>">Becky <?= preg_replace('/\s+/', ' ', RSVP_PHONE_BECKY) ?></a> or <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_PRECIOUS) ?>">Precious <?= preg_replace('/\s+/', ' ', RSVP_PHONE_PRECIOUS) ?></a></p>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
