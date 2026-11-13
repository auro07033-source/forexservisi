<?php
// ask.php — kendi hesaplama (İSTAHED'e bağımlı değil)
header('Content-Type: application/json; charset=utf-8');

$boy  = (int)($_GET['boy']  ?? 170);
$kilo = (int)($_GET['kilo'] ?? 60);

if ($boy < 140 || $boy > 220 || $kilo < 30 || $kilo > 200) {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'hata'=>'Geçersiz boy/kilo (boy 140-220, kilo 30-200)'], JSON_UNESCAPED_UNICODE));
}

// TSK Sağlık Yeteneği Yönetmeliği — boy/kilo alt-üst sınır tablosu (kg)
// Kaynak: MSB Sağlık Yönetmeliği, yaklaşık değerler
$tablo = [
    140 => [33, 55],  145 => [36, 60],  150 => [39, 65],
    155 => [42, 70],  160 => [45, 75],  165 => [48, 80],
    170 => [51, 85],  175 => [54, 90],  180 => [57, 95],
    185 => [60, 100], 190 => [63, 105], 195 => [66, 110],
    200 => [69, 115], 205 => [72, 120], 210 => [75, 125],
    215 => [78, 130], 220 => [81, 135],
];

// Boya en yakın satırı seç (aşağı yuvarla)
$secili = null;
foreach ($tablo as $b => $aralik) {
    if ($boy >= $b) $secili = [$b, $aralik[0], $aralik[1]];
}
if ($secili === null) {
    $ilk = array_key_first($tablo);
    $secili = [$ilk, $tablo[$ilk][0], $tablo[$ilk][1]];
}

[$esasBoy, $altSinir, $ustSinir] = $secili;

// Sonuç
if ($kilo < $altSinir) {
    $durum = 'Askerliğe elverişli değildir (alt sınırın altında)';
    $fazla = $altSinir - $kilo;
    $aciklama = "Boyunuza göre en az {$altSinir} kg olmalısınız. {$fazla} kg eksiksiniz.";
} elseif ($kilo > $ustSinir) {
    $durum = 'Askerliğe elverişli değildir (üst sınırın üzerinde)';
    $fazla = $kilo - $ustSinir;
    $aciklama = "Boyunuza göre en fazla {$ustSinir} kg olmalısınız. {$fazla} kg fazlanız var.";
} else {
    $durum = 'Askerliğe elverişlidir';
    $aciklama = "Boy/kilo oranınız TSK sınırları içinde.";
}

// BKİ (VKİ) hesapla
$bki = round($kilo / (($boy/100) ** 2), 1);
$bkiSinif = match(true) {
    $bki < 18.5 => 'Zayıf',
    $bki < 25   => 'Normal',
    $bki < 30   => 'Fazla kilolu',
    $bki < 35   => 'Obez (1. derece)',
    $bki < 40   => 'Obez (2. derece)',
    default     => 'Obez (3. derece)',
};

echo json_encode([
    'ok'         => true,
    'boy'        => $boy,
    'kilo'       => $kilo,
    'bki'        => $bki,
    'bki_sinif'  => $bkiSinif,
    'alt_sinir'  => $altSinir,
    'ust_sinir'  => $ustSinir,
    'durum'      => $durum,
    'aciklama'   => $aciklama,
    'kaynak'     => 'TSK Sağlık Yeteneği Yönetmeliği (yaklaşık tablo)',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);