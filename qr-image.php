<?php
/**
 * Same-origin QR PNG proxy so the access card (and html2canvas) are not tainted by cross-origin images.
 */
declare(strict_types=1);

$data = isset($_GET['d']) ? (string) $_GET['d'] : '';
if ($data === '' || strlen($data) > 2048) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad request';
    exit;
}

$url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&data=' . rawurlencode($data);
$ctx = stream_context_create([
    'http' => ['timeout' => 15],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
]);
$bin = @file_get_contents($url, false, $ctx);
if ($bin === false || strlen($bin) < 64) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'QR unavailable';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . (string) strlen($bin));
echo $bin;
