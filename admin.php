<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'admin_login':
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($user !== ADMIN_USER) json_out(["success"=>false,"error"=>"Kullanıcı adı yanlış"], 401);
        if ($pass !== ADMIN_PASS) json_out(["success"=>false,"error"=>"Şifre yanlış"], 401);

        $admin = find_user(ADMIN_USER);
        $users = load_users();
        if (!$admin) {
            $admin = [
                'id'=>gen_id(),'username'=>ADMIN_USER,'email'=>'admin@forex.local',
                'password'=>password_hash($pass, PASSWORD_DEFAULT),
                'avatar'=>DEFAULT_AVATAR,'bio'=>'Yönetici','birthday'=>'','horoscope'=>'',
                'city'=>'','job'=>'','instagram'=>'','telegram'=>'','phone'=>'','website'=>'',
                'gender'=>'','notes'=>'','rank'=>'Baş Admin','is_vip'=>true,'verified'=>true,
                'banned'=>false,'is_admin'=>true,'created_at'=>now_iso(),'last_seen'=>now_iso(),
                'query_count'=>0,'login_count'=>1,
            ];
            $users[] = $admin;
            save_users($users);
        } else {
            foreach ($users as &$u) {
                if ($u['id'] === $admin['id']) {
                    $u['password'] = password_hash($pass, PASSWORD_DEFAULT);
                    $u['is_admin'] = true;
                    $u['is_vip']   = true;
                    $u['login_count'] = ($u['login_count'] ?? 0) + 1;
                }
            }
            save_users($users);
        }

        $_SESSION['user_id'] = $admin['id'];
        add_log('admin_login', $user);
        json_out(["success"=>true, "message"=>"Admin girişi başarılı", "user"=>public_user($admin)]);

    case 'admin_check':
        $u = current_user();
        if (!$u || empty($u['is_admin'])) json_out(["success"=>true, "logged"=>false]);
        json_out(["success"=>true, "logged"=>true, "user"=>public_user($u)]);

    case 'list_users':
        require_admin();
        $users = load_users();
        $out = [];
        foreach ($users as $u) $out[] = public_user($u);
        json_out(["success"=>true, "users"=>$out, "total"=>count($out)]);

    case 'user_detail':
        require_admin();
        $id = $_POST['id'] ?? $_GET['id'] ?? '';
        $u = find_user_by_id($id);
        if (!$u) json_out(["success"=>false,"error"=>"Kullanıcı yok"], 404);
        $chat = read_json(CHAT_FILE, []);
        $userMsgs = array_values(array_filter($chat, fn($m) => ($m['user_id'] ?? '') === $id));
        $aiAll = read_json(AI_CHAT_FILE, []);
        $userAi = array_values(array_filter($aiAll, fn($m) => ($m['user_id'] ?? '') === $id));
        json_out([
            "success" => true,
            "user"    => public_user($u),
            "messages"=> count($userMsgs),
            "ai_messages" => count($userAi),
            "last_messages" => array_slice($userMsgs, -5),
        ]);

    case 'ban':
        require_admin();
        $id = $_POST['id'] ?? '';
        $ban = (int)($_POST['ban'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if (!empty($u['is_admin'])) json_out(["success"=>false,"error"=>"Admin banlanamaz"]);
                $u['banned'] = (bool)$ban;
            }
        }
        save_users($users);
        add_log($ban ? 'ban' : 'unban', $id);
        json_out(["success"=>true, "message"=>$ban ? "Banlandı" : "Ban kaldırıldı"]);

    case 'verify':
        require_admin();
        $id = $_POST['id'] ?? '';
        $v = (int)($_POST['verified'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) if ($u['id'] === $id) $u['verified'] = (bool)$v;
        save_users($users);
        add_log('verify', "$id => $v");
        json_out(["success"=>true, "message"=>$v ? "✅ Tik verildi" : "Tik kaldırıldı"]);

    case 'vip':
        require_admin();
        $id = $_POST['id'] ?? '';
        $vip = (int)($_POST['vip'] ?? 1);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['is_vip'] = (bool)$vip;
                if ($vip) $u['rank'] = 'VIP';
                elseif ($u['rank'] === 'VIP') $u['rank'] = 'Üye';
            }
        }
        save_users($users);
        add_log($vip ? 'vip_add' : 'vip_remove', $id);
        json_out(["success"=>true, "message"=>$vip ? "💎 VIP verildi" : "VIP kaldırıldı"]);

    case 'delete_user':
        require_admin();
        $id = $_POST['id'] ?? '';
        $users = load_users();
        $new = [];
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                if (!empty($u['is_admin'])) json_out(["success"=>false,"error"=>"Admin silinemez"]);
                continue;
            }
            $new[] = $u;
        }
        save_users($new);
        add_log('delete_user', $id);
        json_out(["success"=>true, "message"=>"Hesap silindi"]);

    case 'set_rank':
        require_admin();
        $id = $_POST['id'] ?? '';
        $rank = trim($_POST['rank'] ?? '');
        if ($rank === '') json_out(["success"=>false,"error"=>"Rütbe gerekli"]);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['rank'] = mb_substr($rank, 0, 30);
                $u['is_vip'] = ($rank === 'VIP');
            }
        }
        save_users($users);
        add_log('set_rank', "$id => $rank");
        json_out(["success"=>true, "message"=>"Rütbe ayarlandı"]);

    case 'edit_user':
        require_admin();
        $id = $_POST['id'] ?? '';
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                if (isset($_POST['email']))     $u['email']     = trim($_POST['email']);
                if (isset($_POST['bio']))       $u['bio']       = mb_substr(trim($_POST['bio']), 0, 200);
                if (isset($_POST['city']))      $u['city']      = trim($_POST['city']);
                if (isset($_POST['job']))       $u['job']       = trim($_POST['job']);
                if (isset($_POST['phone']))     $u['phone']     = trim($_POST['phone']);
                if (isset($_POST['website']))   $u['website']   = trim($_POST['website']);
                if (isset($_POST['instagram'])) $u['instagram'] = trim($_POST['instagram']);
                if (isset($_POST['telegram']))  $u['telegram']  = trim($_POST['telegram']);
                if (isset($_POST['gender']))    $u['gender']    = trim($_POST['gender']);
                if (isset($_POST['notes']))     $u['notes']     = mb_substr(trim($_POST['notes']), 0, 500);
                if (isset($_POST['avatar']))    $u['avatar']    = trim($_POST['avatar']);
                if (isset($_POST['birthday'])) {
                    $u['birthday'] = trim($_POST['birthday']);
                    $u['horoscope'] = burcHesapla($u['birthday']);
                }
            }
        }
        save_users($users);
        add_log('edit_user', $id);
        json_out(["success"=>true, "message"=>"Kullanıcı güncellendi"]);

    case 'reset_password':
        require_admin();
        $id = $_POST['id'] ?? '';
        $newPass = trim($_POST['password'] ?? '');
        if (strlen($newPass) < 4) json_out(["success"=>false,"error"=>"Şifre en az 4 karakter"]);
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $id) $u['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        }
        save_users($users);
        add_log('reset_password', $id);
        json_out(["success"=>true, "message"=>"Şifre sıfırlandı"]);

    case 'stats':
        require_admin();
        $users = load_users();
        $messages = read_json(CHAT_FILE, []);
        $aiMsgs   = read_json(AI_CHAT_FILE, []);
        $online = 0; $verified = 0; $banned = 0; $vip = 0; $admin = 0; $totalQueries = 0;
        foreach ($users as $u) {
            if (is_online($u)) $online++;
            if (!empty($u['verified'])) $verified++;
            if (!empty($u['banned'])) $banned++;
            if (!empty($u['is_vip']) || ($u['rank'] ?? '') === 'VIP') $vip++;
            if (!empty($u['is_admin'])) $admin++;
            $totalQueries += (int)($u['query_count'] ?? 0);
        }
        json_out([
            "success"     => true,
            "total"       => count($users),
            "online"      => $online,
            "verified"    => $verified,
            "banned"      => $banned,
            "vip"         => $vip,
            "admin"       => $admin,
            "messages"    => count($messages),
            "ai_messages" => count($aiMsgs),
            "queries"     => $totalQueries,
        ]);

    // ═══════════ BRUTE LOG ═══════════

    case 'brute_log_list':
        require_admin();
        $logs = read_json(BRUTE_LOG_FILE, []);
        $logs = array_slice(array_reverse($logs), 0, 200);
        json_out(["success"=>true, "logs"=>$logs]);

    case 'brute_log_clear':
        require_admin();
        write_json(BRUTE_LOG_FILE, []);
        json_out(["success"=>true, "message"=>"Brute logu temizlendi"]);

    case 'brute_log_delete':
        require_admin();
        $id = $_POST['id'] ?? '';
        $logs = read_json(BRUTE_LOG_FILE, []);
        $logs = array_values(array_filter($logs, fn($l) => ($l['id'] ?? '') !== $id));
        write_json(BRUTE_LOG_FILE, $logs);
        json_out(["success"=>true, "message"=>"Silindi"]);

    case 'settings':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $s = load_settings();
            if (isset($_POST['theme']))         $s['theme'] = $_POST['theme'];
            if (isset($_POST['announcement']))  $s['announcement'] = $_POST['announcement'];
            if (isset($_POST['maintenance']))   $s['maintenance'] = (bool)$_POST['maintenance'];
            if (isset($_POST['ai_enabled']))    $s['ai_enabled'] = (bool)$_POST['ai_enabled'];
            if (isset($_POST['register_open'])) $s['register_open'] = (bool)$_POST['register_open'];
            if (isset($_POST['site_title']))    $s['site_title'] = trim($_POST['site_title']);
            if (isset($_POST['ai_model']))      $s['ai_model'] = trim($_POST['ai_model']);
            if (isset($_POST['brute_enabled'])) $s['brute_enabled'] = (bool)$_POST['brute_enabled'];
            save_settings($s);
            add_log('settings_update');
            json_out(["success"=>true, "message"=>"Ayarlar güncellendi", "settings"=>$s]);
        }
        json_out(["success"=>true, "settings"=>load_settings()]);

    case 'clear_chat':
        require_admin();
        write_json(CHAT_FILE, []);
        add_log('clear_chat');
        json_out(["success"=>true, "message"=>"Sohbet temizlendi"]);

    case 'clear_ai':
        require_admin();
        write_json(AI_CHAT_FILE, []);
        add_log('clear_ai');
        json_out(["success"=>true, "message"=>"AI geçmişi temizlendi"]);

    case 'logs':
        require_admin();
        $logs = read_json(LOG_FILE, []);
        $logs = array_slice(array_reverse($logs), 0, 200);
        json_out(["success"=>true, "logs"=>$logs]);

    case 'broadcast':
        require_admin();
        $msg = trim($_POST['message'] ?? '');
        if ($msg === '') json_out(["success"=>false,"error"=>"Mesaj gerekli"]);
        $users = load_users();
        $now = time();
        foreach ($users as &$u) {
            if (!empty($u['is_admin'])) continue;
            $u['inbox'] = $u['inbox'] ?? [];
            $u['inbox'][] = ['id'=>gen_id(), 'from'=>'admin', 'text'=>$msg, 'ts'=>$now, 'read'=>false];
            if (count($u['inbox']) > 50) $u['inbox'] = array_slice($u['inbox'], -50);
        }
        save_users($users);
        add_log('broadcast', $msg);
        json_out(["success"=>true, "message"=>"Duyuru gönderildi"]);

    case 'export_users':
        require_admin();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Ymd_His') . '.json"');
        echo json_encode(load_users(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}