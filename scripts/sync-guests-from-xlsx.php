<?php
declare(strict_types=1);

/**
 * Sync guests in wedding.db from an admin Excel export.
 *
 * Usage:
 *   php scripts/sync-guests-from-xlsx.php [path/to/file.xlsx]
 *
 * - Updates rows that appear in the sheet (matched by ID).
 * - Deletes DB guests whose IDs are not in the sheet.
 * - Preserves qr_code and guest_photo_path on updated rows.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/admin-delete-guest.php';
require_once dirname(__DIR__) . '/includes/guest-access-card.php';

$xlsxPath = $argv[1] ?? dirname(__DIR__) . '/data/registrations_2026_06_17_051201_New (1).xlsx';
if (!is_file($xlsxPath)) {
    fwrite(STDERR, "Excel file not found: {$xlsxPath}\n");
    exit(1);
}

$backupPath = DB_PATH . '.bak-' . date('Y-m-d-His');
if (!copy(DB_PATH, $backupPath)) {
    fwrite(STDERR, "Could not back up database to {$backupPath}\n");
    exit(1);
}
echo "Backup: {$backupPath}\n";

$rows = xlsx_read_rows($xlsxPath);
if ($rows === [] || !isset($rows[0])) {
    fwrite(STDERR, "No rows found in spreadsheet.\n");
    exit(1);
}

$headers = array_map(static fn($h) => trim((string) $h), $rows[0]);
$col = xlsx_header_map($headers);
foreach (['ID', 'Guest', 'Email'] as $required) {
    if (!isset($col[$required])) {
        fwrite(STDERR, "Missing required column: {$required}\n");
        exit(1);
    }
}

$pdo = getDb();
$pdo->beginTransaction();

$sheetIds = [];
$updated = 0;
$skipped = 0;

for ($i = 1, $n = count($rows); $i < $n; $i++) {
    $row = $rows[$i];
    $id = (int) trim((string) xlsx_cell($row, $col, 'ID', ''));
    if ($id < 1) {
        continue;
    }
    $sheetIds[$id] = true;

    $guestLabel = trim((string) xlsx_cell($row, $col, 'Guest', ''));
    $email = trim((string) xlsx_cell($row, $col, 'Email', ''));
    if ($guestLabel === '' || $email === '') {
        echo "Skip row {$i}: missing guest name or email (id {$id})\n";
        $skipped++;
        continue;
    }

    $titleCol = trim((string) xlsx_cell($row, $col, 'Title', ''));
    [$title, $first, $last, $name] = xlsx_parse_guest_identity($guestLabel, $titleCol);

    $gender = xlsx_gender_to_db((string) xlsx_cell($row, $col, 'Gender', ''));
    $phone = xlsx_phone_to_string(xlsx_cell($row, $col, 'Phone', ''));
    $invitedBy = trim((string) xlsx_cell($row, $col, 'Invited by', ''));
    $partySize = max(1, (int) xlsx_cell($row, $col, 'Party size', 1));
    $registrationConfirmed = xlsx_rsvp_to_int((string) xlsx_cell($row, $col, 'RSVP status', ''));
    $checkInCount = xlsx_parse_check_in_count((string) xlsx_cell($row, $col, 'Checked in count', ''));
    if ($checkInCount === null) {
        $checkInCount = xlsx_parse_check_in_count((string) xlsx_cell($row, $col, 'Check-in status', ''));
    }
    $checkInCount = max(0, (int) ($checkInCount ?? 0));
    $checkedIn = $checkInCount >= $partySize ? 1 : 0;
    $createdAt = xlsx_datetime_to_sql(xlsx_cell($row, $col, 'Created at', ''));
    $checkedInAt = xlsx_datetime_to_sql(xlsx_cell($row, $col, 'Checked in at', ''));

    $existing = $pdo->prepare('SELECT id FROM guests WHERE id = ?');
    $existing->execute([$id]);
    if (!$existing->fetchColumn()) {
        echo "Skip row {$i}: id {$id} not in database\n";
        $skipped++;
        continue;
    }

    $sql = 'UPDATE guests SET
        name = ?, title = ?, first_name = ?, last_name = ?, email = ?, gender = ?, phone = ?,
        invited_by = ?, num_guests = ?, registration_confirmed = ?, check_in_count = ?,
        checked_in = ?, created_at = ?, checked_in_at = ?
        WHERE id = ?';
    $pdo->prepare($sql)->execute([
        $name,
        $title !== '' ? $title : null,
        $first !== '' ? $first : null,
        $last !== '' ? $last : null,
        $email,
        $gender !== '' ? $gender : null,
        $phone !== '' ? $phone : null,
        $invitedBy !== '' ? $invitedBy : null,
        $partySize,
        $registrationConfirmed,
        $checkInCount,
        $checkedIn,
        $createdAt,
        $checkedInAt,
        $id,
    ]);
    $updated++;
}

$toDelete = [];
$allIds = $pdo->query('SELECT id FROM guests')->fetchAll(PDO::FETCH_COLUMN);
foreach ($allIds as $dbId) {
    $dbId = (int) $dbId;
    if (!isset($sheetIds[$dbId])) {
        $toDelete[] = $dbId;
    }
}

$deleted = 0;
foreach ($toDelete as $deleteId) {
    if (admin_delete_guest_registration($pdo, $deleteId)) {
        $deleted++;
    }
}

$pdo->commit();

echo "Sheet rows: " . count($sheetIds) . "\n";
echo "Updated: {$updated}\n";
echo "Skipped: {$skipped}\n";
echo "Deleted: {$deleted}\n";
if ($toDelete !== []) {
    echo "Removed IDs: " . implode(', ', $toDelete) . "\n";
}

/**
 * @return list<list<mixed>>
 */
