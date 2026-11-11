<?php
// plaka.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================================================
// JSON DOSYASINI YÜKLE (bir kere, bellekte tutulur)
// ==================================================
$jsonPath = __DIR__ . '/plaka.json';

if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'plaka.json dosyası bulunamadı: ' . $jsonPath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$jsonIcerik = file_get_contents($jsonPath);
if ($jsonIcerik === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'plaka.json okunamadı'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// UTF-8 BOM varsa temizle
$jsonIcerik = preg_replace('/^\xEF\xBB\xBF/', '', $jsonIcerik);

$plakalar = json_decode($jsonIcerik, true);

if (!is_array($plakalar)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'plaka.json geçersiz JSON formatı: ' . json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================================================
// YARDIMCI FONKSİYONLAR
// ==================================================

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    exit;
}

/**
 * Plakayı normalize et: büyük harf + boşluk/tire/nokta temizle
 */
function normalizePlaka(string $plaka): string
{
    $plaka = mb_strtoupper(trim($plaka), 'UTF-8');
    $plaka = preg_replace('/[\s\-\.\_]/u', '', $plaka);
    return $plaka ?? '';
}

/**
 * JSON'daki kaydı standart formata çevir
 */
function formatKayit(array $row): array
{
    return [
        'id'    => (int)($row['id']    ?? 0),
        'plaka' => (string)($row['plaka'] ?? ''),
        'isim'  => (string)($row['isim']  ?? ''),
        'tarih' => (string)($row['tarih'] ?? '-'),
        'gsmn'  => (string)($row['gsmn']  ?? '-'),
    ];
}

// ==================================================
// ROUTER
// ==================================================
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {

        // ============================================
        // 1) TÜM VERİLER (sayfalı)
        // GET /plaka.php?action=list&page=1&limit=1000
        // ============================================
        case 'list':
            $page   = max(1, (int)($_GET['page']  ?? 1));
            $limit  = min(10000, max(1, (int)($_GET['limit'] ?? 1000)));
            $offset = ($page - 1) * $limit;

            $total     = count($plakalar);
            $sayfaDizi = array_slice($plakalar, $offset, $limit);

            $data = array_map('formatKayit', $sayfaDizi);

            jsonResponse([
                'success'     => true,
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int)ceil($total / $limit),
                'count'       => count($data),
                'data'        => $data,
            ]);
            break;

        // ============================================
        // 2) TEK KAYIT
        // GET /plaka.php?action=get&id=1
        // ============================================
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(['success' => false, 'error' => 'Geçersiz id'], 400);
            }

            $bulunan = null;
            foreach ($plakalar as $row) {
                if ((int)($row['id'] ?? 0) === $id) {
                    $bulunan = $row;
                    break;
                }
            }

            if ($bulunan === null) {
                jsonResponse(['success' => false, 'error' => 'Kayıt bulunamadı'], 404);
            }

            jsonResponse([
                'success' => true,
                'data'    => formatKayit($bulunan),
            ]);
            break;

        // ============================================
        // 3) ARAMA
        // GET /plaka.php?action=search&q=34KG4978
        // GET /plaka.php?action=search&q=OĞUZHAN
        // ============================================
        case 'search':
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q === '') {
                jsonResponse(['success' => false, 'error' => 'Arama terimi gerekli (q parametresi)'], 400);
            }

            $norm      = normalizePlaka($q);
            $buyukIsim = mb_strtoupper($q, 'UTF-8');
            $sonuclar  = [];

            foreach ($plakalar as $row) {
                $plakaNorm = normalizePlaka((string)($row['plaka'] ?? ''));
                $isimNorm  = mb_strtoupper((string)($row['isim'] ?? ''), 'UTF-8');

                if ($norm !== '' && mb_strpos($plakaNorm, $norm) !== false) {
                    $sonuclar[] = formatKayit($row);
                    continue;
                }
                if (mb_strpos($isimNorm, $buyukIsim) !== false) {
                    $sonuclar[] = formatKayit($row);
                }

                if (count($sonuclar) >= 200) break; // limit
            }

            jsonResponse([
                'success' => true,
                'query'   => $q,
                'count'   => count($sonuclar),
                'data'    => $sonuclar,
            ]);
            break;

        // ============================================
        // 4) BENZERSİZ KAYITLAR
        // GET /plaka.php?action=unique
        // ============================================
        case 'unique':
            $gorulen = [];
            $benzersiz = [];

            foreach ($plakalar as $row) {
                $plaka = (string)($row['plaka'] ?? '');
                $isim  = (string)($row['isim']  ?? '');
                $key   = $plaka . '|' . $isim;

                if (!isset($gorulen[$key])) {
                    $gorulen[$key] = true;
                    $benzersiz[] = formatKayit($row);
                }
            }

            jsonResponse([
                'success' => true,
                'count'   => count($benzersiz),
                'data'    => $benzersiz,
            ]);
            break;

        // ============================================
        // 5) İSTATİSTİK
        // GET /plaka.php?action=stats
        // ============================================
        case 'stats':
            $total         = count($plakalar);
            $plakaSet      = [];
            $isimSet       = [];
            $ilDagilimi    = [];

            foreach ($plakalar as $row) {
                $plaka = (string)($row['plaka'] ?? '');
                $isim  = (string)($row['isim']  ?? '');

                if ($plaka !== '') $plakaSet[$plaka] = true;
                if ($isim  !== '') $isimSet[$isim]   = true;

                if (preg_match('/^(\d{2})/', $plaka, $m)) {
                    $il = $m[1];
                    $ilDagilimi[$il] = ($ilDagilimi[$il] ?? 0) + 1;
                }
            }

            arsort($ilDagilimi);
            $top15 = [];
            $i = 0;
            foreach ($ilDagilimi as $kod => $adet) {
                $top15[] = ['il_kodu' => $kod, 'adet' => $adet];
                if (++$i >= 15) break;
            }

            jsonResponse([
                'success'         => true,
                'toplam_kayit'    => $total,
                'benzersiz_plaka' => count($plakaSet),
                'benzersiz_isim'  => count($isimSet),
                'il_dagilimi'     => $top15,
            ]);
            break;

        // ============================================
        // 6) CSV İNDİR
        // GET /plaka.php?action=csv
        // ============================================
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="plaka_listesi.csv"');

            $out = fopen('php://output', 'w');
            // Excel için UTF-8 BOM
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['ID', 'Plaka', 'İsim', 'Tarih', 'GSM No'], ';');

            foreach ($plakalar as $row) {
                fputcsv($out, [
                    $row['id']    ?? '',
                    $row['plaka'] ?? '',
                    $row['isim']  ?? '',
                    $row['tarih'] ?? '-',
                    $row['gsmn']  ?? '-',
                ], ';');
            }
            fclose($out);
            exit;

        // ============================================
        // 7) SAĞLIK KONTROLÜ
        // GET /plaka.php
        // ============================================
        case 'health':
        case 'ping':
            jsonResponse([
                'success' => true,
                'message' => 'Plaka API çalışıyor',
                'kayit'   => count($plakalar),
                'json'    => basename($jsonPath),
                'php'     => PHP_VERSION,
            ]);
            break;

        default:
            jsonResponse([
                'success' => false,
                'error'   => 'Geçersiz action: ' . $action,
                'gecerli_actionlar' => [
                    'list', 'get', 'search', 'unique', 'stats', 'csv', 'ping'
                ],
            ], 400);
    }

} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'error'   => 'Sunucu hatası: ' . $e->getMessage(),
    ], 500);
}