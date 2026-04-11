<?php
declare(strict_types=1);

/**
 * Absolute URL for a path under this install. SITE_PUBLIC_URL (config) overrides host detection.
 *
 * @param string $pathAndQuery e.g. "admin/scan?code=ABC"
 */
function build_public_site_url(string $pathAndQuery): string {
    $pathAndQuery = ltrim($pathAndQuery, '/');
    $base = defined('BASE') ? rtrim((string) BASE, '/') : '';
    $absPath = ($base === '' ? '' : $base) . '/' . $pathAndQuery;
    if ($absPath === '' || $absPath[0] !== '/') {
        $absPath = '/' . ltrim($absPath, '/');
    }

    $configured = trim((string) SITE_PUBLIC_URL, '/');
    if ($configured !== '') {
        return $configured . $absPath;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return $absPath;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . $absPath;
}

/** Guest qr_code values are 16 hex chars from registration. */
function guest_qr_secret_looks_valid(string $code): bool {
    return (bool) preg_match('/^[A-Fa-f0-9]{16}$/', trim($code));
}

/**
 * Full URL encoded in guest QR images — opens admin check-in when scanned (admin must be logged in).
 */
function guest_qr_checkin_url_from_code(string $qrCode): string {
    $qrCode = trim($qrCode);
    if (!guest_qr_secret_looks_valid($qrCode)) {
        return '';
    }
    $suffix = 'admin/scan?code=' . rawurlencode(strtoupper($qrCode));

    return build_public_site_url($suffix);
}

function guest_qr_checkin_url_for_guest(array $guest): string {
    return guest_qr_checkin_url_from_code((string) ($guest['qr_code'] ?? ''));
}
