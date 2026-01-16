<?php
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

  $postData = http_build_query([
    'secret' => $secretKey,
    'response' => $token,
    'remoteip' => $remoteIp !== '' ? $remoteIp : null,
  ]);

  $opts = [
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => $postData,
      'timeout' => 8,
    ],
  ];
  $ctx = stream_context_create($opts);
  $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
  if ($raw === false) return ['ok' => false, 'error' => 'request_failed'];

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

