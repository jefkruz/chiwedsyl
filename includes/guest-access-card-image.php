<?php
declare(strict_types=1);

require_once __DIR__ . '/guest-access-card.php';

function guest_access_card_png_font_path(): ?string {
    $local = guest_access_card_project_root() . '/assets/fonts/Lora-Regular.ttf';
    if (is_file($local)) {
        return $local;
    }
    foreach ([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
    ] as $p) {
        if (is_file($p)) {
            return $p;
        }
    }
    return null;
}

/** @return resource|false|null */
function guest_access_card_png_load_image(string $path) {
    $info = @getimagesize($path);
    if ($info === false) {
        return null;
    }
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            return @imagecreatefrompng($path);
        case IMAGETYPE_GIF:
            return @imagecreatefromgif($path);
        case IMAGETYPE_WEBP:
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        default:
            return null;
    }
}

/**
 * Circular cover-crop of guest photo (transparent outside circle).
 *
 * @return resource|false|null
 */
function guest_access_card_png_guest_circle(string $absolutePhotoPath, int $diameter) {
    $src = guest_access_card_png_load_image($absolutePhotoPath);
    if (!$src) {
        return null;
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        imagedestroy($src);
        return null;
    }
    $scale = max($diameter / $sw, $diameter / $sh);
    $nw = (int) ceil($sw * $scale);
    $nh = (int) ceil($sh * $scale);
    $tmp = imagecreatetruecolor($nw, $nh);
    if (!$tmp) {
        imagedestroy($src);
        return null;
    }
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    imagedestroy($src);
    $sx = (int) (($nw - $diameter) / 2);
    $sy = (int) (($nh - $diameter) / 2);
    $square = imagecreatetruecolor($diameter, $diameter);
    imagecopy($square, $tmp, 0, 0, $sx, $sy, $diameter, $diameter);
    imagedestroy($tmp);

    $cx = ($diameter - 1) / 2.0;
    $cy = ($diameter - 1) / 2.0;
    $r = $diameter / 2.0 - 4.0;
    $r2 = $r * $r;
    $out = imagecreatetruecolor($diameter, $diameter);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    $tr = imagecolorallocatealpha($out, 0, 0, 0, 127);
    imagefill($out, 0, 0, $tr);
    imagealphablending($out, true);
    for ($y = 0; $y < $diameter; $y++) {
        for ($x = 0; $x < $diameter; $x++) {
            $dx = $x - $cx;
            $dy = $y - $cy;
            if ($dx * $dx + $dy * $dy <= $r2) {
                $rgb = imagecolorat($square, $x, $y);
                imagesetpixel($out, $x, $y, $rgb);
            }
        }
    }
    imagedestroy($square);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    return $out;
}

/** @return resource|false|null */
function guest_access_card_png_fetch_qr(string $qrData) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&format=png&data=' . rawurlencode($qrData);
    $ctx = stream_context_create(['http' => ['timeout' => 12]]);
    $bin = @file_get_contents($url, false, $ctx);
    if ($bin === false || $bin === '') {
        return null;
    }
    $im = @imagecreatefromstring($bin);
    return $im ?: null;
}

/**
 * @param resource $im
 * @return int baseline Y after last line
 */
function guest_access_card_png_text_wrap($im, string $font, int $size, int $color, string $text, int $left, int $baselineY, int $maxWidth, int $lineHeight): int {
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $line = '';
    $y = $baselineY;
    foreach ($words as $w) {
        $trial = $line === '' ? $w : $line . ' ' . $w;
        $bbox = imagettfbbox($size, 0, $font, $trial);
        if ($bbox === false) {
            continue;
        }
        $tw = (int) ($bbox[2] - $bbox[0]);
        if ($tw > $maxWidth && $line !== '') {
            imagettftext($im, $size, 0, $left, $y, $color, $font, $line);
            $y += $lineHeight;
            $line = $w;
        } else {
            $line = $trial;
        }
    }
    if ($line !== '') {
        imagettftext($im, $size, 0, $left, $y, $color, $font, $line);
        $y += $lineHeight;
    }
    return $y;
}

