<?php
mb_internal_encoding("UTF-8");
header('Content-Type: application/json; charset=utf-8');

$imei = $_GET['imei'] ?? '';

if (empty($imei)) {
    echo json_encode([
        "success" => false,
        "message" => "Hata! Lütfen IMEI numarası girin."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

// IMEI 15 hane olmalı
if (!ctype_digit($imei) || strlen($imei) !== 15) {
    echo json_encode([
        "success" => false,
        "message" => "Hata! IMEI 15 haneli olmalı."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

// Sabit, güvenilir bir User-Agent (iplogger gibi dış servis yok)
$userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 " .
             "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

$cookieFile = sys_get_temp_dir() . '/imei_cookie_' . md5($imei) . '.txt';

// ── 1. Adım: Token al ──
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://www.turkiye.gov.tr/imei-sorgulama",
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$content || $httpCode !== 200) {
    @unlink($cookieFile);
    echo json_encode([
        "success" => false,
        "message" => "e-Devlet sayfasına erişilemedi.",
        "http_code" => $httpCode
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

// Token bul
if (!preg_match('/name="token"\s+value="([^"]+)"/', $content, $m)) {
    @unlink($cookieFile);
    echo json_encode([
        "success" => false,
        "message" => "Token bulunamadı. Sayfa yapısı değişmiş olabilir."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}
$token = $m[1];

// ── 2. Adım: Sorguyu gönder ──
$postData = http_build_query([
    "txtImei" => $imei,
    "token" => $token,
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://www.turkiye.gov.tr/imei-sorgulama?submit",
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$getData = curl_exec($ch);
curl_close($ch);
@unlink($cookieFile);

if (!$getData) {
    echo json_encode([
        "success" => false,
        "message" => "Sorgu yanıtı alınamadı."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

// ── 3. Adım: HTML'i parse et ──
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $getData);
$xpath = new DOMXPath($dom);

// Sonuç yok mu?
$noResult = $xpath->query("//*[contains(@class, 'noResult')]");
if ($noResult->length > 0) {
    echo json_encode([
        "success" => false,
        "message" => "Bu IMEI için kayıt bulunamadı.",
        "IMEI" => $imei
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

// resultContainer içindeki dt/dd çiftleri
$data = ["IMEI" => $imei];
$resultBoxes = $xpath->query("//*[contains(@class, 'resultContainer')]");

foreach ($resultBoxes as $box) {
    $dts = $xpath->query(".//dt", $box);
    $dds = $xpath->query(".//dd", $box);

    for ($i = 0; $i < $dts->length; $i++) {
        $key = trim($dts->item($i)->nodeValue);
        $val = $dds->item($i) ? trim($dds->item($i)->nodeValue) : "";
        if ($key !== "") {
            $data[$key] = $val;
        }
    }
}

// Eğer dt/dd yoksa düz metin içinden çıkarmayı dene
if (count($data) === 1) {
    foreach ($resultBoxes as $box) {
        $text = trim($box->nodeValue);
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if ($k !== "") $data[$k] = $v;
            }
        }
    }
}

if (count($data) === 1) {
    echo json_encode([
        "success" => false,
        "message" => "Sonuç ayrıştırılamadı. Sayfa yapısı değişmiş olabilir.",
        "IMEI" => $imei
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}

echo json_encode([
    "success" => true,
    "data" => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);