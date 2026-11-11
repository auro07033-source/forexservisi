<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'messages':
        require_login();
        $messages = read_json(CHAT_FILE, []);
        $messages = array_slice($messages, -200);
        $out = [];
        foreach ($messages as $m) {
            $sender = find_user_by_id($m['user_id'] ?? '');
            $out[] = [
                'id'       => $m['id'],
                'user_id'  => $m['user_id'],
                'username' => $sender['username'] ?? 'Bilinmeyen',
                'avatar'   => avatar_url($sender ?: []),
                'rank'     => $sender['rank'] ?? 'Üye',
                'verified' => !empty($sender['verified']),
                'text'     => $m['text'],
                'ts'       => $m['ts'],
                'time'     => date('H:i', $m['ts']),
            ];
        }
        json_out(["success"=>true, "messages"=>$out]);

    case 'send':
        require_login();
        $me = current_user();
        $text = trim($_POST['text'] ?? '');
        if ($text === '') json_out(["success"=>false,"error"=>"Boş mesaj"]);
        if (mb_strlen($text) > 500) json_out(["success"=>false,"error"=>"Mesaj çok uzun"]);

        $messages = read_json(CHAT_FILE, []);
        $messages[] = [
            'id'      => gen_id(),
            'user_id' => $me['id'],
            'text'    => $text,
            'ts'      => time(),
        ];
        if (count($messages) > 1000) $messages = array_slice($messages, -1000);
        write_json(CHAT_FILE, $messages);
        json_out(["success"=>true, "message"=>"Gönderildi"]);

    case 'clear':
        require_admin();
        write_json(CHAT_FILE, []);
        json_out(["success"=>true, "message"=>"Sohbet temizlendi"]);

    default:
        json_out(["success"=>false,"error"=>"Bilinmeyen action"], 404);
}