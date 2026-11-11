<?php
/**
 * config.php — Forex Sorgulama Hizmeti
 * İletişim: Telegram @cmrbaskani
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ═══════════ SABİTLER ═══════════
define('DATA_DIR',      __DIR__ . '/data');
define('USERS_FILE',    DATA_DIR . '/users.json');
define('CHAT_FILE',     DATA_DIR . '/chat.json');
define('AI_CHAT_FILE',  DATA_DIR . '/ai_chat.json');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');
define('LOG_FILE',      DATA_DIR . '/admin_log.json');

// ═══════════ HAVA DURUMU ═══════════
define('HAVA_CACHE_FILE', DATA_DIR . '/hava_cache.json');
define('HAVA_CACHE_TTL',  1800);
define('GEO_URL',         'https://geocoding-api.open-meteo.com/v1/search');
define('HAVA_URL',        'https://api.open-meteo.com/v1/forecast');

// ═══════════ WORDLIST / BRUTE ═══════════
define('TR_WORDLIST',    __DIR__ . '/tr_wordlist.txt');
define('BRUTE_LOG_FILE', DATA_DIR . '/brute_log.json');

// ═══════════ KRAFTON ═══════════
define('KRAFTON_LOG_FILE', DATA_DIR . '/krafton_log.json');
define('KRAFTON_URL',      'https://accounts.krafton.com/auth/login/local');

// ═══════════ GÖRSEL ═══════════
define('BG_IMAGE',       'https://i.hizliresim.com/loreuqk4.jpg');
define('DEFAULT_AVATAR', 'https://i.hizliresim.com/midnihxu.jpg');

// ═══════════ ADMIN ═══════════
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'forex:qw24');

// ═══════════ AI ═══════════
define('AI_API',    'https://ucretsizservicetr.onrender.com/gptpro.php');
define('AI_KEY',    'cmrbaskani_2026_secret_key_xyz');
define('AI_DEVICE', 'dev_qyodisa8wzo_1789992264510');

// ═══════════ KURULUM ═══════════
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);

// ═══════════ YARDIMCI ═══════════
function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function read_json($file, $default = []) {
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    if (!$raw) return $default;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $default;
}

function write_json($file, $data) {
    if (file_exists($file)) @copy($file, $file . '.bak');
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// ═══════════ KULLANICI ═══════════
function load_users() { return read_json(USERS_FILE, []); }
function save_users($u) { write_json(USERS_FILE, $u); }

function find_user($username) {
    $u = strtolower(trim($username));
    foreach (load_users() as $user) {
        if (strtolower($user['username'] ?? '') === $u) return $user;
    }
    return null;
}

function find_user_by_id($id) {
    foreach (load_users() as $user) {
        if (($user['id'] ?? '') === $id) return $user;
    }
    return null;
}

function current_user() {
    if (!isset($_SESSION['user_id'])) return null;
    return find_user_by_id($_SESSION['user_id']);
}

function is_logged_in() { return current_user() !== null; }
function is_admin() { $u = current_user(); return $u && !empty($u['is_admin']); }

function is_vip() {
    $u = current_user();
    if (!$u) return false;
    if (!empty($u['is_admin'])) return true;
    if (!empty($u['is_vip'])) return true;
    if (($u['rank'] ?? '') === 'VIP') return true;
    return false;
}

function require_login() {
    if (!is_logged_in()) json_out(["success"=>false, "error"=>"Oturum gerekli", "login"=>true], 401);
}

function require_admin() {
    require_login();
    if (!is_admin()) json_out(["success"=>false, "error"=>"Yetkin yok"], 403);
}

// ═══════════ YARDIMCI ═══════════
function gen_id() { return bin2hex(random_bytes(8)); }
function sanitize_username($u) { return preg_replace('/[^a-zA-Z0-9_]/', '', $u); }
function sanitize_filename($n) { return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $n); }
function now_iso() { return date('c'); }

function avatar_url($user) {
    if (!empty($user['avatar']) && strpos($user['avatar'], 'ui-avatars.com') === false) {
        return $user['avatar'];
    }
    return DEFAULT_AVATAR;
}

function is_online($user) {
    if (empty($user['last_seen'])) return false;
    return (time() - strtotime($user['last_seen'])) < 300;
}

// ═══════════ LOG ═══════════
function add_log($action, $detail = '') {
    $logs = read_json(LOG_FILE, []);
    $me = current_user();
    $logs[] = [
        'id'     => gen_id(),
        'admin'  => $me['username'] ?? 'system',
        'action' => $action,
        'detail' => $detail,
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '-',
        'ts'     => time(),
    ];
    if (count($logs) > 1000) $logs = array_slice($logs, -1000);
    write_json(LOG_FILE, $logs);
}

// ═══════════ AYARLAR ═══════════
function load_settings() {
    return read_json(SETTINGS_FILE, [
        'theme'         => 'dark',
        'announcement'  => '',
        'maintenance'   => false,
        'ai_enabled'    => true,
        'register_open' => true,
        'site_title'    => 'Forex Sorgulama',
        'ai_model'      => 'pollinations',
        'brute_enabled' => true,
    ]);
}
function save_settings($s) { write_json(SETTINGS_FILE, $s); }

// ═══════════ BURÇ ═══════════
function burcHesapla($tarih) {
    if (!$tarih) return '';
    $tarih = str_replace(['/', '.'], '-', $tarih);
    $parts = explode('-', $tarih);
    if (count($parts) !== 3) return '';
    if ((int)$parts[0] > 1900) { $y = (int)$parts[0]; $m = (int)$parts[1]; $d = (int)$parts[2]; }
    else { $d = (int)$parts[0]; $m = (int)$parts[1]; $y = (int)$parts[2]; }

    $burclar = [
        ['01','20','02','18','Kova ♒'], ['02','19','03','20','Balık ♓'],
        ['03','21','04','19','Koç ♈'],  ['04','20','05','20','Boğa ♉'],
        ['05','21','06','20','İkizler ♊'], ['06','21','07','22','Yengeç ♋'],
        ['07','23','08','22','Aslan ♌'], ['08','23','09','22','Başak ♍'],
        ['09','23','10','22','Terazi ♎'], ['10','23','11','21','Akrep ♏'],
        ['11','22','12','21','Yay ♐'],   ['12','22','01','19','Oğlak ♑'],
    ];
    foreach ($burclar as [$sm, $sd, $em, $ed, $isim]) {
        $sm = (int)$sm; $sd = (int)$sd; $em = (int)$em; $ed = (int)$ed;
        if ($sm <= $em) {
            if (($m > $sm || ($m == $sm && $d >= $sd)) && ($m < $em || ($m == $em && $d <= $ed))) return $isim;
        } else {
            if ($m >= $sm || $m <= $em) return $isim;
        }
    }
    return '';
}

// ═══════════ PUBLIC USER ═══════════
function public_user($u) {
    if (!$u) return null;
    return [
        'id'         => $u['id'],
        'username'   => $u['username'],
        'email'      => $u['email'] ?? '',
        'avatar'     => avatar_url($u),
        'bio'        => $u['bio'] ?? '',
        'birthday'   => $u['birthday'] ?? '',
        'horoscope'  => $u['horoscope'] ?? '',
        'city'       => $u['city'] ?? '',
        'job'        => $u['job'] ?? '',
        'instagram'  => $u['instagram'] ?? '',
        'telegram'   => $u['telegram'] ?? '',
        'phone'      => $u['phone'] ?? '',
        'website'    => $u['website'] ?? '',
        'gender'     => $u['gender'] ?? '',
        'notes'      => $u['notes'] ?? '',
        'rank'       => $u['rank'] ?? 'Üye',
        'is_vip'     => !empty($u['is_vip']) || ($u['rank'] ?? '') === 'VIP',
        'verified'   => !empty($u['verified']),
        'banned'     => !empty($u['banned']),
        'is_admin'   => !empty($u['is_admin']),
        'online'     => is_online($u),
        'last_seen'  => $u['last_seen'] ?? null,
        'created_at' => $u['created_at'] ?? null,
        'query_count'=> $u['query_count'] ?? 0,
        'login_count'=> $u['login_count'] ?? 0,
    ];
}