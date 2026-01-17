<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');

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

function tmm_tomtom_api_key(mysqli $db): string {
  $env = getenv('TOMTOM_API_KEY');
  if ($env !== false) {
    $env = trim((string)$env);
    if ($env !== '') return $env;
  }
  return trim(tmm_setting($db, 'tomtom_api_key', ''));
}

function tmm_tomtom_geocode(mysqli $db, string $query): ?array {
  $query = trim($query);
  if ($query === '') return null;

  $cacheKey = 'tomtom:geocode:' . md5($query);
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;

  $key = tmm_tomtom_api_key($db);
  if ($key === '') return null;

  $url = 'https://api.tomtom.com/search/2/geocode/' . rawurlencode($query) . '.json?key=' . rawurlencode($key) . '&limit=1';
  $res = tmm_http_get_json($url, 12);
  if (!($res['ok'] ?? false) || !is_array($res['data'])) return null;
  $data = $res['data'];
  $results = $data['results'] ?? null;
  if (!is_array($results) || empty($results)) return null;
  $first = $results[0] ?? null;
  if (!is_array($first)) return null;
  $pos = $first['position'] ?? null;
  if (!is_array($pos)) return null;
  $lat = isset($pos['lat']) ? (float)$pos['lat'] : null;
  $lon = isset($pos['lon']) ? (float)$pos['lon'] : null;
  if (!is_float($lat) || !is_float($lon)) return null;

  $out = [
    'lat' => $lat,
    'lon' => $lon,
    'query' => $query,
    'name' => (string)($first['poi']['name'] ?? ''),
    'address' => (string)($first['address']['freeformAddress'] ?? ''),
  ];
  tmm_cache_set($db, $cacheKey, $out, 7 * 24 * 3600);
  return $out;
}

function tmm_tomtom_traffic_flow(mysqli $db, float $lat, float $lon): ?array {
  $cacheKey = 'tomtom:flow:' . $lat . ',' . $lon;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;

  $key = tmm_tomtom_api_key($db);
  if ($key === '') return null;

  $url = 'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?key=' . rawurlencode($key) .
    '&point=' . rawurlencode($lat . ',' . $lon);
  $res = tmm_http_get_json($url, 12);
  if (!($res['ok'] ?? false) || !is_array($res['data'])) return null;
  $data = $res['data'];
  tmm_cache_set($db, $cacheKey, $data, 120);
  return $data;
}

function tmm_tomtom_traffic_incidents(mysqli $db, float $lat, float $lon, float $radiusKm = 2.5): ?array {
  $radiusKm = max(0.5, min(10.0, $radiusKm));
  $cacheKey = 'tomtom:incidents:' . $lat . ',' . $lon . ':' . $radiusKm;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;

  $key = tmm_tomtom_api_key($db);
  if ($key === '') return null;

  $latDelta = $radiusKm / 111.0;
  $cos = cos(deg2rad($lat));
  $lonDelta = ($cos > 0.000001) ? ($radiusKm / (111.0 * $cos)) : $latDelta;

  $minLat = $lat - $latDelta;
  $maxLat = $lat + $latDelta;
  $minLon = $lon - $lonDelta;
  $maxLon = $lon + $lonDelta;

  $bbox = $minLon . ',' . $minLat . ',' . $maxLon . ',' . $maxLat;
  $url = 'https://api.tomtom.com/traffic/services/5/incidentDetails?key=' . rawurlencode($key) .
    '&bbox=' . rawurlencode($bbox) .
    '&timeValidityFilter=present&language=en-GB';
  $res = tmm_http_get_json($url, 12);
  if (!($res['ok'] ?? false) || !is_array($res['data'])) return null;
  $data = $res['data'];
  tmm_cache_set($db, $cacheKey, $data, 180);
  return $data;
}

