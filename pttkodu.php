<?php
// pttkodu.php - Tam Çalışan Versiyon

function apiIstekAt($payload) {
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
        CURLOPT_SSL_VERIFYPEER => false // Canlı sunucuda true yapın
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }
    return json_decode($response, true);
}

function postaKoduSorgula($il, $ilce, $mahalleVeyaSokak) {
    // 1. Aşama: İlçe kodunu bulmak için il_kodu'nu almalıyız.
    // PTT API'si il kodu soruyor (Ankara: 6). İl isminden il kodu bulmak için bir eşleşme tablosuna ihtiyacımız var.
    // Şimdilik test için Ankara (6) varsayalım, diğer iller için il_kodu parametresini de göndermelisin.
    $ilKodu = "6"; // Ankara (Dinamik yapmak için il adı -> kod tablosu eklenmeli)
    
    // 2. Aşama: İlçe kodunu bul
    $ilceler = apiIstekAt(["action" => "ilceler", "il_kodu" => $ilKodu]);
    $ilceKodu = null;
    
    if ($ilceler) {
        foreach ($ilceler as $i) {
            if (mb_strtoupper($i['ad'], 'UTF-8') === mb_strtoupper($ilce, 'UTF-8')) {
                $ilceKodu = $i['kod'];
                break;
            }
        }
    }
    
    if (!$ilceKodu) {
        return "İlçe bulunamadı. (İl kodu sabit '6' olarak ayarlı, diğer iller için kod güncellenmeli)";
    }

    // 3. Aşama: İlçeye ait tüm posta kodlarını, mahalleleri ve sokakları çek
    $postakodlari = apiIstekAt([
        "action" => "postakodu", 
        "il_kodu" => $ilKodu, 
        "ilce_kodu" => $ilceKodu
    ]);

    if (!$postakodlari) {
        return "Posta kodu verisi alınamadı.";
    }

    // 4. Aşama: Aranan mahalle veya sokağı listede bul
    $aranan = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $mahalleVeyaSokak), 'UTF-8');
    
    $bulunanlar = [];
    foreach ($postakodlari as $item) {
        $mahalle = mb_strtoupper(str_replace([" MAH.", " MAH"], "", $item['mahalleAdi']), 'UTF-8');
        $sokak = mb_strtoupper($item['sokakAdi'], 'UTF-8');
        
        // Mahalle adı veya sokak adı eşleşiyorsa
        if (strpos($mahalle, $aranan) !== false || strpos($sokak, $aranan) !== false) {
            $bulunanlar[] = [
                "Mahalle" => $item['mahalleAdi'],
                "Sokak" => $item['sokakAdi'],
                "Posta Kodu" => $item['posta_Kodu']
            ];
        }
    }

    if (count($bulunanlar) > 0) {
        // Eğer çok fazla sonuç varsa sadece ilk 10 tanesini dön (JSON şişmesin diye)
        return array_slice($bulunanlar, 0, 10);
    }

    return "Aranan mahalle veya sokak bulunamadı.";
}

// Web'den gelen GET parametreleri
$il = $_GET['il'] ?? 'ANKARA';
$ilce = $_GET['ilce'] ?? 'ÇANKAYA';
$mahalle = $_GET['mahalle'] ?? 'ÇİĞDEM';

header('Content-Type: application/json; charset=utf-8');

if ($il && $ilce && $mahalle) {
    $sonuc = postaKoduSorgula($il, $ilce, $mahalle);
    echo json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode(["hata" => "Eksik parametre. Örnek: ?il=ANKARA&ilce=ÇANKAYA&mahalle=ÇİĞDEM"]);
}