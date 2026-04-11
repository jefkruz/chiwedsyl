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
$bannerImage = 'assets/images/banner.png';
$bannerFullPath = __DIR__ . '/' . str_replace('\\', '/', $bannerImage);
$bannerUrl = is_file($bannerFullPath) ? BASE . '/' . $bannerImage : '';
$heroImage = 'assets/images/DSC02343.jpg';
$heroImagePath = __DIR__ . '/' . str_replace('\\', '/', $heroImage);
$photo2 = 'assets/images/DSC02354.jpg';
$photo3 = 'assets/images/DSC02162.jpg';

$pdo = getDb();
$homeGifts = $pdo->query("SELECT * FROM gift_items ORDER BY sort_order, id LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$homeGallery = $pdo->query("SELECT * FROM gallery_images ORDER BY sort_order, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="hero hero-banner">
    <?php if ($bannerUrl !== ''): ?>
        <div class="hero-banner-media" aria-hidden="true">
            <img class="hero-banner-img" src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" decoding="async" fetchpriority="high">
        </div>
    <?php endif; ?>
    <div class="hero-banner-overlay" aria-hidden="true"></div>
    <div class="hero-banner-inner">
        <h1 class="hero-banner-title">Welcome to Chioma and Sylvanus wedding website…</h1>
        <p class="hero-banner-tagline">Love found us and forever started 😍❤️</p>
        <p class="hero-banner-hashtags">
            <span class="hero-banner-hash">#RhapsodyOfEndlesslove</span>
            <span class="hero-banner-hash">#OmaSyl2026</span>
        </p>
    </div>
</section>

<section class="hero-framed-section" aria-label="Featured photo">
    <?php if (is_file($heroImagePath)): ?>
        <div class="img-frame img-frame-hero">
            <img src="<?= BASE ?>/<?= htmlspecialchars($heroImage) ?>" alt="Chioma and Sylvanus — OmaSyl 2026" class="hero-image">
        </div>
    <?php else: ?>
        <div class="img-frame img-frame-hero hero-image hero-image-placeholder">
            <span class="hero-framed-placeholder" style="color: var(--cream);">OmaSyl 2026</span>
        </div>
    <?php endif; ?>
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
        <h3 class="story-subtitle">Groom</h3>
        <p>I met her at the University of Lagos, where we were both pursuing our Masters degrees (same course). I was drawn to her striking confidence and ambition. She was always pushing to be the best version of herself. Her hard work and dedication inspired me, and I found myself wanting to be around her more and more.</p>
        <p>As we spent more time together, I discovered that her ambition was just one aspect of her remarkable personality; she is kind, compassionate, and genuinely cared about the people around her. Her faith was an integral part of who she was, and it shone through in everything she did. I was impressed by her humility and willingness to learn, despite her many accomplishments.</p>
        <p>We bonded over our shared values and interests, sometimes studying together, discussing our dreams, and supporting each other. What struck me most was her authenticity, she was unapologetically herself, without pretenses or airs. I admired her strength and resilience, especially in the face of challenges. She had a quiet confidence that drew me in, and I knew I wanted to spend more time getting to know her.</p>
        <p>As our friendship blossomed into something more, I was amazed by her generosity and love. She had a way of making me feel seen and valued, and I knew I had found someone special. Her family welcomed me with open arms, and I felt like I had found a second family.</p>
        <p>Looking back, I realize that our university days were just the beginning of our journey together. Our shared experiences and values have only grown stronger with time. I'm grateful for the day I met her and for the love we've built together. She's the missing piece that makes me whole. With great excitement, I'm prayerfully looking forward to a bright future with her.</p>
        <h3 class="story-subtitle">Bride</h3>
        <p>I met the groom at the University of Lagos; we were both studying the same course. What attracted me to him was his intelligence, confidence, and leadership traits. He was always sharing his opinion in class, always ready to teach, or give a helping hand where necessary.</p>
        <p>One striking thing that caught my attention was when he would go out of his way to drop me very close to where I would board a vehicle going home—and he did it almost every time. He is a very good listener, very slow to anger, very thoughtful of others, and always very, very kind.</p>
        <p>His show of love, affection, and wise counsel made me feel safe, and I was convinced I had found the one 🥰. He has a mind of his own, and he is not pressured by circumstances. His family welcomed me with sooooo much love, and that crowned it all for me.</p>
        <p>I am excited to start this new phase with him, and I pray that every one of our desires for this union will come to pass by the power of the Holy Spirit in the name of our Lord Jesus Christ. Amen! 🥰</p>
        <p class="story-together">Our journey together began with a simple hello and grew into a love we never knew we were waiting for. Through every season, we've chosen each other—and we can't wait to say "I do" in front of the people who matter most.</p>
        <p>Thank you for being part of our story. We look forward to seeing you in our big day</p>
        <p>We would love to read your well wishes</p>
    </div>
    <p class="story-cta">
        <a href="<?= BASE ?>/well-wishes" class="btn">Leave a well wish</a>
    </p>
</section>

<section class="details-section" id="details">
    <!-- <h2 class="section-title">Wedding </h2> -->
    <div class="detail-card">
        <h3>Wedding Ceremony</h3>
        <p>Christ Embassy Church, Excel Centre, 8 Billings Way, Ikeja, Lagos State</p>
        <p class="time">10:00 AM</p>
        <p><a href="https://www.google.com/maps/place/Excel+Events+(Planning+and+Decoration),+8A+Billings+Way,+Oregun,+Ikeja+100212,+Lagos" target="_blank" rel="noopener noreferrer" class="btn">Get directions</a></p>
    </div>
</section>

<section class="photo-section">
    <?php if (file_exists($photo2)): ?>
        <div class="img-frame img-frame-full">
            <img src="<?= BASE ?>/<?= htmlspecialchars($photo2) ?>" alt="OmaSyl">
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
                <?php if (is_file(__DIR__ . '/' . ltrim(str_replace('\\', '/', $photo3), '/'))): ?>
                    <article class="home-gallery-slide">
                        <img src="<?= BASE ?>/<?= htmlspecialchars($photo3) ?>" alt="OmaSyl">
                    </article>
                <?php endif; ?>
                <?php foreach ($homeGallery as $img): ?>
                    <?php
                    $gpath = $img['image_path'] ?? '';
                    $gfull = $gpath !== '' ? __DIR__ . '/' . ltrim(str_replace('\\', '/', $gpath), '/') : '';
                    ?>
                    <?php if ($gpath !== '' && is_file($gfull)): ?>
                        <article class="home-gallery-slide">
                            <img src="<?= BASE ?>/<?= htmlspecialchars($gpath) ?>" alt="<?= htmlspecialchars($img['caption'] ?? 'Gallery image') ?>">
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="button" class="carousel-btn next" data-target="home-gallery-carousel" aria-label="Next gallery images">›</button>
    </div>
    <p class="home-carousel-link">
        <a href="<?= BASE ?>/gallery" class="btn">View more</a>
    </p>
</section>

<section class="rsvp-cta-section">
    <h2 class="section-title">Why you need to RSVP</h2>
    <p>So we can reserve your seat and make sure everything is perfect for you. Please register below or reach out to us.</p>
    <a href="<?= BASE ?>/register" class="btn">Click here to RSVP</a>
    <p class="rsvp-phones">Or call <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_EHI) ?>">Ehi <?= preg_replace('/\s+/', ' ', RSVP_PHONE_EHI) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_ONYINYE) ?>">Onyinye <?= preg_replace('/\s+/', ' ', RSVP_PHONE_ONYINYE) ?></a>, <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_BECKY) ?>">Becky <?= preg_replace('/\s+/', ' ', RSVP_PHONE_BECKY) ?></a> or <a href="tel:<?= preg_replace('/\s+/', '', RSVP_PHONE_PRECIOUS) ?>">Precious <?= preg_replace('/\s+/', ' ', RSVP_PHONE_PRECIOUS) ?></a></p>
</section>

<?php include __DIR__ . '/includes/gift-modal.php'; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
