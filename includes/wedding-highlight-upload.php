<?php
declare(strict_types=1);

/**
 * @return array{path: ?string, error: ?string}
 */
function wedding_highlight_process_upload(?array $fileUpload, int $maxBytes): array {
    if (!is_array($fileUpload)) {
        return ['path' => null, 'error' => 'Please choose a photo to upload.'];
    }
    $fileErr = (int) ($fileUpload['error'] ?? UPLOAD_ERR_NO_FILE);
    $fileName = trim((string) ($fileUpload['name'] ?? ''));
    if ($fileErr === UPLOAD_ERR_NO_FILE || $fileName === '') {
        return ['path' => null, 'error' => 'Please choose a photo to upload.'];
    }
    if ($fileErr === UPLOAD_ERR_INI_SIZE || $fileErr === UPLOAD_ERR_FORM_SIZE) {
        return ['path' => null, 'error' => 'The photo must be 10 MB or smaller.'];
    }
    if ($fileErr !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Photo upload failed. Please try again.'];
    }
    if ((int) ($fileUpload['size'] ?? 0) > $maxBytes) {
        return ['path' => null, 'error' => 'The photo must be 10 MB or smaller.'];
    }
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return ['path' => null, 'error' => 'Please use a JPG, PNG, GIF or WebP image.'];
    }
    $uploadDir = rtrim(UPLOAD_PATH, '/') . '/wedding-highlights/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid('highlight_') . '.' . $ext;
    if (!move_uploaded_file($fileUpload['tmp_name'], $uploadDir . $filename)) {
        return ['path' => null, 'error' => 'Could not save the photo. Please try again.'];
    }

    return ['path' => 'uploads/wedding-highlights/' . $filename, 'error' => null];
}

function wedding_highlight_delete_file(?string $relativePath): void {
    $rel = trim((string) $relativePath);
    if ($rel === '' || !preg_match('#^uploads/wedding-highlights/[A-Za-z0-9][A-Za-z0-9._-]*$#', $rel)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

function wedding_highlight_normalize_hashtags(string $raw): string {
    $parts = preg_split('/\s+/', trim($raw)) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if ($part[0] !== '#') {
            $part = '#' . $part;
        }
        $tags[] = $part;
    }

    return implode(' ', array_values(array_unique($tags)));
}

function wedding_highlight_image_exists(array $row): bool {
    $path = trim((string) ($row['image_path'] ?? ''));
    if ($path === '') {
        return false;
    }

    return is_file(dirname(__DIR__) . '/' . ltrim($path, '/'));
}

/**
 * @return list<array<string, mixed>>
 */
function wedding_highlight_fetch_visible(PDO $pdo, ?int $limit = null): array {
    $sql = 'SELECT * FROM wedding_highlights WHERE is_visible = 1 ORDER BY created_at DESC, id DESC';
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        if (wedding_highlight_image_exists($row)) {
            $out[] = $row;
        }
    }

    return $out;
}
