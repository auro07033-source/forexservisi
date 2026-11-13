<?php
// SKRS E-Reçete İlaç Listesi API — titck.gov.tr/dinamikmodul/43
// Rusça: парсим HTML-таблицу модуля 43, извлекаем ссылки на файлы .xlsx/.xls/.csv
// и даём прямой доступ к последнему списку.

header('Content-Type: application/json; charset=utf-8');

$modulUrl = 'https://www.titck.gov.tr/dinamikmodul/43';

// 1) HTML'i al
$ch = curl_init($modulUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER     => ['Accept: text/html'],
]);
$html = curl_exec($ch);
$kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata = curl_error($ch);
curl_close($ch);

if ($html === false || $kod >= 400) {
    http_response_code(502);
    echo json_encode(['ok'=>false,'kod'=>$kod,'hata'=>$hata ?: 'Sayfa alınamadı'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Satırları ayıkla: ad | tarih | dosya linki
$liste = [];
if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $html, $satirlar)) {
    foreach ($satirlar[1] as $satir) {
        // hücreler
        if (!preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $satir, $hucre)) continue;
        $alanlar = $hucre[1];
        if (count($alanlar) < 3) continue;

        $ad    = trim(html_entity_decode(strip_tags($alanlar[0]), ENT_QUOTES, 'UTF-8'));
        $tarih = trim(html_entity_decode(strip_tags($alanlar[1]), ENT_QUOTES, 'UTF-8'));

        // dosya linki
        $dosya = null;
        if (preg_match('/href=["\']([^"\']+)["\']/i', $alanlar[2], $m)) {
            $dosya = $m[1];
            if (str_starts_with($dosya, '/')) {
                $dosya = 'https://www.titck.gov.tr' . $dosya;
            }
        }

        if ($ad !== '' && $dosya) {
            $liste[] = ['ad'=>$ad, 'tarih'=>$tarih, 'dosya'=>$dosya];
        }
    }
}

// 3) En yeni kayıt (tarihe göre sırala)
usort($liste, function($a, $b) {
    $ta = strtotime(str_replace('.', '-', $a['tarih'])) ?: 0;
    $tb = strtotime(str_replace('.', '-', $b['tarih'])) ?: 0;
    return $tb <=> $ta;
});

$son = $liste[0] ?? null;

echo json_encode([
    'ok'       => true,
    'modul'    => $modulUrl,
    'toplam'   => count($liste),
    'en_yeni'  => $son,
    'liste'    => array_slice($liste, 0, 20), // ilk 20 kayıt
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);