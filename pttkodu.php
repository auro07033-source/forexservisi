<?php
// ptt_posta_kodu.php
// Çalıştırma: php ptt_posta_kodu.php

function postaKoduSorgula($il, $ilce, $mahalle) {
    $url = "https://www.ptt.gov.tr/api/posta-kodu";
    
    // API'ye gönderilecek veri (JSON formatında)
    $payload = json_encode([
        "İl_Adi" => mb_strtoupper($il, 'UTF-8'),
        "İlce_Adi" => mb_strtoupper($ilce, 'UTF-8'),
        "mahalleAdi" => mb_strtoupper($mahalle, 'UTF-8')
    ]);

    // cURL başlat
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Accept: */*",
            "Accept-Language: tr-TR,tr;q=0.9",
            "Content-Type: application/json",
            "Origin: https://www.ptt.gov.tr",
            "Referer: https://www.ptt.gov.tr/posta-kodu",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false // Test ortamı için, canlıda true yapın
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "cURL Hatası: " . $error;
    }

    if ($httpCode !== 200) {
        return "HTTP Hatası: " . $httpCode;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return "JSON Çözümleme Hatası: " . json_last_error_msg();
    }

    // Dönen veri bir dizi ise filtreleme yap
    if (is_array($data)) {
        foreach ($data as $item) {
            // Anahtarların varlığını kontrol et
            $itemIl = isset($item['İl_Adi']) ? mb_strtoupper($item['İl_Adi'], 'UTF-8') : '';
            $itemIlce = isset($item['İlce_Adi']) ? mb_strtoupper($item['İlce_Adi'], 'UTF-8') : '';
            $itemMahalle = isset($item['mahalleAdi']) ? mb_strtoupper($item['mahalleAdi'], 'UTF-8') : '';

            if ($itemIl === mb_strtoupper($il, 'UTF-8') &&
                $itemIlce === mb_strtoupper($ilce, 'UTF-8') &&
                $itemMahalle === mb_strtoupper($mahalle, 'UTF-8')) {
                return $item['posta_Kodu'] ?? "Posta kodu anahtarı bulunamadı.";
            }
        }
    }

    return "Posta kodu bulunamadı.";
}

// Terminalden veya formdan gelen veriler
// Terminal kullanımı için:
$il = readline("İl: ");
$ilce = readline("İlçe: ");
$mahalle = readline("Mahalle: ");

$kod = postaKoduSorgula($il, $ilce, $mahalle);
echo "Posta Kodu: " . $kod . PHP_EOL;