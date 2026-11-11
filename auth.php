<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'register':
        $s = load_settings();
        if (!($s['register_open'] ?? true)) json_out(["success"=>false,"error"=>"Kayıtlar geçici olarak kapalı"]);

        $username = sanitize_username($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email    = trim($_POST['email'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');

        if (strlen($username) < 3) json_out(["success"=>false,"error"=>"Kullanıcı adı en az 3 karakter"]);
        if (strlen($password) < 4) json_out(["success"=>false,"error"=>"Şifre en az 4 karakter"]);
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(["success"=>false,"error"=>"Geçersiz email"]);
        if (find_user($username)) json_out(["success"=>false,"error"=>"Bu kullanıcı adı alınmış"]);

        $users = load_users();
        $newUser = [
            'id'=>gen_id(),'username'=>$username,'email'=>$email,
            'password'=>password_hash($password, PASSWORD_DEFAULT),
            'avatar'=>DEFAULT_AVATAR,'bio'=>'Forex Sorgulama Hizmeti üyesi',
            'birthday'=>$birthday,'horoscope'=>$birthday?burcHesapla($birthday):'',
            'city'=>'','job'=>'','instagram'=>'','telegram'=>'','phone'=>'','website'=>'',
            'gender'=>'','notes'=>'','rank'=>'Üye','is_vip'=>false,'verified'=>false,
            'banned'=>false,'is_admin'=>false,'created_at'=>now_iso(),'last_seen'=>now_iso(),
            'query_count'=>0,'login_count'=>1,
        ];
        $users[] = $newUser;
        save_users($users);
        $_SESSION['user_id'] = $newUser['id'];
        json_out(["success"=>true, "message"=>"Kayıt başarılı", "user"=>public_user($newUser)]);

    case 'login':
        $username = sanitize_username($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = find_user($username);
        if (!$user) json_out(["success"=>false,"error"=>"Kullanıcı bulunamadı"], 401);
        if (!empty($user['banned'])) json_out(["success"=>false,"error"=>"Hesabınız askıya alınmış"], 403);
        if (!password_verify($password, $user['password'])) json_out(["success"=>false,"error"=>"Şifre yanlış"], 401);

        $_SESSION['user_id'] = $user['id'];
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $user['id']) {
                $u['last_seen'] = now_iso();
                $u['login_count'] = ($u['login_count'] ?? 0) + 1;
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Giriş başarılı", "user"=>public_user($user)]);

    case 'logout':
        session_destroy();
        json_out(["success"=>true, "message"=>"Çıkış yapıldı"]);

    case 'check':
        $u = current_user();
        if (!$u) json_out(["success"=>true, "logged"=>false]);
        if (!empty($u['banned'])) json_out(["success"=>false, "logged"=>false, "banned"=>true]);
        $users = load_users();
        foreach ($users as &$uu) if ($uu['id'] === $u['id']) $uu['last_seen'] = now_iso();
        save_users($users);
        json_out(["success"=>true, "logged"=>true, "user"=>public_user($u)]);

    case 'profile':
        require_login();
        $target = $_GET['user'] ?? '';
        $u = find_user($target) ?: current_user();
        if (!$u) json_out(["success"=>false,"error"=>"Kullanıcı bulunamadı"], 404);
        json_out(["success"=>true, "user"=>public_user($u)]);

    case 'update_profile':
        require_login();
        $me = current_user();
        $users = load_users();
        $newBday = trim($_POST['birthday'] ?? ($me['birthday'] ?? ''));
        foreach ($users as &$u) {
            if ($u['id'] === $me['id']) {
                $u['bio']       = mb_substr(trim($_POST['bio'] ?? ''), 0, 200);
                $u['email']     = trim($_POST['email'] ?? $me['email']);
                $u['birthday']  = $newBday;
                $u['horoscope'] = $newBday ? burcHesapla($newBday) : ($u['horoscope'] ?? '');
                $u['city']      = trim($_POST['city'] ?? ($me['city'] ?? ''));
                $u['job']       = trim($_POST['job'] ?? ($me['job'] ?? ''));
                $u['instagram'] = trim($_POST['instagram'] ?? ($me['instagram'] ?? ''));
                $u['telegram']  = trim($_POST['telegram'] ?? ($me['telegram'] ?? ''));
                $u['phone']     = trim($_POST['phone'] ?? ($me['phone'] ?? ''));
                $u['website']   = trim($_POST['website'] ?? ($me['website'] ?? ''));
                $u['gender']    = trim($_POST['gender'] ?? ($me['gender'] ?? ''));
                $av = trim($_POST['avatar'] ?? '');
                if ($av && filter_var($av, FILTER_VALIDATE_URL)) $u['avatar'] = $av;
            }
        }
        save_users($users);
        json_out(["success"=>true, "message"=>"Profil güncellendi", "user"=>public_user(current_user())]);

    case 'change_password':
        require_login();
        $me = current_user();
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($old, $me['password'])) json_out(["success"=>false,"error"=>"Mevcut şifre yanlış"]);
        if (strlen($new) < 4) json_out(["success"=>false,"error"=>"Yeni şifre en az 4 karakter"]);
        $users = load_users();
        foreach ($users as &$u) if ($u['id'] === $me['id']) $u['password'] = password_hash($new, PASSWORD_DEFAULT);
        save_users($users);
        json_out(["success"=>true, "message"=>"Şifre değiştirildi"]);

    case 'users':
        require_login();
        $users = load_users();
        $list = [];
        foreach ($users as $u) $list[] = public_user($u);
        json_out(["success"=>true, "users"=>$list, "total"=>count($list)]);

    case 'inbox':
        require_login();
        $me = current_user();
        $inbox = $me['inbox'] ?? [];
        $unread = count(array_filter($inbox, fn($m) => empty($m['read'])));
        json_out(["success"=>true, "inbox"=>array_slice($inbox, -30), "unread"=>$unread]);

    case 'read_inbox':
        require_login();
        $me = current_user();
        $users = load_users();
        foreach ($users as &$u) {
            if ($u['id'] === $me['id']) {
                foreach ($u['inbox'] as &$m) $m['read'] = true;
            }
        }
        save_users($users);
        json_out(["success"=>true]);

    case 'heartbeat':
        if (is_logged_in()) {
            $users = load_users();
            foreach ($users as &$u) if ($u['id'] === $_SESSION['user_id']) $u['last_seen'] = now_iso();
            save_users($users);
        }
        json_out(["success"=>true]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}