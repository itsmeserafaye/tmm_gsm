<?php
require_once __DIR__ . '/env.php';
tmm_load_env_default();

function recaptcha_config(mysqli $db): array {
  $siteKey = '';
  $secretKey = '';

  $sql = "SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('recaptcha_site_key','recaptcha_secret_key')";
  $res = $db->query($sql);
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $k = (string)($row['setting_key'] ?? '');
      $v = (string)($row['setting_value'] ?? '');
      if ($k === 'recaptcha_site_key') $siteKey = $v;
      if ($k === 'recaptcha_secret_key') $secretKey = $v;
    }
    $res->free();
  }

  $envSite = getenv('RECAPTCHA_SITE_KEY');
  $envSecret = getenv('RECAPTCHA_SECRET_KEY');
  if (is_string($envSite) && $envSite !== '') $siteKey = $envSite;
  if (is_string($envSecret) && $envSecret !== '') $secretKey = $envSecret;

  return ['site_key' => $siteKey, 'secret_key' => $secretKey];
}

function recaptcha_verify(string $secretKey, string $token, string $remoteIp = ''): array {
  $secretKey = trim($secretKey);
  $token = trim($token);
  if ($secretKey === '' || $token === '') return ['ok' => false, 'error' => 'missing_secret_or_token'];

  $payload = [
    'secret' => $secretKey,
    'response' => $token,
  ];
  if ($remoteIp !== '') $payload['remoteip'] = $remoteIp;

  $raw = false;
  $httpCode = 0;
  $transport = '';
  $curlErr = '';

  if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($payload),
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 8,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: TMM/1.0',
      ],
    ]);
    $raw = curl_exec($ch);
    $curlErr = (string)curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ch = null;
    $transport = 'curl';
  }

  if ($raw === false) {
    $postData = http_build_query($payload);
    $opts = [
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: TMM/1.0\r\n",
        'content' => $postData,
        'timeout' => 8,
      ],
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    $transport = 'fopen';
  }

  if ($raw === false || (is_int($httpCode) && $httpCode !== 0 && ($httpCode < 200 || $httpCode >= 300))) {
    return ['ok' => false, 'error' => 'request_failed', 'transport' => $transport, 'http_code' => $httpCode, 'curl_error' => $curlErr];
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) return ['ok' => false, 'error' => 'bad_response'];

  $success = (bool)($json['success'] ?? false);
  return [
    'ok' => $success,
    'error_codes' => $json['error-codes'] ?? [],
    'score' => $json['score'] ?? null,
    'action' => $json['action'] ?? null,
    'challenge_ts' => $json['challenge_ts'] ?? null,
    'hostname' => $json['hostname'] ?? null,
  ];
}