function xlsx_read_rows(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }

    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $shared = xlsx_parse_shared_strings($sharedXml);
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        return [];
    }

    return xlsx_parse_sheet_rows($sheetXml, $shared);
}

/**
 * @return list<string>
 */
function xlsx_parse_shared_strings(string $xml): array {
    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml)) {
        return [];
    }
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $out = [];
    foreach ($xp->query('//m:si') as $si) {
        $parts = [];
        foreach ($xp->query('.//m:t', $si) as $t) {
            $parts[] = $t->textContent;
        }
        $out[] = implode('', $parts);
    }

    return $out;
}

/**
 * @param list<string> $shared
 * @return list<list<mixed>>
 */
function xlsx_parse_sheet_rows(string $xml, array $shared): array {
    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml)) {
        return [];
    }
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach ($xp->query('//m:sheetData/m:row') as $rowNode) {
        $rowIndex = (int) $rowNode->attributes->getNamedItem('r')->nodeValue - 1;
        $cells = [];
        foreach ($xp->query('m:c', $rowNode) as $cell) {
            $ref = (string) $cell->attributes->getNamedItem('r')->nodeValue;
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            $colIndex = xlsx_col_to_index($m[1]);
            $type = (string) ($cell->attributes->getNamedItem('t')->nodeValue ?? '');
            $valueNode = $xp->query('m:v', $cell)->item(0);
            $inline = $xp->query('m:is', $cell)->item(0);
            $value = '';
            if ($type === 's' && $valueNode) {
                $value = $shared[(int) $valueNode->textContent] ?? '';
            } elseif ($type === 'inlineStr' && $inline) {
                $parts = [];
                foreach ($xp->query('.//m:t', $inline) as $t) {
                    $parts[] = $t->textContent;
                }
                $value = implode('', $parts);
            } elseif ($valueNode) {
                $value = $valueNode->textContent;
            }
            $cells[$colIndex] = $value;
        }
        if ($cells === []) {
            continue;
        }
        $max = max(array_keys($cells));
        $line = [];
        for ($c = 0; $c <= $max; $c++) {
            $line[] = $cells[$c] ?? '';
        }
        $rows[$rowIndex] = $line;
    }
    ksort($rows);

    return array_values($rows);
}

function xlsx_col_to_index(string $letters): int {
    $n = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }

    return $n - 1;
}

/**
 * @param list<mixed> $headers
 * @return array<string, int>
 */
function xlsx_header_map(array $headers): array {
    $map = [];
    foreach ($headers as $i => $header) {
        if ($header !== '') {
            $map[$header] = $i;
        }
    }

    return $map;
}

/**
 * @param list<mixed> $row
 * @param array<string, int> $col
 */
function xlsx_cell(array $row, array $col, string $header, mixed $default = ''): mixed {
    if (!isset($col[$header])) {
        return $default;
    }
    $idx = $col[$header];

    return $row[$idx] ?? $default;
}

/**
 * @return array{0: string, 1: string, 2: string, 3: string}
 */
function xlsx_parse_guest_identity(string $guestLabel, string $titleCol): array {
    $guest = trim($guestLabel);
    $title = trim($titleCol);
    $validTitles = array_keys(guest_valid_titles());

    if ($title === '') {
        usort($validTitles, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($validTitles as $candidate) {
            if (stripos($guest, $candidate . ' ') === 0) {
                $title = $candidate;
                $guest = trim(substr($guest, strlen($candidate)));
                break;
            }
        }
    } elseif (stripos($guest, $title . ' ') === 0) {
        $guest = trim(substr($guest, strlen($title)));
    }

    $parts = preg_split('/\s+/', $guest, 2) ?: [];
    $first = trim((string) ($parts[0] ?? ''));
    $last = trim((string) ($parts[1] ?? ''));
    $name = guest_composed_full_name($title, $first, $last);
    if ($name === '') {
        $name = $guestLabel;
    }

    return [$title, $first, $last, $name];
}

function xlsx_gender_to_db(string $gender): string {
    $g = strtolower(trim($gender));
    if ($g === 'male') {
        return 'male';
    }
    if ($g === 'female') {
        return 'female';
    }

    return '';
}

function xlsx_phone_to_string(mixed $phone): string {
    if ($phone === null || $phone === '') {
        return '';
    }
    if (is_float($phone) || is_int($phone)) {
        return preg_replace('/\.0+$/', '', (string) $phone) ?? (string) $phone;
    }
    $s = trim((string) $phone);
    if (preg_match('/^[0-9]+(?:\.0+)?$/', $s)) {
        return preg_replace('/\.0+$/', '', $s) ?? $s;
    }
    if (preg_match('/^(\d+(?:\.\d+)?)[eE][+-]?\d+$/', $s, $m)) {
        return number_format((float) $m[1], 0, '', '');
    }

    return $s;
}

function xlsx_rsvp_to_int(string $status): int {
    return strcasecmp(trim($status), 'Confirmed') === 0 ? 1 : 0;
}

function xlsx_parse_check_in_count(string $value): ?int {
    if (preg_match('/(\d+)\s*\/\s*(\d+)/', $value, $m)) {
        return (int) $m[1];
    }

    return null;
}

function xlsx_datetime_to_sql(mixed $value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    $serial = (float) $value;
    $unix = (int) round(($serial - 25569) * 86400);

    return gmdate('Y-m-d H:i:s', $unix);
}
