<?php
// ibanczm.php — IBAN çözümleme (hesapno.com modülü üzerinden)
// Kullanım: ibanczm.php?iban=TR330006100519786457841326

header('Content-Type: application/json; charset=utf-8');
set_time_limit(60);

$iban = strtoupper(preg_replace('/\s+/', '', $_GET['iban'] ?? ''));
if ($iban === '') {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'hata'=>'iban parametresi gerekli'], JSON_UNESCAPED_UNICODE));
}
if (strlen($iban) !== 26 || !str_starts_with($iban, 'TR')) {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'hata'=>'Geçersiz TR IBAN (26 hane olmalı)'], JSON_UNESCAPED_UNICODE));
}

// 1) Ana sayfa → cookie al
$jar = sys_get_temp_dir() . '/hesapno_' . md5($iban) . '.txt';
$ch = curl_init('https://hesapno.com/cozumleme_iban.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0',
    CURLOPT_HTTPHEADER     => ['Accept: text/html','Accept-Language: tr-TR'],
    CURLOPT_TIMEOUT        => 20,
]);
curl_exec($ch);
curl_close($ch);

// 2) Modüle POST
$ch = curl_init('https://hesapno.com/mod_iban_coz');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['iban' => $iban]),
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: tr-TR',
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://hesapno.com',
        'Referer: https://hesapno.com/cozumleme_iban.php',
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$html = curl_exec($ch);
$kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata = curl_error($ch);
curl_close($ch);
@unlink($jar);

if ($html === false || $kod >= 400) {
    http_response_code(502);
    exit(json_encode([
        'ok'   => false,
        'kod'  => $kod,
        'hata' => $hata ?: 'İstek başarısız',
    ], JSON_UNESCAPED_UNICODE));
}

// 3) Tablo satırlarını ayıkla
$temiz = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
$temiz = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $temiz);

$alanlar = [];
if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $temiz, $satirlar)) {
    foreach ($satirlar[1] as $s) {
        if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/si', $s, $h)) {
            if (count($h[1]) >= 2) {
                $k = trim(html_entity_decode(strip_tags($h[1][0]), ENT_QUOTES, 'UTF-8'));
                $v = trim(html_entity_decode(strip_tags($h[1][1]), ENT_QUOTES, 'UTF-8'));
                if ($k !== '') $alanlar[$k] = $v;
            }
        }
    }
}

$metin = trim(preg_replace('/\s+/', ' ', strip_tags($temiz)));

echo json_encode([
    'ok'   => true,
    'kod'  => $kod,
    'iban' => $iban,
    'data' => $alanlar ?: null,
    'ham'  => $alanlar ? null : mb_substr($metin, 0, 800, 'UTF-8'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);