function guest_access_card_render_png_binary(array $guest): ?string {
    if (!extension_loaded('gd')) {
        return null;
    }
    $font = guest_access_card_png_font_path();
    if ($font === null) {
        return null;
    }

    $W = 720;
    $H = 1120;
    $im = imagecreatetruecolor($W, $H);
    if (!$im) {
        return null;
    }
    imagealphablending($im, true);

    $cream = imagecolorallocate($im, 252, 249, 242);
    $cream2 = imagecolorallocate($im, 236, 228, 214);
    $choc = imagecolorallocate($im, 61, 35, 20);
    $choc2 = imagecolorallocate($im, 42, 26, 18);
    $gold = imagecolorallocate($im, 201, 162, 39);
    $goldLight = imagecolorallocate($im, 230, 200, 100);
    $white = imagecolorallocate($im, 255, 255, 255);
    $muted = imagecolorallocate($im, 107, 80, 64);
    $tagC = imagecolorallocate($im, 232, 218, 195);

    imagefilledrectangle($im, 0, 0, $W, $H, $cream);
    for ($i = 0; $i < 24; $i++) {
        $t = $i / 23;
        $r = (int) (40 + (74 - 40) * $t);
        $g = (int) (28 + (48 - 28) * $t);
        $b = (int) (52 + (36 - 52) * $t);
        $c = imagecolorallocate($im, $r, $g, $b);
        $y1 = (int) ($i * 170 / 24);
        $y2 = (int) (($i + 1) * 170 / 24);
        imagefilledrectangle($im, 0, $y1, $W, $y2, $c);
    }
    imagefilledrectangle($im, 0, 170, $W, 178, $gold);

    $logoPath = guest_access_card_project_root() . '/assets/images/logo.png';
    if (is_file($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
            imagesavealpha($logo, true);
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $targetH = 56;
            $targetW = (int) ($lw * ($targetH / max(1, $lh)));
            $lx = (int) (($W - $targetW) / 2);
            $ly = 22;
            imagecopyresampled($im, $logo, $lx, $ly, 0, 0, $targetW, $targetH, $lw, $lh);
            imagedestroy($logo);
        }
    }

    $site = defined('SITE_NAME') ? (string) SITE_NAME : 'Wedding';
    $bbox = imagettfbbox(15, 0, $font, $site);
    if ($bbox !== false) {
        $tw = (int) ($bbox[2] - $bbox[0]);
        imagettftext($im, 15, 0, (int) (($W - $tw) / 2), 118, $goldLight, $font, $site);
    }
    $tag = 'CELEBRATION ACCESS PASS';
    $tb = imagettfbbox(9, 0, $font, $tag);
    if ($tb !== false) {
        $ttw = (int) ($tb[2] - $tb[0]);
        imagettftext($im, 9, 0, (int) (($W - $ttw) / 2), 144, $tagC, $font, $tag);
    }
    $dateLine = guest_access_card_event_date_label();
    if ($dateLine !== '') {
        $db = imagettfbbox(10, 0, $font, $dateLine);
        if ($db !== false) {
            $dtw = (int) ($db[2] - $db[0]);
            imagettftext($im, 10, 0, (int) (($W - $dtw) / 2), 164, $tagC, $font, $dateLine);
        }
    }

    imagefilledrectangle($im, 0, 178, $W, $H, $cream);
    imagefilledrectangle($im, 0, 178, $W, 368, $cream2);

    $photoPath = trim((string) ($guest['guest_photo_path'] ?? ''));
    $fullPhoto = $photoPath !== '' ? guest_access_card_project_root() . '/' . $photoPath : '';
    $diam = 228;
    $cx = (int) ($W / 2);
    $cy = 278;
    if ($fullPhoto !== '' && is_file($fullPhoto)) {
        $circle = guest_access_card_png_guest_circle($fullPhoto, $diam);
        if ($circle) {
            $ring = 7;
            imagefilledellipse($im, $cx, $cy, $diam + $ring * 2 + 10, $diam + $ring * 2 + 10, $gold);
            imagefilledellipse($im, $cx, $cy, $diam + $ring * 2, $diam + $ring * 2, $choc2);
            imagealphablending($im, true);
            imagecopy($im, $circle, $cx - (int) ($diam / 2), $cy - (int) ($diam / 2), 0, 0, $diam, $diam);
            imagedestroy($circle);
        }
    } else {
        imagefilledellipse($im, $cx, $cy, $diam + 18, $diam + 18, $gold);
        imagefilledellipse($im, $cx, $cy, $diam + 8, $diam + 8, $cream2);
    }

    $name = guest_display_name($guest);
    $nb = imagettfbbox(22, 0, $font, $name);
    if ($nb !== false) {
        $nw = (int) ($nb[2] - $nb[0]);
        imagettftext($im, 22, 0, (int) (($W - $nw) / 2), 398, $choc, $font, $name);
    }

    $email = (string) ($guest['email'] ?? '');
    $bboxMail = imagettfbbox(11, 0, $font, 'Mg');
    $mailBase = 428 + ($bboxMail !== false ? abs((int) $bboxMail[7]) : 12);
    $yAfterMail = guest_access_card_png_text_wrap($im, $font, 11, $muted, $email, 48, $mailBase, $W - 96, 22);

    $num = max(1, (int) ($guest['num_guests'] ?? 1));
    $extras = max(0, $num - 1);
    $party = $num === 1
        ? 'This pass admits 1 person at the gate.'
        : "This pass admits {$num} people total — you plus {$extras} guest" . ($extras === 1 ? '' : 's') . ' with you.';

    $boxTop = $yAfterMail + 20;
    $boxPadX = 28;
    $boxInnerW = $W - 80 - $boxPadX * 2;
    $estLines = max(2, (int) ceil(strlen($party) / 38));
    $boxH = min(120, 32 + $estLines * 24);
    $boxFill = imagecolorallocate($im, 255, 252, 246);
    imagefilledrectangle($im, 40, $boxTop, $W - 40, $boxTop + $boxH, $boxFill);
    imagesetthickness($im, 2);
    imagerectangle($im, 40, $boxTop, $W - 40, $boxTop + $boxH, $gold);
    imagesetthickness($im, 1);
    $bboxP = imagettfbbox(12, 0, $font, 'Mg');
    $pBase = $boxTop + 26 + ($bboxP !== false ? abs((int) $bboxP[7]) : 12);
    guest_access_card_png_text_wrap($im, $font, 12, $choc, $party, 40 + $boxPadX, $pBase, $boxInnerW, 24);

    $qrY = $boxTop + $boxH + 26;
    $qrData = function_exists('guest_qr_checkin_url_for_guest')
        ? guest_qr_checkin_url_for_guest($guest)
        : (string) ($guest['qr_code'] ?? '');
    if ($qrData === '') {
        $qrData = (string) ($guest['qr_code'] ?? '');
    }
    $qrIm = $qrData !== '' ? guest_access_card_png_fetch_qr($qrData) : null;
    if ($qrIm) {
        $qs = 200;
        $qx = (int) (($W - $qs) / 2);
        imagefilledrectangle($im, $qx - 8, $qrY - 8, $qx + $qs + 8, $qrY + $qs + 8, $white);
        imagerectangle($im, $qx - 8, $qrY - 8, $qx + $qs + 8, $qrY + $qs + 8, $cream2);
        imagecopyresampled($im, $qrIm, $qx, $qrY, 0, 0, $qs, $qs, imagesx($qrIm), imagesy($qrIm));
        imagedestroy($qrIm);
        $hintY = $qrY + $qs + 24;
    } else {
        imagettftext($im, 10, 0, 48, $qrY + 20, $muted, $font, 'QR could not be generated — show your email at the gate.');
        $hintY = $qrY + 48;
    }
    imagettftext($im, 9, 0, 48, $hintY, $muted, $font, 'Save on your phone. Staff scan the QR to check you in once.');

    imagefilledrectangle($im, 0, $H - 44, $W, $H, $choc2);
    $ft = 'Official guest pass · keep private';
    $fb = imagettfbbox(8, 0, $font, $ft);
    if ($fb !== false) {
        $fw = (int) ($fb[2] - $fb[0]);
        imagettftext($im, 8, 0, (int) (($W - $fw) / 2), $H - 16, imagecolorallocate($im, 200, 185, 165), $font, $ft);
    }

    imagesetthickness($im, 5);
    imagerectangle($im, 10, 10, $W - 11, $H - 11, $gold);
    imagesetthickness($im, 2);
    imagerectangle($im, 18, 18, $W - 19, $H - 19, $choc);

    ob_start();
    imagepng($im, null, 6);
    $png = ob_get_clean();
    imagedestroy($im);
    return $png !== false && $png !== '' ? $png : null;
}

function guest_access_card_send_png_download(array $guest): bool {
    $bin = guest_access_card_render_png_binary($guest);
    if ($bin === null) {
        return false;
    }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . guest_access_card_download_filename($guest) . '"');
    header('Content-Length: ' . (string) strlen($bin));
    echo $bin;
    return true;
}
