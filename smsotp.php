<?php
// veepn-sms.php - VeePN ücretsiz SMS OTP proxy
// Kullanım:
//   ?action=countries                    → tüm ülkeler
//   ?action=numbers&country=belgium      → o ülkedeki numaralar
//   ?action=messages&country=belgium&number=32465123170
//   ?action=messages&country=belgium&number=32465123170&filter=instagram
//   ?action=messages&country=belgium&number=32465123170&page=2

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE = "https://veepn.com";
$UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36";

function req($url, $opts = []) {
    global $UA;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_USERAGENT => $UA,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json, text/plain, */*",
            "Accept-Language: tr-TR,tr;q=0.9",
            "X-Requested-With: XMLHttpRequest"
        ],
    ] + $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ["body" => $body, "code" => $code, "error" => $err];
}

$action  = $_GET['action'] ?? 'countries';
$country = $_GET['country'] ?? '';
$number  = preg_replace('/[^0-9]/', '', $_GET['number'] ?? '');
$page    = (int)($_GET['page'] ?? 1);
$count   = (int)($_GET['count'] ?? 100);
$filter  = strtolower(trim($_GET['filter'] ?? ''));
$latest  = isset($_GET['latest']);

// ==================== COUNTRIES ====================
if ($action === 'countries') {
    $r = req("$BASE/tr/online-sms/countries/");
    if ($r['code'] !== 200) {
        echo json_encode(["success" => false, "message" => "HTTP " . $r['code']]);
        exit;
    }
    $data = json_decode($r['body'], true);
    if (!is_array($data)) {
        echo json_encode(["success" => false, "message" => "Geçersiz JSON", "raw" => substr($r['body'], 0, 200)]);
        exit;
    }
    $online = array_values(array_filter($data, fn($c) => !empty($c['online'])));
    echo json_encode([
        "success" => true,
        "total" => count($data),
        "online_count" => count($online),
        "countries" => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==================== NUMBERS ====================
if ($action === 'numbers') {
    if (!$country) {
        echo json_encode(["success" => false, "message" => "country parametresi gerekli"]);
        exit;
    }
    $r = req("$BASE/tr/online-sms/countries/" . urlencode($country) . "/");
    if ($r['code'] !== 200) {
        echo json_encode(["success" => false, "message" => "HTTP " . $r['code']]);
        exit;
    }
    
    // HTML parse: numaraları bul
    $numbers = [];
    // VeePN numaraları HTML'de <a href="/tr/online-sms/countries/{slug}/{number}"> gibi
    if (preg_match_all('/\/online-sms\/countries\/' . preg_quote($country, '/') . '\/(\d{8,15})/i', $r['body'], $m)) {
        $numbers = array_values(array_unique($m[1]));
    }
    
    echo json_encode([
        "success" => true,
        "country" => $country,
        "numbers" => $numbers,
        "count" => count($numbers)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==================== MESSAGES ====================
if ($action === 'messages') {
    if (!$country || !$number) {
        echo json_encode(["success" => false, "message" => "country ve number parametreleri gerekli"]);
        exit;
    }
    
    $params = http_build_query([
        "page" => max(1, $page),
        "count" => min(max(1, $count), 500)
    ]);
    $url = "$BASE/tr/online-sms/countries/" . urlencode($country) . "/" . $number . "/?$params";
    
    $r = req($url, [
        CURLOPT_HTTPHEADER => [
            "Accept: application/json, text/plain, */*",
            "Accept-Language: tr-TR,tr;q=0.9",
            "Referer: $BASE/tr/online-sms/countries/" . urlencode($country) . "/" . $number,
            "Origin: $BASE",
            "X-Requested-With: XMLHttpRequest"
        ]
    ]);
    
    if ($r['code'] !== 200) {
        echo json_encode([
            "success" => false,
            "message" => "HTTP " . $r['code'],
            "raw" => substr($r['body'], 0, 300)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $data = json_decode($r['body'], true);
    if (!is_array($data) || !isset($data['data'])) {
        echo json_encode([
            "success" => false,
            "message" => "Geçersiz JSON yapısı",
            "raw" => substr($r['body'], 0, 300)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $messages = $data['data'];
    
    // Filtre
    if ($filter) {
        $messages = array_values(array_filter($messages, function($m) use ($filter) {
            $text = strtolower(($m['text'] ?? '') . ' ' . ($m['in_number'] ?? ''));
            return strpos($text, $filter) !== false;
        }));
    }
    
    if ($latest) $messages = array_slice($messages, 0, 10);
    
    // Sadece kod içerenler (opsiyonel)
    $only_codes = isset($_GET['only_codes']);
    if ($only_codes) {
        $messages = array_values(array_filter($messages, fn($m) => !empty($m['code']) && $m['code'] !== '-'));
    }
    
    echo json_encode([
        "success" => true,
        "country" => $country,
        "number" => $data['number'] ?? $number,
        "country_id" => $data['country'] ?? null,
        "current_page" => $data['current_page'] ?? $page,
        "per_page" => $data['per_page'] ?? $count,
        "total_messages" => $data['total'] ?? 0,
        "last_page" => $data['last_page'] ?? 1,
        "count_in_page" => count($messages),
        "messages" => array_map(function($m) {
            return [
                "id" => $m['id'] ?? null,
                "gonderen" => $m['in_number'] ?? '',
                "sms" => $m['text'] ?? '',
                "kod" => $m['code'] ?? '',
                "zaman" => $m['created_at'] ?? '',
                "ne_zaman" => $m['data_humans'] ?? ''
            ];
        }, $messages),
        "pagination" => [
            "has_next" => !empty($data['next_page_url']),
            "next_page" => ($data['current_page'] ?? 1) < ($data['last_page'] ?? 1) ? ($data['current_page'] ?? 1) + 1 : null,
            "prev_page" => ($data['current_page'] ?? 1) > 1 ? ($data['current_page'] ?? 1) - 1 : null
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    "success" => false,
    "message" => "Geçersiz action",
    "valid_actions" => ["countries", "numbers", "messages"]
]);