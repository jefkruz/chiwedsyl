<?php
declare(strict_types=1);

require_once __DIR__ . '/site-url.php';
require_once __DIR__ . '/guest-access-card.php';

/**
 * Normalize a phone number for wa.me (digits only, Nigeria-friendly).
 */
function guest_normalize_phone_for_whatsapp(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') {
        return '';
    }
    if ($digits[0] === '0') {
        return '234' . substr($digits, 1);
    }
    if (strpos($digits, '234') !== 0 && strlen($digits) === 10) {
        return '234' . $digits;
    }

    return $digits;
}

function guest_pass_code_from_guest(array $guest): string {
    return strtoupper(trim((string) ($guest['qr_code'] ?? '')));
}

function guest_pass_public_page_url(array $guest): string {
    $code = guest_pass_code_from_guest($guest);
    if (!guest_qr_secret_looks_valid($code)) {
        return '';
    }

    return build_public_site_url('guest-pass?code=' . rawurlencode($code));
}

function guest_pass_png_download_url(array $guest): string {
    $code = guest_pass_code_from_guest($guest);
    if (!guest_qr_secret_looks_valid($code)) {
        return '';
    }

    return build_public_site_url('guest-pass?code=' . rawurlencode($code) . '&download=png');
}

/**
 * @return array<string, mixed>|null
 */
function guest_fetch_confirmed_by_pass_code(PDO $pdo, string $code): ?array {
    if (!guest_qr_secret_looks_valid($code)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM guests WHERE UPPER(qr_code) = ? AND registration_confirmed = 1 LIMIT 1');
    $stmt->execute([strtoupper(trim($code))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function guest_whatsapp_invite_message(array $guest): string {
    $displayName = guest_display_name($guest);
    $greeting = $displayName !== ''
        ? 'Warm greetings dear ' . $displayName . ','
        : 'Warm greetings dear Esteemed,';

    $passUrl = guest_pass_public_page_url($guest);
    $pngUrl = guest_pass_png_download_url($guest);

    $message = $greeting . "\n\n"
        . "We are excited to give you an access card to #Oma&Syl26 event scheduled to hold at waterfalls event centre Billings way Ikeja, on 20th June, 2026.\n\n"
        . "Pls download your unique guest pass below.\n\n";

    if ($passUrl !== '') {
        $message .= "View your pass:\n" . $passUrl . "\n\n";
    }
    if ($pngUrl !== '') {
        $message .= "Download pass (PNG):\n" . $pngUrl . "\n\n";
    }

    $message .= 'Do not share this with any other person.';

    return $message;
}

function guest_whatsapp_invite_url(array $guest): string {
    $phone = guest_normalize_phone_for_whatsapp((string) ($guest['phone'] ?? ''));
    if ($phone === '') {
        return '';
    }

    return 'https://wa.me/' . rawurlencode($phone) . '?text=' . rawurlencode(guest_whatsapp_invite_message($guest));
}

function guest_has_whatsapp_phone(array $guest): bool {
    return guest_normalize_phone_for_whatsapp((string) ($guest['phone'] ?? '')) !== '';
}
