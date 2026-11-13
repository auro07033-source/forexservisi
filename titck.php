<?php
// ============================================================
// ZipArchive OLMADAN XLSX okuma — saf PHP
// Rusça: минимальный ZIP-ридер (local file header + deflate),
// без расширения zip. Работает на любом PHP с zlib.
// ============================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('memory_limit', '512M');
set_time_limit(300);

const MODUL_URL = 'https://www.titck.gov.tr/dinamikmodul/43';
const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL = 3600;
const UA        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36';

if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);

function httpGet(string $url, int $timeout = 120): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HTTPHEADER     => ['Accept: text/html,*/*', 'Referer: https://www.titck.gov.tr/'],
    ]);
    $b = curl_exec($ch);
    $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $b !== false && $c >= 200 && $c < 400, 'code' => $c, 'body' => $b];
}

// ---------- SAF PHP ZIP OKUYUCU ----------
// ZIP yapısı: her dosya "local file header" (PK\x03\x04) ile başlar.
// Sıkıştırma yöntemi 8 = deflate → gzinflate ile açılır.
function zipIcindenAl(string $zipData, string $hedef): ?string {
    $uzunluk = strlen($zipData);
    $ara = 0;
    while (($pos = strpos($zipData, "PK\x03\x04", $ara)) !== false) {
        // Local file header: 30 bayt sabit
        if ($pos + 30 > $uzunluk) break;
        $h = unpack('vversion/vextract/vflag/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($zipData, $pos + 4, 26));
        $adBas = $pos + 30;
        $ad = substr($zipData, $adBas, $h['nameLen']);
        $veriBas = $adBas + $h['nameLen'] + $h['extraLen'];

        // Sıkıştırılmış boyut 0 ise (data descriptor kullanılıyor) → central directory'den bulmak gerekir.
        // TİTCK dosyalarında genelde boyut header'da yazılı.
        $compSize = $h['compSize'];

        if ($ad === $hedef) {
            if ($compSize === 0) return null; // data descriptor — aşağıdaki fallback
            $sikisik = substr($zipData, $veriBas, $compSize);
            if ($h['method'] === 0) return $sikisik;              // stored
            if ($h['method'] === 8) return @gzinflate($sikisik);  // deflate
            return null; // desteklenmeyen yöntem
        }
        $ara = $veriBas + max($compSize, 1);
    }
    return null;
}

// ---------- EN YENİ XLSX LİNKİ ----------
function enYeniXlsx(): ?string {
    $c = CACHE_DIR . '/link.json';
    if (is_file($c) && time() - filemtime($c) < CACHE_TTL) {
        $j = json_decode((string)file_get_contents($c), true);
        if (!empty($j['url'])) return $j['url'];
    }
    $r = httpGet(MODUL_URL);
    if (!$r['ok'] || !$r['body']) return null;
    preg_match_all('/href=["\']([^"\']+\.xlsx)["\']/i', $r['body'], $m);
    if (empty($m[1])) return null;
    $linkler = array_values(array_unique($m[1]));
    foreach ($linkler as &$l) {
        if (str_starts_with($l, '/')) $l = 'https://www.titck.gov.tr' . $l;
        elseif (!str_starts_with($l, 'http')) $l = 'https://www.titck.gov.tr/' . ltrim($l, '/');
    }
    unset($l);
    usort($linkler, function ($a, $b) {
        $ta = preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', basename($a), $ma) ? strtotime("$ma[3]-$ma[2]-$ma[1]") : 0;
        $tb = preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', basename($b), $mb) ? strtotime("$mb[3]-$mb[2]-$mb[1]") : 0;
        return $tb <=> $ta;
    });
    $son = $linkler[0];
    @file_put_contents($c, json_encode(['url' => $son, 'ts' => time()]));
    return $son;
}

// ---------- XLSX İNDİR ----------
function xlsxIndir(string $url): ?string {
    $ad = CACHE_DIR . '/' . md5($url) . '.xlsx';
    if (is_file($ad) && filesize($ad) > 5000) return $ad;
    $tmp = $ad . '.part';
    $fp = fopen($tmp, 'wb');
    if (!$fp) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HTTPHEADER     => ['Referer: https://www.titck.gov.tr/'],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($code < 200 || $code >= 400 || filesize($tmp) < 5000) { @unlink($tmp); return null; }
    @rename($tmp, $ad);
    return $ad;
}

// ---------- XLSX PARSE (ZipArchive'sız) ----------
function xlsxOku(string $dosya): array {
    $zipData = (string)file_get_contents($dosya);

    // sharedStrings
    $shared = [];
    $ssXml = zipIcindenAl($zipData, 'xl/sharedStrings.xml');
    if ($ssXml !== null) {
        $sx = @simplexml_load_string($ssXml);
        if ($sx) foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }

    // sheet1
    $sheetXml = zipIcindenAl($zipData, 'xl/worksheets/sheet1.xml');
    if ($sheetXml === null) return [];
    $sx = @simplexml_load_string($sheetXml);
    if (!$sx) return [];

    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $c) {
            $t = (string)$c['t'];
            $v = (string)$c->v;
            if ($t === 's')            $val = $shared[(int)$v] ?? '';
            elseif ($t === 'inlineStr') $val = (string)$c->is->t;
            else                       $val = $v;
            $line[] = trim($val);
        }
        if ($line) $rows[] = $line;
    }
    return $rows;
}

// ---------- ANA AKIŞ ----------
try {
    $link = enYeniXlsx();
    if (!$link) throw new RuntimeException('XLSX linki bulunamadı');

    $dosya = xlsxIndir($link);
    if (!$dosya) throw new RuntimeException('XLSX indirilemedi');

    $satirlar = xlsxOku($dosya);
    if (!$satirlar) throw new RuntimeException('XLSX parse edilemedi (ZIP yapısı okunamadı)');

    $baslik = $satirlar[0] ?? [];
    $veri   = array_slice($satirlar, 1);

    $q     = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(5000, (int)($_GET['limit'] ?? 20)));
    if ($q !== '') {
        $veri = array_values(array_filter($veri, function ($r) use ($q) {
            foreach ($r as $h) if (mb_stripos($h, $q) !== false) return true;
            return false;
        }));
    }

    echo json_encode([
        'ok'     => true,
        'kaynak' => MODUL_URL,
        'dosya'  => $link,
        'boyut'  => filesize($dosya),
        'baslik' => $baslik,
        'toplam' => count($veri),
        'limit'  => $limit,
        'ornek'  => array_slice($veri, 0, $limit),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'hata'=>$e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}