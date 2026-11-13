<?php
// askerlik.php — Askerlik boy/kilo hesaplama isteği
// Not: Cloudflare cf_clearance cookie'si gerekir, yoksa 403.
// Alan adları (boy, kilo) DevTools → Payload'dan teyit edilmeli.

header('Content-Type: application/json; charset=utf-8');

$boy  = $_GET['boy']  ?? '170';   // cm
$kilo = $_GET['kilo'] ?? '60';    // kg

$url = 'https://istahed.org.tr/askerlikboykilohesaplama/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'boy'  => $boy,
        'kilo' => $kilo,
    ]),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: tr-TR',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://istahed.org.tr',
        'Referer: https://istahed.org.tr/askerlikboykilohesaplama/',
        'Upgrade-Insecure-Requests: 1',
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$html = curl_exec($ch);
$kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata = curl_error($ch);
curl_close($ch);

if ($html === false || $kod >= 400) {
    http_response_code(502);
    exit(json_encode([
        'ok'   => false,
        'kod'  => $kod,
        'hata' => $hata ?: 'İstek başarısız (Cloudflare engeli olabilir)',
    ], JSON_UNESCAPED_UNICODE));
}

// Sonuç metnini HTML'den çıkar (WordPress form sonucu)
$sonuc = '';
if (preg_match('/(askerlik|boy|kilo|sonuç|elverişli|elverişsiz)[^<]{0,200}/iu', strip_tags($html), $m)) {
    $sonuc = trim($m[0]);
}

echo json_encode([
    'ok'    => true,
    'kod'   => $kod,
    'boy'   => $boy,
    'kilo'  => $kilo,
    'sonuc' => $sonuc,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);