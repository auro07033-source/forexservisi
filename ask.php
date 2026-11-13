<?php
// ask.php — düzeltilmiş sürüm
header('Content-Type: application/json; charset=utf-8');

$boy  = (int)($_GET['boy']  ?? 170);
$kilo = (int)($_GET['kilo'] ?? 60);

$ch = curl_init('https://istahed.org.tr/askerlikboykilohesaplama/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['boy'=>$boy,'kilo'=>$kilo]),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html',
        'Accept-Language: tr-TR',
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: https://istahed.org.tr/askerlikboykilohesaplama/',
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$html = curl_exec($ch);
$kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $kod >= 400) {
    http_response_code(502);
    exit(json_encode(['ok'=>false,'kod'=>$kod,'hata'=>'İstek başarısız']));
}

// JSON-LD ve script'leri çıkar
$temiz = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
$temiz = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $temiz);
$metin = trim(preg_replace('/\s+/', ' ', strip_tags($temiz)));

// Askerlik sonucu ile ilgili cümleyi bul
$sonuc = null;
foreach ([
    '/(askerliğe\s+elverişli\s+değildir[^.]*\.)/iu',
    '/(askerliğe\s+elverişlidir[^.]*\.)/iu',
    '/(boy\s*ve\s*kilo[^.]*\.)/iu',
    '/(sonuç[^.]*\.)/iu',
] as $re) {
    if (preg_match($re, $metin, $m)) { $sonuc = trim($m[1]); break; }
}

// Boy/kilo değerlerini de döndür
echo json_encode([
    'ok'    => true,
    'kod'   => $kod,
    'boy'   => $boy,
    'kilo'  => $kilo,
    'sonuc' => $sonuc,
    'metin_uzunluk' => strlen($metin),
    // hata ayıklama için ilk 500 karakter
    'metin_ornek' => $sonuc ? null : mb_substr($metin, 0, 500),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);