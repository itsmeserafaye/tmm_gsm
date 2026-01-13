<?php
require_once __DIR__ . '/db.php';

function tmm_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $val = (string)($row['setting_value'] ?? '');
  return $val !== '' ? $val : $default;
}

function tmm_http_get_json(string $url, int $timeoutSeconds = 10): array {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
    CURLOPT_TIMEOUT => $timeoutSeconds,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'User-Agent: TMM/1.0'
    ],
  ]);
  $body = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false || $code < 200 || $code >= 300) {
    return ['ok' => false, 'status' => $code, 'error' => $err ?: 'http_error'];
  }
  $json = json_decode($body, true);
  if (!is_array($json)) {
    return ['ok' => false, 'status' => $code, 'error' => 'invalid_json'];
  }
  return ['ok' => true, 'status' => $code, 'data' => $json];
}

function tmm_cache_get(mysqli $db, string $key): ?array {
  $now = date('Y-m-d H:i:s');
  $stmt = $db->prepare("SELECT payload FROM external_data_cache WHERE cache_key=? AND expires_at > ? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('ss', $key, $now);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) return null;
  $payload = (string)($row['payload'] ?? '');
  $data = json_decode($payload, true);
  return is_array($data) ? $data : null;
}

function tmm_cache_set(mysqli $db, string $key, array $data, int $ttlSeconds): bool {
  $payload = json_encode($data);
  if (!is_string($payload)) return false;
  $fetchedAt = date('Y-m-d H:i:s');
  $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
  $stmt = $db->prepare("REPLACE INTO external_data_cache(cache_key, payload, fetched_at, expires_at) VALUES (?,?,?,?)");
  if (!$stmt) return false;
  $stmt->bind_param('ssss', $key, $payload, $fetchedAt, $expiresAt);
  $ok = $stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

