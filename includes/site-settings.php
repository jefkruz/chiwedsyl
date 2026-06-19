<?php
declare(strict_types=1);

const SITE_SETTING_RSVP_OPEN = 'rsvp_registrations_open';

function site_setting_get(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT value FROM site_settings WHERE key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function site_setting_set(PDO $pdo, string $key, string $value): void {
    $pdo->prepare('INSERT OR REPLACE INTO site_settings (key, value) VALUES (?, ?)')->execute([$key, $value]);
}

function rsvp_registrations_open(PDO $pdo): bool {
    return site_setting_get($pdo, SITE_SETTING_RSVP_OPEN, '1') !== '0';
}

function rsvp_set_registrations_open(PDO $pdo, bool $open): void {
    site_setting_set($pdo, SITE_SETTING_RSVP_OPEN, $open ? '1' : '0');
}
