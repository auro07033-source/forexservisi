<?php
// imeisorgu.php
// e-Devlet IMEI sorgulama - tam sürüm
// Kullanım: /imeisorgu.php?imei=123456789012345
// Debug:   /imeisorgu.php?imei=...&debug=1  (ham HTML döner)

mb_internal_encoding("UTF-8");
header('Content-Type: application/json; charset=utf-8');

$imei = $_GET['imei'] ?? '';
$debug = isset($_GET['debug']);

if (empty($imei)) {
    echo json_encode([
        "success" => false,
        "message" => "Hata! Lütfen IMEI numarası girin."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!ctype_digit($imei) || strlen($imei) !== 15) {
    echo json_encode([
        "success" => false,
        "message" => "Hata! IMEI 15 haneli olmalı."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 " .
             "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

$cookieFile = sys_get_temp_dir() . '/imei_' . md5($imei . microtime()) . '.txt';

$baseHeaders = [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
    "Accept-Language: tr-TR,tr;q=0.9,en;q=0.8",
    "Cache-Control: no-cache",
    "Pragma: no-cache",
    "Upgrade-Insecure-Requests: 1",
    "Sec-Fetch-Dest: document",
    "Sec-Fetch-Mode: navigate",
    "Sec-Fetch-Site: same-origin",
    "Sec-Fetch-User: ?1",
];

// ── 1. ADIM: Token ve data-unique al ──
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
    CURLOPT_HTTPHEADER => $baseHeaders,
]);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($debug && $httpCode !== 200) {
    @unlink($cookieFile);
    echo json_encode([
        "step" => "token_page",
        "http_code" => $httpCode,
        "length" => strlen($content),
        "html" => $content
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Token (input hidden)
if (!preg_match('/name="token"\s+value="([^"]+)"/', $content, $m)) {
    @unlink($cookieFile);
    echo json_encode([
        "success" => false,
        "message" => "Token bulunamadı. Sayfa değişmiş olabilir."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$token = $m[1];

// data-unique (body)
$unique = "";
if (preg_match('/data-unique="([^"]+)"/', $content, $u)) {
    $unique = $u[1];
}

// ── 2. ADIM: Sorguyu POST et ──
$postFields = [
    "txtImei" => $imei,
    "token" => $token,
];
if ($unique !== "") {
    $postFields["unique"] = $unique;
}

$postHeaders = array_merge($baseHeaders, [
    "Content-Type: application/x-www-form-urlencoded",
    "Origin: https://www.turkiye.gov.tr",
    "Referer: https://www.turkiye.gov.tr/imei-sorgulama",
    "Sec-Fetch-Site: same-origin",
    "Sec-Fetch-Mode: navigate",
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://www.turkiye.gov.tr/imei-sorgulama?submit",
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HTTPHEADER => $postHeaders,
]);
$getData = curl_exec($ch);
$httpCode2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);
@unlink($cookieFile);

if ($debug) {
    echo json_encode([
        "step" => "post_page",
        "http_code" => $httpCode2,
        "final_url" => $finalUrl,
        "length" => strlen($getData),
        "html" => $getData
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!$getData) {
    echo json_encode([
        "success" => false,
        "message" => "Sorgu yanıtı alınamadı."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── 3. ADIM: HTML parse ──
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $getData);
$xpath = new DOMXPath($dom);

// Hata sayfası mı?
$errNodes = $xpath->query("//*[contains(@class, 'errorContainer') or contains(@class, 'warningContainer')]");
if ($errNodes->length > 0) {
    $errMsg = trim($errNodes->item(0)->nodeValue);
    echo json_encode([
        "success" => false,
        "message" => $errMsg ?: "e-Devlet hata döndü.",
        "IMEI" => $imei
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Sonuç kutuları
$data = ["IMEI" => $imei];
$found = false;

// Yöntem 1: resultContainer içinde dt/dd
$resultBoxes = $xpath->query("//*[contains(@class, 'resultContainer')]");
foreach ($resultBoxes as $box) {
    $dts = $xpath->query(".//dt", $box);
    $dds = $xpath->query(".//dd", $box);
    for ($i = 0; $i < $dts->length; $i++) {
        $key = trim($dts->item($i)->nodeValue);
        $val = $dds->item($i) ? trim($dds->item($i)->nodeValue) : "";
        if ($key !== "") {
            $data[$key] = $val;
            $found = true;
        }
    }
}

// Yöntem 2: resultContainer içinde th/td
if (!$found) {
    $ths = $xpath->query("//*[contains(@class, 'resultContainer')]//th");
    $tds = $xpath->query("//*[contains(@class, 'resultContainer')]//td");
    for ($i = 0; $i < $ths->length; $i++) {
        $key = trim($ths->item($i)->nodeValue);
        $val = $tds->item($i) ? trim($tds->item($i)->nodeValue) : "";
        if ($key !== "") {
            $data[$key] = $val;
            $found = true;
        }
    }
}

// Yöntem 3: resultContainer içinde satır satır "Başlık: Değer"
if (!$found) {
    foreach ($resultBoxes as $box) {
        $text = trim($box->nodeValue);
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if ($k !== "" && mb_strlen($k) < 60) {
                    $data[$k] = $v;
                    $found = true;
                }
            }
        }
    }
}

// Yöntem 4: tüm sayfada "resultContainer" varsa düz metin
if (!$found) {
    if (strpos($getData, 'resultContainer') !== false) {
        $allText = trim(strip_tags($getData));
        $lines = preg_split('/\r?\n/', $allText);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                if ($k !== "" && mb_strlen($k) < 60) {
                    $data[$k] = $v;
                    $found = true;
                }
            }
        }
    }
}

if (!$found) {
    echo json_encode([
        "success" => false,
        "message" => "Sonuç ayrıştırılamadı. e-Devlet beklenen yanıtı vermedi.",
        "IMEI" => $imei,
        "ipucu" => "?debug=1 ile ham HTML'i kontrol et."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);