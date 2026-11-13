<?php
// pttkodu.php - Web Uyumlu Versiyon

function postaKoduSorgula($il, $ilce, $mahalle) {
    $url = "https://www.ptt.gov.tr/api/posta-kodu";
    $payload = json_encode([
        "İl_Adi" => mb_strtoupper($il, 'UTF-8'),
        "İlce_Adi" => mb_strtoupper($ilce, 'UTF-8'),
        "mahalleAdi" => mb_strtoupper($mahalle, 'UTF-8')
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (is_array($data)) {
        foreach ($data as $item) {
            if (mb_strtoupper($item['İl_Adi'] ?? '', 'UTF-8') === mb_strtoupper($il, 'UTF-8') &&
                mb_strtoupper($item['İlce_Adi'] ?? '', 'UTF-8') === mb_strtoupper($ilce, 'UTF-8') &&
                mb_strtoupper($item['mahalleAdi'] ?? '', 'UTF-8') === mb_strtoupper($mahalle, 'UTF-8')) {
                return $item['posta_Kodu'] ?? "Bulunamadı";
            }
        }
    }
    return "Posta kodu bulunamadı.";
}

// Web'den gelen GET parametrelerini al
$il = $_GET['il'] ?? '';
$ilce = $_GET['ilce'] ?? '';
$mahalle = $_GET['mahalle'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if ($il && $ilce && $mahalle) {
    echo json_encode(["posta_kodu" => postaKoduSorgula($il, $ilce, $mahalle)]);
} else {
    echo json_encode(["hata" => "Eksik parametre. Örnek: ?il=ANKARA&ilce=YENİMAHALLE&mahalle=BEŞTEPE"]);
}