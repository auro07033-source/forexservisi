<?php
// SKRS E-Reçete listesini indirip XLSX'ten ilaç verisini çeken tam PHP
// Rusça: скачиваем .xlsx через ZipArchive + SimpleXML, без внешних библиотек.

header('Content-Type: application/json; charset=utf-8');

$jsonUrl = 'https://forexservisi.onrender.com/titck.php';

// 1) En yeni dosya linkini al
$ch = curl_init($jsonUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
]);
$json = curl_exec($ch);
$kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($json === false || $kod >= 400) {
    http_response_code(502);
    echo json_encode(['ok'=>false,'hata'=>'titck.php yanıt vermedi','kod'=>$kod], JSON_UNESCAPED_UNICODE);
    exit;
}

$veri = json_decode($json, true);
$dosyaUrl = $veri['en_yeni']['dosya'] ?? null;
if (!$dosyaUrl) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'hata'=>'Dosya linki yok'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) XLSX dosyasını indir
$tmp = sys_get_temp_dir() . '/skrs_' . md5($dosyaUrl) . '.xlsx';

if (!file_exists($tmp) || filesize($tmp) < 1000) {
    $fp = fopen($tmp, 'wb');
    $ch = curl_init($dosyaUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    curl_exec($ch);
    $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($kod >= 400) {
        http_response_code(502);
        echo json_encode(['ok'=>false,'hata'=>'XLSX indirilemedi','kod'=>$kod], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 3) XLSX'i aç (zip)
$zip = new ZipArchive();
if ($zip->open($tmp) !== true) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'hata'=>'XLSX açılamadı'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) Paylaşılan metinler (sharedStrings.xml)
$paylasilan = [];
if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $s = simplexml_load_string($xml);
    foreach ($s->si as $si) {
        $metin = '';
        if (isset($si->t)) $metin = (string)$si->t;
        elseif (isset($si->r)) foreach ($si->r as $r) $metin .= (string)$r->t;
        $paylasilan[] = $metin;
    }
}

// 5) İlk sayfa (sheet1)
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

if ($sheetXml === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'hata'=>'sheet1.xml yok'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sheet = simplexml_load_string($sheetXml);
$satirlar = [];
foreach ($sheet->sheetData->row as $row) {
    $satir = [];
    foreach ($row->c as $c) {
        $tip = (string)$c['t'];
        $v   = (string)$c->v;
        if ($tip === 's' && isset($paylasilan[(int)$v])) {
            $deger = $paylasilan[(int)$v];
        } elseif ($tip === 'inlineStr') {
            $deger = (string)$c->is->t;
        } else {
            $deger = $v;
        }
        $satir[] = $deger;
    }
    if (!empty($satir)) $satirlar[] = $satir;
}

// 6) Başlık + ilk 20 satır
$baslik = $satirlar[0] ?? [];
$ornek  = array_slice($satirlar, 1, 20);

echo json_encode([
    'ok'        => true,
    'dosya'     => $dosyaUrl,
    'boyut'     => filesize($tmp),
    'toplam_satir' => count($satirlar),
    'baslik'    => $baslik,
    'ornek'     => $ornek,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);