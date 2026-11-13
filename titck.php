<?php
// ============================================================
// SKRS E-Reçete — SAĞLAM SÜRÜM
// Sorun: forexservisi.onrender.com (Render) kararsız.
// Çözüm: Aracıyı tamamen kaldır, TİTCK'ye doğrudan git.
// XLSX'i indir, cache'le, ZIP+XML ile parse et, JSON döndür.
// Rusça: убираем посредника Render, работаем напрямую с titck.gov.tr.
// ============================================================

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
ini_set('memory_limit', '512M');
set_time_limit(300);

// ---------- AYARLAR ----------
const MODUL_URL  = 'https://www.titck.gov.tr/dinamikmodul/43';
const CACHE_DIR  = __DIR__ . '/cache';
const CACHE_TTL  = 3600;          // 1 saat
const UA         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);

// ---------- YARDIMCI: cURL ----------
function httpGet(string $url, bool $binary = false, int $timeout = 120): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => '',                 // gzip/deflate
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: tr,en;q=0.9',
            'Referer: https://www.titck.gov.tr/',
            'Connection: keep-alive',
        ],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['ok' => $body !== false && $code >= 200 && $code < 400, 'code' => $code, 'body' => $body, 'err' => $err];
}

// ---------- 1) MODÜL SAYFASINDAN EN YENİ XLSX LİNKİ ----------
function enYeniXlsx(): ?string {
    $cache = CACHE_DIR . '/link.json';
    if (is_file($cache) && time() - filemtime($cache) < CACHE_TTL) {
        $j = json_decode((string)file_get_contents($cache), true);
        if (!empty($j['url'])) return $j['url'];
    }

    $r = httpGet(MODUL_URL);
    if (!$r['ok'] || !$r['body']) return null;

    // Tüm .xlsx linklerini topla
    preg_match_all('/href=["\']([^"\']+\.xlsx)["\']/i', $r['body'], $m);
    if (empty($m[1])) return null;

    $linkler = array_values(array_unique($m[1]));
    // Göreli yolları mutlak yap
    foreach ($linkler as &$l) {
        if (str_starts_with($l, '/')) $l = 'https://www.titck.gov.tr' . $l;
        elseif (!str_starts_with($l, 'http')) $l = 'https://www.titck.gov.tr/' . ltrim($l, '/');
    }
    unset($l);

    // Dosya adındaki tarihe göre (DD.MM.YYYY) en yenisini seç
    usort($linkler, function ($a, $b) {
        $ta = preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', basename($a), $ma) ? strtotime("$ma[3]-$ma[2]-$ma[1]") : 0;
        $tb = preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', basename($b), $mb) ? strtotime("$mb[3]-$mb[2]-$mb[1]") : 0;
        return $tb <=> $ta;
    });

    $son = $linkler[0];
    @file_put_contents($cache, json_encode(['url' => $son, 'ts' => time()]));
    return $son;
}

// ---------- 2) XLSX İNDİR (CACHE'Lİ) ----------
function xlsxIndir(string $url): ?string {
    $ad   = CACHE_DIR . '/' . md5($url) . '.xlsx';
    if (is_file($ad) && filesize($ad) > 5000) return $ad;

    $tmp = $ad . '.part';
    $fp  = fopen($tmp, 'wb');
    if (!$fp) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_HTTPHEADER     => ['Referer: https://www.titck.gov.tr/'],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($code < 200 || $code >= 400 || filesize($tmp) < 5000) {
        @unlink($tmp);
        return null;
    }
    @rename($tmp, $ad);
    return $ad;
}

// ---------- 3) XLSX PARSE (ZIP + XML, kütüphanesiz) ----------
function xlsxOku(string $dosya): array {
    $zip = new ZipArchive();
    if ($zip->open($dosya) !== true) return [];

    // sharedStrings
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = @simplexml_load_string($xml);
        if ($sx) foreach ($sx->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
            $shared[] = $t;
        }
    }

    // sheet1
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return [];

    $sx = @simplexml_load_string($sheetXml);
    if (!$sx) return [];

    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $c) {
            $t = (string)$c['t'];
            $v = (string)$c->v;
            if ($t === 's')          $val = $shared[(int)$v] ?? '';
            elseif ($t === 'inlineStr') $val = (string)$c->is->t;
            else                     $val = $v;
            $line[] = trim($val);
        }
        if ($line) $rows[] = $line;
    }
    return $rows;
}

// ---------- 4) ANA AKIŞ ----------
try {
    $link = enYeniXlsx();
    if (!$link) throw new RuntimeException('XLSX linki bulunamadı (TİTCK yanıt vermedi)');

    $dosya = xlsxIndir($link);
    if (!$dosya) throw new RuntimeException('XLSX indirilemedi');

    $satirlar = xlsxOku($dosya);
    if (!$satirlar) throw new RuntimeException('XLSX parse edilemedi');

    $baslik = $satirlar[0] ?? [];
    $veri   = array_slice($satirlar, 1);

    // opsiyonel: ?q=arama & ?limit=N
    $q     = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(5000, (int)($_GET['limit'] ?? 20)));

    if ($q !== '') {
        $veri = array_values(array_filter($veri, function ($r) use ($q) {
            foreach ($r as $h) if (mb_stripos($h, $q) !== false) return true;
            return false;
        }));
    }

    echo json_encode([
        'ok'      => true,
        'kaynak'  => MODUL_URL,
        'dosya'   => $link,
        'boyut'   => filesize($dosya),
        'baslik'  => $baslik,
        'toplam'  => count($veri),
        'limit'   => $limit,
        'ornek'   => array_slice($veri, 0, $limit),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'hata'  => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}