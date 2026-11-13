<?php
// ============================================================
// SKRS E-Reçete — DÜZELTİLMİŞ SÜRÜM
// ZIP okuma hatası giderildi: doğru header boyutu + central directory.
// ZipArchive gerekmez; sadece zlib.
// Rusça: исправлен парсер ZIP — правильные смещения и central directory.
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

// ---------- ZIP: central directory üzerinden oku ----------
// Central Directory kaydı: PK\x01\x02, sabit 42 bayt + name + extra + comment
// Local header'daki sıkıştırılmış boyut 0 olabilir (data descriptor),
// bu yüzden güvenilir kaynak central directory'dir.
function zipDosyaAl(string $zipData, string $hedef): ?string {
    $len = strlen($zipData);
    if ($len < 22) return null;

    // End of Central Directory (EOCD): PK\x05\x06, dosya sonundan geriye ara
    $eocdPos = strrpos($zipData, "PK\x05\x06");
    if ($eocdPos === false) return null;

    $eocd = unpack('vdisk/vcdDisk/vdiskEntries/ventries/VcdSize/VcdOffset/vcommentLen', substr($zipData, $eocdPos + 4, 18));
    if ($eocd === false) return null;

    $cdOffset = $eocd['cdOffset'];
    $entries  = $eocd['entries'];
    $p = $cdOffset;

    for ($i = 0; $i < $entries; $i++) {
        if ($p + 46 > $len) break;
        if (substr($zipData, $p, 4) !== "PK\x01\x02") break;

        $h = unpack(
            'vverMade/vverNeed/vflags/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
            substr($zipData, $p + 4, 42)
        );
        if ($h === false) break;

        $name = substr($zipData, $p + 46, $h['nameLen']);
        $p += 46 + $h['nameLen'] + $h['extraLen'] + $h['commentLen'];

        if ($name !== $hedef) continue;

        // Local header'a git
        $lo = $h['localOffset'];
        if ($lo + 30 > $len) return null;
        if (substr($zipData, $lo, 4) !== "PK\x03\x04") return null;

        $lh = unpack('vverNeed/vflags/vmethod/vmtime/vmdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($zipData, $lo + 4, 26));
        if ($lh === false) return null;

        $veriBas = $lo + 30 + $lh['nameLen'] + $lh['extraLen'];
        $sikisik = substr($zipData, $veriBas, $h['compSize']);
        if (strlen($sikisik) !== $h['compSize']) return null;

        if ($h['method'] === 0) return $sikisik;              // stored
        if ($h['method'] === 8) {                             // deflate
            $acik = @gzinflate($sikisik);
            return $acik === false ? null : $acik;
        }
        return null; // desteklenmeyen yöntem
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

// ---------- XLSX PARSE ----------
function xlsxOku(string $dosya): array {
    $zipData = (string)file_get_contents($dosya);

    $shared = [];
    $ssXml = zipDosyaAl($zipData, 'xl/sharedStrings.xml');
    if ($ssXml !== null) {
        $sx = @simplexml_load_string($ssXml);
        if ($sx) foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }

    $sheetXml = zipDosyaAl($zipData, 'xl/worksheets/sheet1.xml');
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
    if (!$satirlar) throw new RuntimeException('XLSX parse edilemedi');

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