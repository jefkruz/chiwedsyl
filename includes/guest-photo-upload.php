<?php
declare(strict_types=1);

/**
 * @return array{path: ?string, error: ?string}
 */
function guest_process_photo_upload(?array $fileUpload, int $maxBytes, bool $required): array {
    if (!is_array($fileUpload)) {
        return ['path' => null, 'error' => $required ? 'Please upload a photo for the guest pass.' : null];
    }
    $fileErr = (int) ($fileUpload['error'] ?? UPLOAD_ERR_NO_FILE);
    $fileName = trim((string) ($fileUpload['name'] ?? ''));
    if ($fileErr === UPLOAD_ERR_NO_FILE || $fileName === '') {
        return ['path' => null, 'error' => $required ? 'Please upload a photo (JPG, PNG, GIF or WebP, max 10 MB).' : null];
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
    $uploadDir = UPLOAD_PATH . '/guests/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid('guest_') . '.' . $ext;
    if (!move_uploaded_file($fileUpload['tmp_name'], $uploadDir . $filename)) {
        return ['path' => null, 'error' => 'Could not save the photo. Please try again.'];
    }

    return ['path' => 'uploads/guests/' . $filename, 'error' => null];
}

function guest_delete_stored_photo_file(?string $relativePath): void {
    $rel = trim((string) $relativePath);
    if ($rel === '' || !preg_match('#^uploads/guests/[A-Za-z0-9][A-Za-z0-9._-]*$#', $rel)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $rel;
    if (is_file($full)) {
        @unlink($full);
    }
}
