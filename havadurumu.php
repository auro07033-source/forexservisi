<?php
/**
 * havadurumu.php — İl -> koordinat -> hava API
 * Kullanıcı: ?il=Ankara
 * Dönen: Open-Meteo ham JSON (default 7 gün)
 * Fallback: Open-Meteo 429 verirse wttr.in denenir
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ═══════════ GİRDİ ═══════════
$il = trim($_REQUEST['il'] ?? $_REQUEST['sehir'] ?? '');
if ($il === '') {
    echo json_encode(["error" => "il parametresi gerekli"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════ HTTP YARDIMCI ═══════════
function http_get($url, $params = [], $timeout = 15) {
    if ($params) {
        $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    $GLOBALS['HTTP_LAST'] = [
        'url'       => $url,
        'code'      => null,
        'err'       => null,
        'body_len'  => 0,
        'body_head' => '',
        'json_err'  => null,
        'method'    => null
    ];

    // ─── 1) cURL ───
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'HavaBot/1.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Encoding: identity'
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $GLOBALS['HTTP_LAST']['method']    = 'curl';
        $GLOBALS['HTTP_LAST']['code']      = $code;
        $GLOBALS['HTTP_LAST']['err']       = $err;
        $GLOBALS['HTTP_LAST']['body_len']  = strlen((string)$body);
        $GLOBALS['HTTP_LAST']['body_head'] = substr((string)$body, 0, 500);

        if (!$err && $code === 200 && $body) {
            $j = json_decode($body, true);
            if (is_array($j)) return $j;
            $GLOBALS['HTTP_LAST']['json_err'] = json_last_error_msg();
        }
    }

    // ─── 2) file_get_contents fallback ───
    $GLOBALS['HTTP_LAST']['method'] = 'file_get_contents';
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => $timeout,
            'user_agent' => 'HavaBot/1.0',
            'header'     => "Accept: application/json\r\nAccept-Encoding: identity\r\n"
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $body2 = @file_get_contents($url, false, $ctx);

    $GLOBALS['HTTP_LAST']['body_len']  = strlen((string)$body2);
    $GLOBALS['HTTP_LAST']['body_head'] = substr((string)$body2, 0, 500);

    if ($body2 === false) {
        $GLOBALS['HTTP_LAST']['err'] = 'file_get_contents failed';
        return null;
    }
    $j2 = json_decode($body2, true);
    if (!is_array($j2)) {
        $GLOBALS['HTTP_LAST']['json_err'] = json_last_error_msg();
        return null;
    }
    return $j2;
}

// ═══════════ 1) GEOCODING ═══════════
$geo = http_get('https://geocoding-api.open-meteo.com/v1/search', [
    'name'     => $il,
    'count'    => 1,
    'language' => 'tr',
    'format'   => 'json'
]);
$geo_debug = $GLOBALS['HTTP_LAST'];

if (!$geo || empty($geo['results'])) {
    echo json_encode([
        "error"     => "'$il' bulunamadı",
        "geo_debug" => $geo_debug
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$g      = $geo['results'][0];
$enlem  = $g['latitude'];
$boylam = $g['longitude'];
$tz     = $g['timezone'] ?? 'Europe/Istanbul';

// ═══════════ 2) HAVA API ═══════════
$hava = http_get('https://api.open-meteo.com/v1/forecast', [
    'latitude'  => $enlem,
    'longitude' => $boylam,
    'current'   => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code,apparent_temperature,pressure_msl',
    'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,wind_speed_10m_max,sunrise,sunset,uv_index_max',
    'hourly'    => 'temperature_2m,precipitation_probability,weather_code,wind_speed_10m',
    'timezone'  => $tz
]);
$hava_debug = $GLOBALS['HTTP_LAST'];

// ═══════════ 3) FALLBACK — wttr.in ═══════════
if (!$hava || (($hava_debug['code'] ?? 0) === 429)) {
    // wttr.in'den dene
    $wttr = http_get('https://wttr.in/' . rawurlencode($il), [
        'format' => 'j1'
    ]);

    if ($wttr && isset($wttr['current_condition'])) {
        $c = $wttr['current_condition'][0];
        echo json_encode([
            'kaynak'   => 'none',
            'il'       => $il,
            'konum'    => [
                'ad'     => $wttr['nearest_area'][0]['areaName'][0]['value'] ?? $il,
                'ulke'   => $wttr['nearest_area'][0]['country'][0]['value'] ?? '',
                'enlem'  => $wttr['nearest_area'][0]['latitude'] ?? $enlem,
                'boylam' => $wttr['nearest_area'][0]['longitude'] ?? $boylam,
            ],
            'current'  => [
                'temperature_2m'       => (float)$c['temp_C'],
                'relative_humidity_2m' => (int)$c['humidity'],
                'wind_speed_10m'       => (float)$c['windspeedKmph'],
                'weather_code'         => 0,
                'weather_desc'         => $c['weatherDesc'][0]['value'] ?? '',
                'apparent_temperature' => (float)$c['FeelsLikeC'],
                'pressure_msl'         => (float)$c['pressure'],
                'time'                 => date('c'),
            ],
            'daily'    => array_map(function($d) {
                return [
                    'date'    => $d['date'],
                    'max'     => (float)$d['maxtempC'],
                    'min'     => (float)$d['mintempC'],
                    'yagis'   => (float)($d['totalSnow_cm'] ?? 0),
                    'gunes_dog'=> ($d['astronomy'][0]['sunrise'] ?? '') . ' ' . $d['date'],
                    'gunes_bat'=> ($d['astronomy'][0]['sunset']  ?? '') . ' ' . $d['date'],
                ];
            }, $wttr['weather'] ?? []),
            'hourly'   => [],
            'not'      => 'Gunluk limit asildi !',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // İkisi de başarısız
    echo json_encode([
        "error"      => "Hava verisi alınamadı !",
        "geo_debug"  => $geo_debug,
        "hava_debug" => $hava_debug
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════ 4) HAM JSON DÖN ═══════════
echo json_encode($hava, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);