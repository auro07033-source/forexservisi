<?php
// Nöbetçi Eczane Sorgu API - PHP
// turkiye.gov.tr JS ile çalışır; doğrudan HTML kazıma mümkün değil.
// Çözüm: TİTCK'nin arka plan JSON servisini kullan (e-Devlet arayüzünün çağırdığı endpoint).

header('Content-Type: application/json; charset=utf-8');

// 1) Parametreler
$il  = $_GET['il']  ?? 'İstanbul';   // il adı
$ilce = $_GET['ilce'] ?? '';          // opsiyonel ilçe

// 2) TİTCK nöbetçi eczane servisi (e-Devlet'in kullandığı kaynak)
$apiUrl = 'https://www.titck.gov.tr/api/nobetci-eczane?' . http_build_query([
    'il'   => $il,
    'ilce' => $ilce,
]);

// 3) İstek
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Referer: https://www.turkiye.gov.tr/',
    ],
]);
$yanit = curl_exec($ch);
$kod   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata  = curl_error($ch);
curl_close($ch);

// 4) Sonuç
if ($yanit === false || $kod >= 400) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'kod'   => $kod,
        'hata'  => $hata ?: 'Servis yanıt vermedi',
        'not'   => 'TİTCK servisi kapalıysa e-Devlet oturumu gerekir',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$veri = json_decode($yanit, true);

echo json_encode([
    'ok'   => true,
    'il'   => $il,
    'ilce' => $ilce,
    'adet' => is_array($veri) ? count($veri) : 0,
    'data' => $veri,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);