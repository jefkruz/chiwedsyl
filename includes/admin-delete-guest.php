<?php
declare(strict_types=1);

/**
 * Delete a guest registration and remove their uploads/guests photo when path is safe.
 */
function admin_delete_guest_registration(PDO $pdo, int $id): bool {
    if ($id < 1) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id, guest_photo_path FROM guests WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $rel = trim((string) ($row['guest_photo_path'] ?? ''));
    if ($rel !== '' && preg_match('#^uploads/guests/[A-Za-z0-9][A-Za-z0-9._-]*$#', $rel)) {
        $full = dirname(__DIR__) . '/' . $rel;
        if (is_file($full)) {
            @unlink($full);
        }
    }
    $pdo->prepare('DELETE FROM guests WHERE id = ?')->execute([$id]);

    return true;
}
