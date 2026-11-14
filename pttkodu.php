<?php
// pttkodu.php - Düzeltilmiş Versiyon

function apiIstekAt($payload, &$debugInfo) {
    $url = "https://www.ptt.gov.tr/api/posta-kodu";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json, text/plain, */*",
            "Origin: https://www.ptt.gov.tr",
            "Referer: https://www.ptt.gov.tr/posta-kodu",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $debugInfo[] = [
        "payload" => $payload,
        "http_code" => $httpCode,
        "response_snippet" => substr($response, 0, 100)
    ];

    return json_decode($response, true);
}

$il = $_GET['il'] ?? 'ANKARA';
$ilce = $_GET['ilce'] ?? 'YENİMAHALLE';
$mahalle = $_GET['mahalle'] ?? 'BEŞTEPE';

header('Content-Type: application/json; charset=utf-8');

$ilKodu = "6"; // Ankara
$debugLog = [];

// 1. İlçe kodunu bul
$ilceler = apiIstekAt(["action" => "ilceler", "il_kodu" => $ilKodu], $debugLog);
$ilceKodu = null;

if ($ilceler) {
    foreach ($ilceler as $i) {
        if (mb_strtoupper($i['ad'], 'UTF-8') === mb_strtoupper($ilce, 'UTF-8')) {
            $ilceKodu = (string) $i['kod']; // KRİTİK DÜZELTME: String'e cast et
            break;
        }
    }
}

if (!$ilceKodu) {
    echo json_encode(["hata" => "İlçe bulunamadı."], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Posta kodlarını çek (String olarak gönder)
$postakodlari = apiIstekAt([
    "action" => "postakodu", 
    "il_kodu" => $ilKodu, 
    "ilce_kodu" => (string) $ilceKodu
], $debugLog);

if (!$postakodlari) {
    echo json_encode([
        "hata" => "Posta kodu verisi alınamadı.",
        "debug" => $debugLog
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 3. Filtrele
$aranan = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $mahalle), 'UTF-8');
$bulunanlar = [];

foreach ($postakodlari as $item) {
    $itemMahalle = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $item['mahalleAdi']), 'UTF-8');
    $itemSokak = mb_strtoupper($item['sokakAdi'], 'UTF-8');
    
    if (mb_strpos($itemMahalle, $aranan) !== false || mb_strpos($itemSokak, $aranan) !== false) {
        $bulunanlar[] = [
            "Mahalle" => $item['mahalleAdi'],
            "Sokak" => $item['sokakAdi'],
            "Posta Kodu" => $item['posta_Kodu']
        ];
    }
}

if (count($bulunanlar) > 0) {
    echo json_encode(array_slice($bulunanlar, 0, 10), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "mesaj" => "Aranan mahalle/sokak bulunamadı.",
        "toplam_kayit" => count($postakodlari)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}