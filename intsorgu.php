<?php
// interpol.php — Interpol Kırmızı Bülten arama
// Kullanım: interpol.php?name=GIMARET&forename=THOMAS

header('Content-Type: application/json; charset=utf-8');

$name     = trim((string)($_GET['name'] ?? ''));
$forename = trim((string)($_GET['forename'] ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));

if ($name === '' && $forename === '') {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'hata'=>'name veya forename gerekli'], JSON_UNESCAPED_UNICODE));
}

$params = array_filter([
    'name'          => $name,
    'forename'      => $forename,
    'resultPerPage' => 20,
    'page'          => $page,
]);

$url = 'https://ws-public.interpol.int/notices/v1/red?' . http_build_query($params);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING       => '',
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: */*',
        'Accept-Language: tr-TR',
        'Origin: https://www.interpol.int',
        'Referer: https://www.interpol.int/',
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$yanit = curl_exec($ch);
$kod   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$hata  = curl_error($ch);
curl_close($ch);

if ($yanit === false || $kod >= 400) {
    http_response_code(502);
    exit(json_encode([
        'ok'   => false,
        'kod'  => $kod,
        'hata' => $hata ?: 'Interpol yanıt vermedi',
    ], JSON_UNESCAPED_UNICODE));
}

$veri = json_decode($yanit, true);

// Sadeleştirilmiş çıktı
$sonuclar = [];
foreach ($veri['_embedded']['notices'] ?? [] as $n) {
    $sonuclar[] = [
        'ad'        => $n['name'] ?? '',
        'soyad'     => $n['forename'] ?? '',
        'dogum'     => $n['date_of_birth'] ?? '',
        'uyruk'     => $n['nationalities'] ?? [],
        'bulte_no'  => $n['entity_id'] ?? '',
        'detay'     => $n['_links']['self']['href'] ?? '',
        'foto'      => $n['_links']['thumbnail']['href'] ?? '',
    ];
}

echo json_encode([
    'ok'      => true,
    'toplam'  => $veri['total'] ?? 0,
    'sorgu'   => ['name'=>$name, 'forename'=>$forename, 'page'=>$page],
    'sonuc'   => $sonuclar,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);