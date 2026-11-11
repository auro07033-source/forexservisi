<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'chat':
        require_login();
        $s = load_settings();
        if (empty($s['ai_enabled'])) json_out(["success"=>false,"error"=>"AI şu an devre dışı"]);

        $q = trim($_POST['q'] ?? $_GET['q'] ?? '');
        if (!$q) json_out(["success"=>false,"error"=>"Mesaj gerekli"]);

        $me = current_user();

        $komutCevap = panelKomutIsle($q, $me);
        if ($komutCevap !== null) {
            aiMesajKaydet($me['id'], $q, $komutCevap, true);
            json_out(["success"=>true, "response"=>$komutCevap, "command"=>true]);
        }

        $model = $s['ai_model'] ?? 'pollinations';
        $url = AI_API . '?' . http_build_query([
            'key'       => AI_KEY,
            'device_id' => AI_DEVICE,
            'model'     => $model,
            'q'         => $q,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'ForexPanel/1.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $code !== 200) {
            $msg = $err ? "cURL: $err" : "HTTP $code";
            if ($code === 503) $msg = "AI servisi şu an uykuda (503), birkaç saniye sonra tekrar dene.";
            json_out(["success"=>false,"error"=>$msg], 500);
        }

        $json = json_decode($body, true);
        if (!$json || empty($json['response'])) {
            json_out(["success"=>false,"error"=>"AI yanıtı geçersiz","raw"=>substr($body,0,200)], 500);
        }

        $cevap = $json['response'];
        aiMesajKaydet($me['id'], $q, $cevap, false);
        json_out([
            "success"  => true,
            "response" => $cevap,
            "model"    => $json['model'] ?? $model,
            "sure"     => $json['sure'] ?? null,
        ]);

    case 'history':
        require_login();
        $me = current_user();
        $all = read_json(AI_CHAT_FILE, []);
        $mine = array_values(array_filter($all, fn($m) => $m['user_id'] === $me['id']));
        $mine = array_slice($mine, -50);
        json_out(["success"=>true, "messages"=>$mine]);

    case 'clear':
        require_login();
        $me = current_user();
        $all = read_json(AI_CHAT_FILE, []);
        $new = array_values(array_filter($all, fn($m) => $m['user_id'] !== $me['id']));
        write_json(AI_CHAT_FILE, $new);
        json_out(["success"=>true, "message"=>"AI geçmişi temizlendi"]);

    case 'settings':
        json_out(["success"=>true, "settings"=>load_settings()]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}

function aiMesajKaydet($user_id, $soru, $cevap, $komut) {
    $all = read_json(AI_CHAT_FILE, []);
    $all[] = ['id'=>gen_id(),'user_id'=>$user_id,'soru'=>$soru,'cevap'=>$cevap,'komut'=>$komut,'ts'=>time()];
    if (count($all) > 2000) $all = array_slice($all, -2000);
    write_json(AI_CHAT_FILE, $all);
}

function panelKomutIsle($q, $me) {
    if (empty($me['is_admin'])) return null;
    $settings = load_settings();

    if (preg_match('/(gece modu|karanl[ıi]k|dark mode)/i', $q)) {
        if (preg_match('/(aç|ac|aktif|enable|yap)/i', $q)) { $settings['theme']='dark'; save_settings($settings); return "✅ Gece moduna alındı."; }
        if (preg_match('/(kapat|kapa|disable|kald[ıi]r)/i', $q)) { $settings['theme']='light'; save_settings($settings); return "✅ Gündüz moduna alındı."; }
    }
    if (preg_match('/duyuru (ayarla|yaz|ekle|koy)[:\s]+(.+)/i', $q, $m)) {
        $settings['announcement'] = trim($m[2]); save_settings($settings);
        return "✅ Duyuru güncellendi:\n\n".trim($m[2]);
    }
    if (preg_match('/duyuru (temizle|sil|kald[ıi]r)/i', $q)) {
        $settings['announcement']=''; save_settings($settings); return "✅ Duyuru temizlendi.";
    }
    if (preg_match('/bak[ıi]m (modu )?(aç|ac|aktif|yap)/i', $q)) {
        $settings['maintenance']=true; save_settings($settings); return "🔧 Bakım modu AKTİF.";
    }
    if (preg_match('/bak[ıi]m (modu )?(kapat|kapa|kald[ıi]r|disable)/i', $q)) {
        $settings['maintenance']=false; save_settings($settings); return "✅ Bakım modu KAPALI.";
    }
    if (preg_match('/ai (kapat|kapa|disable|durdur)/i', $q)) {
        $settings['ai_enabled']=false; save_settings($settings); return "✅ AI kapatıldı.";
    }
    if (preg_match('/ai (aç|ac|aktif|enable|ba[şs]lat)/i', $q)) {
        $settings['ai_enabled']=true; save_settings($settings); return "✅ AI açıldı.";
    }
    if (preg_match('/(istatistik|kaç kullan[ıi]c[ıi]|stats)/i', $q)) {
        $users = load_users();
        $total=count($users); $online=0;$vip=0;$banned=0;$admin=0;
        foreach ($users as $u) {
            if (is_online($u)) $online++;
            if (!empty($u['is_vip'])||($u['rank']??'')==='VIP') $vip++;
            if (!empty($u['banned'])) $banned++;
            if (!empty($u['is_admin'])) $admin++;
        }
        $msgs=count(read_json(CHAT_FILE,[]));
        return "📊 **İstatistik**\n\n👥 Toplam: {$total}\n🟢 Çevrimiçi: {$online}\n💎 VIP: {$vip}\n🚫 Banlı: {$banned}\n🛡️ Admin: {$admin}\n💬 Mesaj: {$msgs}";
    }
    if (preg_match('/banla\s+(\S+)/i', $q, $m)) {
        $t=find_user(trim($m[1])); if(!$t) return "❌ Kullanıcı yok: ".$m[1];
        if (!empty($t['is_admin'])) return "❌ Admin banlanamaz.";
        $users=load_users(); foreach($users as &$u) if($u['id']===$t['id']) $u['banned']=true; save_users($users);
        return "✅ **".$t['username']."** banlandı.";
    }
    if (preg_match('/ban\s+kald[ıi]r\s+(\S+)/i', $q, $m)) {
        $t=find_user(trim($m[1])); if(!$t) return "❌ Kullanıcı yok.";
        $users=load_users(); foreach($users as &$u) if($u['id']===$t['id']) $u['banned']=false; save_users($users);
        return "✅ **".$t['username']."** banı kaldırıldı.";
    }
    if (preg_match('/vip\s+ver\s+(\S+)/i', $q, $m)) {
        $t=find_user(trim($m[1])); if(!$t) return "❌ Kullanıcı yok.";
        $users=load_users(); foreach($users as &$u) if($u['id']===$t['id']){$u['rank']='VIP';$u['is_vip']=true;} save_users($users);
        return "💎 **".$t['username']."** artık VIP.";
    }
    if (preg_match('/vip\s+(kald[ıi]r|al)\s+(\S+)/i', $q, $m)) {
        $t=find_user(trim($m[2])); if(!$t) return "❌ Kullanıcı yok.";
        $users=load_users(); foreach($users as &$u) if($u['id']===$t['id']){$u['rank']='Üye';$u['is_vip']=false;} save_users($users);
        return "✅ **".$t['username']."** VIP kaldırıldı.";
    }
    if (preg_match('/sil\s+(\S+)/i', $q, $m)) {
        $t=find_user(trim($m[1])); if(!$t) return "❌ Kullanıcı yok.";
        if (!empty($t['is_admin'])) return "❌ Admin silinemez.";
        $users=load_users(); $new=array_values(array_filter($users, fn($u)=>$u['id']!==$t['id'])); save_users($new);
        return "🗑️ **".$t['username']."** silindi.";
    }
    if (preg_match('/(yard[ıi]m|komutlar|help)/i', $q)) {
        return "🤖 **Komutlar**\n\n🌙 `gece modu aç/kapat`\n📢 `duyuru ayarla: mesaj` / `duyuru temizle`\n🔧 `bakım modu aç/kapat`\n🤖 `ai aç/kapat`\n📊 `istatistik`\n🚫 `banla USER` / `ban kaldır USER`\n💎 `vip ver USER` / `vip kaldır USER`\n🗑️ `sil USER`";
    }
    return null;
}