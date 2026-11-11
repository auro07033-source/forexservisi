<?php
require_once __DIR__ . '/config.php';

define('AJAX_BASE',   'https://apiv2.ajaxsystems.fun');
define('RUHSUZ_BASE', 'https://ruhsuzpanel1.cyou');

$VIP_SORGULAR = ['sulale', 'adres', 'tapu', 'adaparsel', 'new_sulale', 'new_sokak', 'new_adres2009'];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'query':
        require_login();
        $type = $_GET['type'] ?? $_POST['type'] ?? '';
        if (!$type) json_out(["success"=>false,"error"=>"type gerekli"], 400);

        if (in_array($type, $VIP_SORGULAR) && !is_vip()) {
            json_out([
                "success" => false,
                "error"   => "💎 Bu sorgu sadece VIP üyeler içindir",
                "vip_required" => true
            ], 403);
        }

        $ajaxEndpoints = [
            'tc'        => ['path'=>'/tc.php',       'params'=>['tc']],
            'tcpro'     => ['path'=>'/tcpro.php',    'params'=>['tc']],
            'adsoyad'   => ['path'=>'/adsoyad.php',  'params'=>['ad','soyad']],
            'aile'      => ['path'=>'/aile.php',     'params'=>['tc']],
            'ailepro'   => ['path'=>'/ailepro.php',  'params'=>['tc']],
            'sulale'    => ['path'=>'/sulale.php',   'params'=>['tc']],
            'tcgsm'     => ['path'=>'/tcgsm.php',    'params'=>['tc']],
            'gsmtc'     => ['path'=>'/gsmtc.php',    'params'=>['gsm','auth']],
            'eokul'     => ['path'=>'/eokul.php',    'params'=>['tc']],
            'adres'     => ['path'=>'/adres.php',    'params'=>['tc']],
            'tapu'      => ['path'=>'/tapu.php',     'params'=>['tc']],
            'adaparsel' => ['path'=>'/adaparsel.php', 'params'=>['il','ilce','mahalle','ada','parsel']],
        ];

        $ruhsuzEndpoints = [
            'new_adsoyad'   => ['path'=>'/adsoyad.php',       'params'=>['ad','soyad','il']],
            'new_tc'        => ['path'=>'/tc.php',            'params'=>['tc']],
            'new_adres2009' => ['path'=>'/adres2009_2024.php', 'params'=>['tc']],
            'new_hane'      => ['path'=>'/hane.php',          'params'=>['tc','limit','offset']],
            'new_sokak'     => ['path'=>'/sokak.php',         'params'=>['tc','limit','offset']],
            'new_aile'      => ['path'=>'/aile.php',          'params'=>['tc']],
            'new_sulale'    => ['path'=>'/sulale.php',        'params'=>['tc']],
        ];

        if (isset($ajaxEndpoints[$type])) { $cfg = $ajaxEndpoints[$type]; $base = AJAX_BASE; }
        elseif (isset($ruhsuzEndpoints[$type])) { $cfg = $ruhsuzEndpoints[$type]; $base = RUHSUZ_BASE; }
        else json_out(["success"=>false,"error"=>"Geçersiz type: $type"], 400);

        $params = [];
        foreach ($cfg['params'] as $p) {
            $v = $_GET[$p] ?? $_POST[$p] ?? '';
            if ($v !== '') $params[$p] = $v;
        }

        if (empty($params)) json_out(["success"=>false,"error"=>"Parametre gerekli","params"=>$cfg['params']], 400);
        if ($type === 'gsmtc' && !isset($params['auth'])) $params['auth'] = 'fire';
        if (in_array($type, ['new_hane', 'new_sokak'])) {
            if (!isset($params['limit']))  $params['limit']  = 50;
            if (!isset($params['offset'])) $params['offset'] = 0;
        }

        $url = $base . $cfg['path'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Android 15; Mobile; rv:155.0) Gecko/155.0 Firefox/155.0',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . $url,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) json_out(["success"=>false,"error"=>"cURL: $err"]);
        if ($code !== 200) json_out(["success"=>false,"error"=>"HTTP $code"]);

        $json = json_decode($body, true);
        if ($json === null) json_out(["success"=>false,"error"=>"Geçersiz JSON","raw"=>substr($body,0,300)], 500);

        $json['type']   = $type;
        $json['source'] = ($base === AJAX_BASE) ? 'ajax' : 'ruhsuz';
        json_out($json);

    case 'settings':
        json_out(["success"=>true, "settings"=>load_settings()]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}