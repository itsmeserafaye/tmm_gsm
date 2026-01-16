<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/external_data.php';
require_once __DIR__ . '/../../../includes/env.php';

header('Content-Type: application/json');

function tmm_get_env(string $key, string $default = ''): string {
  $v = getenv($key);
  if ($v === false) return $default;
  return trim((string)$v);
}

function traffic_send(bool $ok, string $message, $data = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
  exit;
}

function traffic_bbox_from_center(float $lat, float $lon, float $km): array {
  $km = max(0.5, min(50.0, $km));
  $dLat = $km / 111.0;
  $cos = cos(deg2rad($lat));
  $dLon = ($cos > 0.00001) ? ($km / (111.0 * $cos)) : ($km / 111.0);
  $minLat = $lat - $dLat;
  $maxLat = $lat + $dLat;
  $minLon = $lon - $dLon;
  $maxLon = $lon + $dLon;
  return [$minLon, $minLat, $maxLon, $maxLat];
}

try {
  tmm_load_env(__DIR__ . '/../../../.env');
  require_login();
  $db = db();

  $provider = strtolower(trim((string)tmm_setting($db, 'traffic_provider', 'tomtom')));
  $lat = (float)tmm_setting($db, 'weather_lat', '14.5995');
  $lon = (float)tmm_setting($db, 'weather_lon', '120.9842');
  $label = (string)tmm_setting($db, 'weather_label', 'City Center');
  $bboxKm = (float)tmm_setting($db, 'traffic_bbox_km', '5');
  $ttl = (int)tmm_setting($db, 'traffic_cache_ttl', '300');
  $ttl = max(60, min(1800, $ttl));

  if ($provider !== 'tomtom') {
    traffic_send(true, 'Traffic provider not configured.', ['configured' => false, 'provider' => $provider]);
  }

  $apiKey = tmm_get_env('TOMTOM_API_KEY', (string)tmm_setting($db, 'tomtom_api_key', ''));
  if ($apiKey === '') {
    traffic_send(true, 'Traffic is not configured (missing TomTom API key).', ['configured' => false, 'provider' => 'tomtom']);
  }

  $cacheKey = 'traffic:tomtom:' . number_format($lat, 4, '.', '') . ',' . number_format($lon, 4, '.', '') . ':km=' . number_format($bboxKm, 1, '.', '');
  $cached = tmm_cache_get($db, $cacheKey);
  if ($cached && is_array($cached) && array_key_exists('congestion', $cached) && $cached['congestion'] !== null) {
    traffic_send(true, 'ok', $cached);
  }

  $flowUrl = 'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?point=' . urlencode($lat . ',' . $lon) . '&key=' . urlencode($apiKey);
  $flowRes = tmm_http_get_json($flowUrl, 10);
  $flow = ($flowRes['ok'] ?? false) ? ($flowRes['data'] ?? null) : null;

  $congestion = null;
  $currentSpeed = null;
  $freeFlowSpeed = null;
  if (is_array($flow) && isset($flow['flowSegmentData']) && is_array($flow['flowSegmentData'])) {
    $fsd = $flow['flowSegmentData'];
    $currentSpeed = isset($fsd['currentSpeed']) ? (float)$fsd['currentSpeed'] : null;
    $freeFlowSpeed = isset($fsd['freeFlowSpeed']) ? (float)$fsd['freeFlowSpeed'] : null;
    if ($currentSpeed !== null && $freeFlowSpeed !== null && $freeFlowSpeed > 0) {
      $ratio = $currentSpeed / $freeFlowSpeed;
      $congestion = max(0.0, min(1.0, 1.0 - $ratio));
    }
  }

  $bbox = traffic_bbox_from_center($lat, $lon, $bboxKm);
  $bboxStr = implode(',', array_map(function ($v) { return number_format((float)$v, 6, '.', ''); }, $bbox));
  $incUrl = 'https://api.tomtom.com/traffic/services/5/incidentDetails?bbox=' . urlencode($bboxStr) . '&categoryFilter=0,1,2,3,4,5,6,7,8,9,10,11,14&timeValidityFilter=present&key=' . urlencode($apiKey);
  $incRes = tmm_http_get_json($incUrl, 10);
  $inc = ($incRes['ok'] ?? false) ? ($incRes['data'] ?? null) : null;

  $incidentsCount = 0;
  $maxDelay = 0;
  if (is_array($inc) && isset($inc['incidents']) && is_array($inc['incidents'])) {
    $incidentsCount = count($inc['incidents']);
    foreach ($inc['incidents'] as $it) {
      if (!is_array($it)) continue;
      $delay = (int)($it['properties']['magnitudeOfDelay'] ?? 0);
      if ($delay > $maxDelay) $maxDelay = $delay;
    }
  }

  $payload = [
    'configured' => true,
    'provider' => 'tomtom',
    'label' => $label,
    'lat' => $lat,
    'lon' => $lon,
    'bbox_km' => $bboxKm,
    'congestion' => $congestion,
    'current_speed_kph' => $currentSpeed,
    'free_flow_speed_kph' => $freeFlowSpeed,
    'incidents_count' => $incidentsCount,
    'max_delay' => $maxDelay,
    'flow_status' => (int)($flowRes['status'] ?? 0),
    'incidents_status' => (int)($incRes['status'] ?? 0),
    'fetched_at' => date('c'),
  ];

  tmm_cache_set($db, $cacheKey, $payload, $ttl);
  traffic_send(true, 'ok', $payload);
} catch (Throwable $e) {
  traffic_send(false, 'Traffic fetch failed.', ['error' => $e->getMessage()], 500);
}
