<?php
// EGM İhbar API - PHP
// Not: Hedef site bot koruması içerebilir; örnek iskelet aşağıdadır.

header('Content-Type: application/json; charset=utf-8');

$ihbarUrl = 'https://onlineislemler.egm.gov.tr/Sayfalar/Ihbar.aspx';

// 1) Oturum ve çerez dosyası
$cookieFile = __DIR__ . '/egm_cookies.txt';

// 2) İlk istek: sayfayı al, viewstate ve cookie topla
$ch = curl_init($ihbarUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR      => $cookieFile,
    CURLOPT_COOKIEFILE     => $cookieFile,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'hata' => 'Sayfa alınamadı', 'kod' => $httpCode], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3) ASP.NET hidden alanlarını çek
function hiddenAlan($html, $id) {
    if (preg_match('/id="' . preg_quote($id, '/') . '"[^>]*value="([^"]*)"/i', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    return '';
}

$viewState     = hiddenAlan($html, '__VIEWSTATE');
$viewStateGen  = hiddenAlan($html, '__VIEWSTATEGENERATOR');
$eventValid    = hiddenAlan($html, '__EVENTVALIDATION');

// 4) Kullanıcıdan gelen ihbar verisi
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$payload = [
    '__VIEWSTATE'          => $viewState,
    '__VIEWSTATEGENERATOR' => $viewStateGen,
    '__EVENTVALIDATION'    => $eventValid,
    // Form alan adlarını hedef sayfadaki gerçek id'lere göre güncelle:
    'txtAdSoyad'           => $input['ad_soyad']   ?? '',
    'txtTcKimlik'          => $input['tc']         ?? '',
    'txtTelefon'           => $input['telefon']    ?? '',
    'txtEposta'            => $input['eposta']     ?? '',
    'txtKonu'              => $input['konu']       ?? '',
    'txtIhbar'             => $input['mesaj']      ?? '',
    'btnGonder'            => 'Gönder',
];

// 5) POST isteği
$ch = curl_init($ihbarUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_COOKIEJAR      => $cookieFile,
    CURLOPT_COOKIEFILE     => $cookieFile,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: ' . $ihbarUrl,
        'Origin: https://onlineislemler.egm.gov.tr',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 30,
]);
$yanit = curl_exec($ch);
$kod   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata  = curl_error($ch);
curl_close($ch);

// 6) Sonucu döndür
if ($yanit === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'hata' => $hata], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok'       => $kod < 400,
    'kod'      => $kod,
    'yanit'    => mb_substr(strip_tags($yanit), 0, 2000, 'UTF-8'),
], JSON_UNESCAPED_UNICODE);