<?php
declare(strict_types=1);

/**
 * Titles shown on RSVP and admin guest forms (value => label).
 *
 * @return array<string, string>
 */
function guest_valid_titles(): array {
    return [
        'Mr'    => 'Mr',
        'Mrs'   => 'Mrs',
        'Miss'  => 'Miss',
        'Ms'    => 'Ms',
        'Dr'    => 'Dr',
        'Prof'  => 'Prof',
        'Eng'   => 'Eng',
        'Rev'   => 'Rev',
        'Chief' => 'Chief',
    ];
}

/**
 * Project root (parent of /includes).
 */
function guest_access_card_project_root(): string {
    return dirname(__DIR__);
}

/**
 * Human-readable wedding date for the pass, or empty.
 */
function guest_access_card_event_date_label(): string {
    if (!defined('WEDDING_DATE') || WEDDING_DATE === '') {
        return '';
    }
    $ts = strtotime((string) WEDDING_DATE);
    if ($ts === false) {
        return '';
    }
    return date('l, F j, Y', $ts);
}

/**
 * Display name for card: Title + First + Last, or legacy `name`.
 */
function guest_display_name(array $guest): string {
    $t = trim((string) ($guest['title'] ?? ''));
    $fn = trim((string) ($guest['first_name'] ?? ''));
    $ln = trim((string) ($guest['last_name'] ?? ''));
    if ($fn !== '' || $ln !== '') {
        $core = trim($fn . ' ' . $ln);
        return trim(($t !== '' ? $t . ' ' : '') . $core);
    }
    return trim((string) ($guest['name'] ?? ''));
}

/**
 * Compose stored `name` from title + first + last.
 */
function guest_composed_full_name(string $title, string $first, string $last): string {
    $t = trim($title);
    $fn = trim($first);
    $ln = trim($last);
    $core = trim($fn . ' ' . $ln);
    return trim(($t !== '' ? $t . ' ' : '') . $core);
}

/**
 * Guest pass image exists on disk (path set and file readable).
 */
function guest_has_valid_pass_photo_on_disk(array $guest): bool {
    $photoPath = trim((string) ($guest['guest_photo_path'] ?? ''));
    if ($photoPath === '') {
        return false;
    }
    return is_file(guest_access_card_project_root() . '/' . $photoPath);
}

/**
 * True when the guest should complete missing RSVP fields (photo and/or gender, etc.).
 */
function guest_profile_needs_completion(array $guest): bool {
    if (!guest_has_valid_pass_photo_on_disk($guest)) {
        return true;
    }
    $g = trim((string) ($guest['gender'] ?? ''));
    if (!in_array($g, ['male', 'female'], true)) {
        return true;
    }
    $title = trim((string) ($guest['title'] ?? ''));
    if ($title === '' || !isset(guest_valid_titles()[$title])) {
        return true;
    }
    $fn = trim((string) ($guest['first_name'] ?? ''));
    $ln = trim((string) ($guest['last_name'] ?? ''));
    if ($fn === '' || $ln === '') {
        return true;
    }
    return false;
}

/**
 * Human-readable list of missing profile items (for messaging).
 *
 * @return list<string>
 */
function guest_profile_missing_labels(array $guest): array {
    $missing = [];
    if (!guest_has_valid_pass_photo_on_disk($guest)) {
        $missing[] = 'pass photo';
    }
    $g = trim((string) ($guest['gender'] ?? ''));
    if (!in_array($g, ['male', 'female'], true)) {
        $missing[] = 'gender';
    }
    $title = trim((string) ($guest['title'] ?? ''));
    if ($title === '' || !isset(guest_valid_titles()[$title])) {
        $missing[] = 'title';
    }
    $fn = trim((string) ($guest['first_name'] ?? ''));
    $ln = trim((string) ($guest['last_name'] ?? ''));
    if ($fn === '' || $ln === '') {
        $missing[] = 'first and last name';
    }
    return $missing;
}

/**
 * Suggest first/last name fields when only legacy `name` is set.
 *
 * @return array{0: string, 1: string}
 */
function guest_default_first_last_for_form(array $guest): array {
    $fn = trim((string) ($guest['first_name'] ?? ''));
    $ln = trim((string) ($guest['last_name'] ?? ''));
    if ($fn === '' && $ln === '' && trim((string) ($guest['name'] ?? '')) !== '') {
        $parts = preg_split('/\s+/', trim((string) $guest['name']), 2);
        $fn = $parts[0] ?? '';
        $ln = $parts[1] ?? '';
    }
    return [$fn, $ln];
}

