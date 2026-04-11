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
    $qrData = function_exists('guest_qr_checkin_url_for_guest')
        ? guest_qr_checkin_url_for_guest($guest)
        : (string) ($guest['qr_code'] ?? '');
    if ($qrData === '') {
        $qrData = (string) ($guest['qr_code'] ?? '');
    }
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
    $html .= '<div class="guest-access-card-photo-stage">';
    if ($photoSrcAttr !== null && $photoSrcAttr !== '') {
        $html .= '<div class="guest-access-card-photo-ring"><img src="' . $photoSrcAttr . '" alt=""></div>';
    } elseif ($photoUrlWeb !== null) {
        $html .= '<div class="guest-access-card-photo-ring"><img src="' . htmlspecialchars($photoUrlWeb, ENT_QUOTES, 'UTF-8') . '" alt=""></div>';
    } else {
        $html .= '<div class="guest-access-card-photo-ring guest-access-card-photo-ring--empty" aria-hidden="true"></div>';
    }
    $html .= '</div>';

    $html .= '<div class="guest-access-card-body">';
    $html .= '<h2 class="guest-access-card-name">' . $displayName . '</h2>';
    $html .= '<p class="guest-access-card-email">' . $email . '</p>';
    $html .= '<div class="guest-access-card-admit">' . $partyLine . '</div>';
    $html .= '<div class="guest-access-card-qr"><img src="' . htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') . '" alt="Check-in QR code" width="200" height="200" loading="lazy"></div>';
    $html .= '<p class="guest-access-card-hint">Present this pass at entry. Staff scan the QR to check you in (once per pass). Download your pass as an image to save on your phone.</p>';
    $html .= '</div></div>';
    $html .= '<footer class="guest-access-card-footer">Official guest pass · keep private</footer>';
    $html .= '</div></article>';
    return $html;
}

function guest_access_card_download_filename(array $guest): string {
    $safe = preg_replace('/[^a-zA-Z0-9-_]+/', '-', guest_display_name($guest) ?: 'guest');
    $safe = trim($safe, '-') ?: 'guest';
    return 'access-pass-' . $safe . '.png';
}
