<?php
// askerlik.php — kendi hesaplama (TSK yönetmeliği)
header('Content-Type: application/json; charset=utf-8');

$boy  = (int)($_GET['boy']  ?? 170);  // cm
$kilo = (int)($_GET['kilo'] ?? 60);   // kg

if ($boy < 100 || $boy > 250 || $kilo < 30 || $kilo > 300) {
    exit(json_encode(['ok'=>false,'hata'=>'Geçersiz boy/kilo']));
}

// TSK boy-kilo tablosu (alt/üst sınırlar, kg)
// Kaynak: TSK Sağlık Yeteneği Yönetmeliği
$tablo = [
    // boy => [min, max]
    150 => [42, 68],  155 => [45, 72],  160 => [48, 77],
    165 => [51, 82],  170 => [54, 87],  175 => [57, 92],
    180 => [60, 97],  185 => [63, 102], 190 => [66, 107],
    195 => [69, 112], 200 => [72, 117],
];

// En yakın boy satırını bul
$min = null; $max = null;
foreach ($tablo as $b => [$lo, $hi]) {
    if ($boy >= $b && ($min === null || abs($boy-$b) < abs($boy-$min))) {
        $min = $b; $max = $hi;
        $altSinir = $lo; $ustSinir = $hi;
    }
}

$durum = 'Bilinmiyor';
if ($kilo < $altSinir)      $durum = 'Askerliğe elverişli değildir (zayıf)';
elseif ($kilo > $ustSinir)  $durum = 'Askerliğe elverişli değildir (obez)';
else                        $durum = 'Askerliğe elverişlidir';

echo json_encode([
    'ok'        => true,
    'boy'       => $boy,
    'kilo'      => $kilo,
    'alt_sinir' => $altSinir ?? null,
    'ust_sinir' => $ustSinir ?? null,
    'durum'     => $durum,
    'not'       => 'TSK Sağlık Yeteneği Yönetmeliği tablosu (yaklaşık)',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);