function guest_access_card_photo_url(array $guest, string $base): ?string {
    $photoPath = trim((string) ($guest['guest_photo_path'] ?? ''));
    if ($photoPath === '') {
        return null;
    }
    $full = guest_access_card_project_root() . '/' . $photoPath;
    if (!is_file($full)) {
        return null;
    }
    $prefix = $base === '' ? '' : rtrim($base, '/');
    return $prefix . '/' . ltrim($photoPath, '/');
}

function guest_access_card_file_data_uri(string $absolutePath): ?string {
    if (!is_file($absolutePath)) {
        return null;
    }
    $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mime = $mimeMap[$ext] ?? null;
    if ($mime === null) {
        return null;
    }
    $raw = @file_get_contents($absolutePath);
    if ($raw === false || $raw === '') {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function guest_access_card_logo_data_uri(): ?string {
    $path = guest_access_card_project_root() . '/assets/images/logo.png';
    return guest_access_card_file_data_uri($path);
}

/**
 * HTML fragment for the digital guest access card.
 *
 * @param array  $guest                   Guests table row
 * @param string $base                    Site base (e.g. '') for asset URLs
 * @param bool   $embed_for_offline_file  If true, logo and guest photo are inlined (saved HTML opens correctly offline)
 */
function render_guest_access_card(array $guest, string $base = '', bool $embed_for_offline_file = false): string {
    $displayName = htmlspecialchars(guest_display_name($guest), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string) ($guest['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $qrData = (string) ($guest['qr_code'] ?? '');
    $num = max(1, (int) ($guest['num_guests'] ?? 1));
    $extras = max(0, $num - 1);
    $site = defined('SITE_NAME') ? htmlspecialchars((string) SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Wedding';
    $dateLine = guest_access_card_event_date_label();
    $dateHtml = $dateLine !== '' ? htmlspecialchars($dateLine, ENT_QUOTES, 'UTF-8') : '';

    if ($embed_for_offline_file) {
        $logoSrcAttr = guest_access_card_logo_data_uri();
    } else {
        $logoSrcAttr = null;
    }
    if ($logoSrcAttr === null || $logoSrcAttr === '') {
        $prefix = $base === '' ? '' : rtrim($base, '/');
        $logoSrcAttr = htmlspecialchars($prefix . '/assets/images/logo.png', ENT_QUOTES, 'UTF-8');
    }

    $photoSrcAttr = null;
    if ($embed_for_offline_file) {
        $pp = trim((string) ($guest['guest_photo_path'] ?? ''));
        if ($pp !== '') {
            $full = guest_access_card_project_root() . '/' . $pp;
            $photoSrcAttr = guest_access_card_file_data_uri($full);
        }
    }
    $photoUrlWeb = guest_access_card_photo_url($guest, $base);

    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($qrData);

    $partyLine = $num === 1
        ? 'This pass admits <strong>1</strong> person at the gate.'
        : 'This pass admits <strong>' . $num . '</strong> people in total — you plus <strong>' . $extras . '</strong> guest' . ($extras === 1 ? '' : 's') . ' arriving with you.';

    $html = '<article class="guest-access-card" aria-label="Wedding access pass">';
    $html .= '<div class="guest-access-card-frame">';
    $html .= '<header class="guest-access-card-header">';
    $html .= '<div class="guest-access-card-header-inner">';
    $html .= '<img class="guest-access-card-logo" src="' . $logoSrcAttr . '" width="120" height="120" alt="">';
    $html .= '<div class="guest-access-card-headlines">';
    $html .= '<p class="guest-access-card-brand">' . $site . '</p>';
    $html .= '<p class="guest-access-card-tagline">Celebration access pass</p>';
    if ($dateHtml !== '') {
        $html .= '<p class="guest-access-card-date">' . $dateHtml . '</p>';
    }
    $html .= '</div></div></header>';

    $html .= '<div class="guest-access-card-main">';
    if ($photoSrcAttr !== null && $photoSrcAttr !== '') {
        $html .= '<div class="guest-access-card-photo"><img src="' . $photoSrcAttr . '" alt=""></div>';
    } elseif ($photoUrlWeb !== null) {
        $html .= '<div class="guest-access-card-photo"><img src="' . htmlspecialchars($photoUrlWeb, ENT_QUOTES, 'UTF-8') . '" alt=""></div>';
    } else {
        $html .= '<div class="guest-access-card-photo guest-access-card-photo-placeholder" aria-hidden="true"></div>';
    }

    $html .= '<div class="guest-access-card-body">';
    $html .= '<h2 class="guest-access-card-name">' . $displayName . '</h2>';
    $html .= '<p class="guest-access-card-email">' . $email . '</p>';
    $html .= '<div class="guest-access-card-admit">' . $partyLine . '</div>';
    $html .= '<div class="guest-access-card-qr"><img src="' . htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') . '" alt="Check-in QR code" width="200" height="200" loading="lazy"></div>';
    $html .= '<p class="guest-access-card-hint">Present this pass at entry. You may print or save this page.</p>';
    $html .= '</div></div>';
    $html .= '<footer class="guest-access-card-footer">Official guest pass · keep private</footer>';
    $html .= '</div></article>';
    return $html;
}

function guest_access_card_inline_styles(): string {
    return <<<'CSS'
*{box-sizing:border-box;}
body{margin:0;padding:1.75rem 1rem 2rem;font-family:Georgia,"Times New Roman",serif;background:linear-gradient(165deg,#2a1810 0%,#4a3224 40%,#6b4e3d 100%);color:#3d2914;min-height:100vh;}
.guest-access-card{max-width:400px;margin:0 auto;}
.guest-access-card-frame{background:linear-gradient(180deg,#fffdf8 0%,#f5ebe0 55%,#efe4d8 100%);border-radius:20px;box-shadow:0 24px 48px rgba(0,0,0,0.35),0 0 0 1px rgba(255,255,255,0.25) inset,0 0 0 3px #c9a227,0 0 0 6px #5c3d2e;overflow:hidden;}
.guest-access-card-header{background:linear-gradient(135deg,#1e1424 0%,#3d2914 50%,#5c3d2e 100%);color:#f5ebe0;padding:1.25rem 1.25rem 1.1rem;text-align:center;border-bottom:3px solid #c9a227;}
.guest-access-card-header-inner{display:flex;flex-direction:column;align-items:center;gap:0.65rem;}
.guest-access-card-logo{width:72px;height:72px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.35));}
.guest-access-card-headlines{text-align:center;}
.guest-access-card-brand{font-family:Georgia,serif;font-size:1.35rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;margin:0;color:#f5e6c8;}
.guest-access-card-tagline{font-size:0.72rem;text-transform:uppercase;letter-spacing:0.28em;margin:0.25rem 0 0;color:rgba(245,230,200,0.85);}
.guest-access-card-date{font-size:0.82rem;margin:0.5rem 0 0;color:#e8d4a8;font-weight:500;}
.guest-access-card-main{padding:0;}
.guest-access-card-photo{width:100%;aspect-ratio:1;max-height:280px;background:#e8dfd0;overflow:hidden;}
.guest-access-card-photo img{width:100%;height:100%;object-fit:cover;display:block;}
.guest-access-card-photo-placeholder{min-height:180px;background:linear-gradient(160deg,#e8dfd0,#c4b5a0);}
.guest-access-card-body{padding:1.35rem 1.35rem 1.5rem;text-align:center;}
.guest-access-card-name{font-size:1.55rem;font-weight:600;margin:0 0 0.4rem;line-height:1.2;color:#2a1810;}
.guest-access-card-email{font-size:0.82rem;color:#5c4a3d;margin:0 0 1rem;word-break:break-all;}
.guest-access-card-admit{font-size:0.92rem;line-height:1.5;margin:0 0 1.15rem;padding:0.85rem 1rem;background:rgba(201,162,39,0.12);border:1px solid rgba(92,61,46,0.25);border-radius:12px;color:#3d2914;}
.guest-access-card-admit strong{color:#1e1424;}
.guest-access-card-qr{display:flex;justify-content:center;margin:0 0 0.75rem;}
.guest-access-card-qr img{display:block;border-radius:10px;border:4px solid #fff;box-shadow:0 4px 16px rgba(42,24,16,0.15);}
.guest-access-card-hint{font-size:0.72rem;color:#6b5344;margin:0;line-height:1.45;max-width:280px;margin-left:auto;margin-right:auto;}
.guest-access-card-footer{text-align:center;font-size:0.65rem;text-transform:uppercase;letter-spacing:0.15em;padding:0.65rem 1rem;background:#2a1810;color:rgba(245,235,220,0.55);}
CSS;
}

/**
 * Full standalone HTML document (saved file / download). Embeds logo and photo for offline viewing.
 */
function render_guest_access_card_document(array $guest, string $base = ''): string {
    $inner = render_guest_access_card($guest, $base, true);
    $css = guest_access_card_inline_styles();
    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access pass — '
        . htmlspecialchars(guest_display_name($guest), ENT_QUOTES, 'UTF-8')
        . '</title><style>' . $css . '</style></head><body>' . $inner . '</body></html>';
}

function guest_access_card_download_filename(array $guest): string {
    $safe = preg_replace('/[^a-zA-Z0-9-_]+/', '-', guest_display_name($guest) ?: 'guest');
    $safe = trim($safe, '-') ?: 'guest';
    return 'access-pass-' . $safe . '.html';
}
