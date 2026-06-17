<?php
declare(strict_types=1);

require_once __DIR__ . '/guest-access-card.php';

/**
 * @return array{headers: list<string>, rows: list<list<string|int>>}
 */
function admin_guests_export_dataset(array $guests): array {
    $headers = [
        'ID',
        'Guest',
        'Title',
        'Email',
        'Gender',
        'Phone',
        'Invited by',
        'Party size',
        'RSVP status',
        'Check-in status',
        'Checked in count',
        'Created at',
        'Checked in at',
    ];

    $rows = [];
    foreach ($guests as $g) {
        $scanLimit = guest_party_scan_limit($g);
        $scanCount = guest_check_in_count($g);
        $regOk = (int) ($g['registration_confirmed'] ?? 0) === 1;
        if (guest_pass_fully_checked_in($g)) {
            $checkInStatus = 'In (' . $scanLimit . '/' . $scanLimit . ')';
        } elseif ($scanCount > 0) {
            $checkInStatus = 'Partial (' . $scanCount . '/' . $scanLimit . ')';
        } else {
            $checkInStatus = 'Not checked in';
        }

        $gender = (string) ($g['gender'] ?? '');
        if ($gender === 'male') {
            $gender = 'Male';
        } elseif ($gender === 'female') {
            $gender = 'Female';
        } else {
            $gender = '';
        }

        $rows[] = [
            (int) ($g['id'] ?? 0),
            (string) ($g['name'] ?? ''),
            (string) ($g['title'] ?? ''),
            (string) ($g['email'] ?? ''),
            $gender,
            (string) ($g['phone'] ?? ''),
            (string) ($g['invited_by'] ?? ''),
            $scanLimit,
            $regOk ? 'Confirmed' : 'Pending',
            $checkInStatus,
            $scanCount . '/' . $scanLimit,
            (string) ($g['created_at'] ?? ''),
            (string) ($g['checked_in_at'] ?? ''),
        ];
    }

    return ['headers' => $headers, 'rows' => $rows];
}

function admin_xlsx_column_name(int $index): string {
    $name = '';
    $n = $index + 1;
    while ($n > 0) {
        $n--;
        $name = chr(65 + ($n % 26)) . $name;
        $n = intdiv($n, 26);
    }

    return $name;
}

function admin_xlsx_cell_xml(string $ref, $value): string {
    if (is_int($value) || is_float($value)) {
        return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
    }

    $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return '<c r="' . $ref . '" t="inlineStr"><is><t>' . $text . '</t></is></c>';
}

function admin_build_xlsx_sheet_xml(array $headers, array $rows): string {
    $sheetRows = '';
    $allRows = array_merge([$headers], $rows);
    foreach ($allRows as $rowIndex => $row) {
        $rowNum = $rowIndex + 1;
        $cells = '';
        foreach (array_values($row) as $colIndex => $value) {
            $ref = admin_xlsx_column_name($colIndex) . (string) $rowNum;
            $cells .= admin_xlsx_cell_xml($ref, $value);
        }
        $sheetRows .= '<row r="' . $rowNum . '">' . $cells . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . '</worksheet>';
}

/**
 * Stream an .xlsx download (requires PHP ZipArchive).
 *
 * @param list<string> $headers
 * @param list<list<string|int>> $rows
 */
function admin_send_xlsx_download(array $headers, array $rows, string $filename): void {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('Excel export requires the PHP Zip extension.');
    }

    $sheetXml = admin_build_xlsx_sheet_xml($headers, $rows);
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    if ($tmp === false) {
        http_response_code(500);
        exit('Could not create export file.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        http_response_code(500);
        exit('Could not create Excel file.');
    }

    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.worksheet+xml"/>'
        . '</Types>'
    );
    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Registrations" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>'
    );
    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?: 'registrations.xlsx';
    if (substr(strtolower($safeName), -5) !== '.xlsx') {
        $safeName .= '.xlsx';
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . (string) filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}
