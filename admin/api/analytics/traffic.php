<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/external_data.php';
require_once __DIR__ . '/../../../includes/env.php';

header('Content-Type: application/json');

function traffic_send(bool $ok, string $message, $data = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data]);
  exit;
}

function traffic_congestion_class(?float $currentSpeed, ?float $freeFlowSpeed): string {
  if ($currentSpeed === null || $freeFlowSpeed === null || $currentSpeed <= 0.0 || $freeFlowSpeed <= 0.0) return 'unknown';
  $ratio = $currentSpeed / $freeFlowSpeed;
  if ($ratio >= 0.85) return 'free';
  if ($ratio >= 0.65) return 'moderate';
  if ($ratio >= 0.45) return 'heavy';
  return 'severe';
}

function traffic_congestion_class_from_pct(?float $congestionPct): string {
  if ($congestionPct === null) return 'unknown';
  if ($congestionPct <= 15.0) return 'free';
  if ($congestionPct <= 35.0) return 'moderate';
  if ($congestionPct <= 55.0) return 'heavy';
  return 'severe';
}

function traffic_flow_metrics(?array $flow): array {
  $out = ['congestion' => 'unknown', 'congestion_pct' => null, 'current_speed_kph' => null, 'free_flow_speed_kph' => null];
  if (!is_array($flow) || !is_array($flow['flowSegmentData'] ?? null)) return $out;
  $fsd = $flow['flowSegmentData'];
  $current = isset($fsd['currentSpeed']) ? (float)$fsd['currentSpeed'] : null;
  $free = isset($fsd['freeFlowSpeed']) ? (float)$fsd['freeFlowSpeed'] : null;
  $out['current_speed_kph'] = $current;
  $out['free_flow_speed_kph'] = $free;
  $out['congestion'] = traffic_congestion_class($current, $free);
  if ($current !== null && $free !== null && $free > 0.0) {
    $out['congestion_pct'] = round(max(0.0, min(100.0, (1.0 - ($current / $free)) * 100.0)), 1);
  }
  return $out;
}

function traffic_incident_metrics(?array $inc): array {
  $out = ['incidents_count' => 0, 'samples' => []];
  $list = (is_array($inc) && is_array($inc['incidents'] ?? null)) ? $inc['incidents'] : [];
  if (!is_array($list)) return $out;
  $out['incidents_count'] = count($list);
  $samples = [];
  foreach ($list as $it) {
    if (!is_array($it)) continue;
    $p = $it['properties'] ?? null;
    if (!is_array($p)) continue;
    $desc = '';
    $events = $p['events'] ?? null;
    if (is_array($events) && !empty($events) && is_array($events[0] ?? null)) {
      $desc = (string)($events[0]['description'] ?? '');
    }
    if ($desc === '') $desc = (string)($p['from'] ?? '');
    $desc = trim($desc);
    if ($desc !== '') $samples[] = $desc;
    if (count($samples) >= 3) break;
  }
  $out['samples'] = $samples;
  return $out;
}

function traffic_factor_from_metrics(?float $congestionPct, int $incCount): float {
  $impact = 0.0;
  if ($congestionPct !== null) $impact += ($congestionPct / 100.0) * 0.20;
  $impact += min(0.10, $incCount * 0.01);
  return 1.0 + min(0.25, max(0.0, $impact));
}

function traffic_city_snapshot(mysqli $db, float $radiusKm): array {
  $lat = (float)tmm_setting($db, 'weather_lat', '14.5995');
  $lon = (float)tmm_setting($db, 'weather_lon', '120.9842');
  $label = (string)tmm_setting($db, 'weather_label', 'City Center');
  $flow = tmm_tomtom_traffic_flow($db, $lat, $lon);
  $inc = tmm_tomtom_traffic_incidents($db, $lat, $lon, $radiusKm);
  $fm = traffic_flow_metrics($flow);
  $im = traffic_incident_metrics($inc);
  return [
    'label' => $label,
    'congestion' => $fm['congestion'],
    'congestion_pct' => $fm['congestion_pct'],
    'incidents_count' => (int)$im['incidents_count'],
    'incident_samples' => $im['samples'],
    'points' => [[
      'label' => 'city_center',
      'lat' => $lat,
      'lon' => $lon,
      'congestion' => $fm['congestion'],
      'congestion_pct' => $fm['congestion_pct'],
      'incidents_count' => (int)$im['incidents_count'],
    ]],
  ];
}

try {
  tmm_load_env(__DIR__ . '/../../../.env');
  require_login();
  $db = db();

  $provider = strtolower(trim((string)tmm_setting($db, 'traffic_provider', 'tomtom')));
  $bboxKm = isset($_GET['radius_km']) ? (float)$_GET['radius_km'] : (float)tmm_setting($db, 'traffic_bbox_km', '2.5');
  $bboxKm = max(0.5, min(10.0, $bboxKm));
  $ttl = (int)tmm_setting($db, 'traffic_cache_ttl', '300');
  $ttl = max(60, min(1800, $ttl));

  if ($provider !== 'tomtom') {
    traffic_send(true, 'Traffic provider not configured.', ['configured' => false, 'provider' => $provider]);
  }

  $apiKey = tmm_tomtom_api_key($db);
  if ($apiKey === '') {
    traffic_send(true, 'Traffic is not configured (missing TomTom API key).', ['configured' => false, 'provider' => 'tomtom']);
  }

  $areaType = trim((string)($_GET['area_type'] ?? 'city'));
  $areaType = $areaType === 'parking_area' ? 'route' : $areaType;
  if (!in_array($areaType, ['city', 'terminal', 'route'], true)) $areaType = 'city';
  $areaRef = trim((string)($_GET['area_ref'] ?? ''));

  $cacheKey = 'traffic:ctx:' . $areaType . ':' . md5($areaRef . ':km=' . $bboxKm);
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) traffic_send(true, 'ok', $cached);

  $payload = [
    'configured' => true,
    'provider' => 'tomtom',
    'area_type' => $areaType,
    'area_ref' => $areaRef,
    'radius_km' => $bboxKm,
    'traffic_status' => 'ok',
    'traffic_source' => 'context',
    'label' => '',
    'traffic_factor' => 1.0,
    'congestion' => 'unknown',
    'congestion_pct' => null,
    'incidents_count' => 0,
    'incident_samples' => [],
    'points' => [],
    'fetched_at' => date('c'),
  ];

  if ($areaType === 'city') {
    $payload['traffic_source'] = 'city';
    $snap = traffic_city_snapshot($db, $bboxKm);
    $payload['label'] = $snap['label'];
    $payload['congestion'] = $snap['congestion'];
    $payload['congestion_pct'] = $snap['congestion_pct'];
    $payload['incidents_count'] = $snap['incidents_count'];
    $payload['incident_samples'] = $snap['incident_samples'];
    $payload['points'] = $snap['points'];
    $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
    tmm_cache_set($db, $cacheKey, $payload, $ttl);
    traffic_send(true, 'ok', $payload);
  }

  if ($areaType === 'terminal') {
    if ($areaRef === '') {
      $payload['traffic_status'] = 'missing_area_ref';
      tmm_cache_set($db, $cacheKey, $payload, 60);
      traffic_send(true, 'ok', $payload);
    }
    $stmt = $db->prepare("SELECT name, city, address FROM terminals WHERE id=? LIMIT 1");
    if (!$stmt) traffic_send(false, 'db_prepare_failed', null, 500);
    $stmt->bind_param('s', $areaRef);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
      $payload['traffic_status'] = 'unknown_area_ref';
      tmm_cache_set($db, $cacheKey, $payload, 60);
      traffic_send(true, 'ok', $payload);
    }
    $payload['label'] = (string)($row['name'] ?? 'Terminal');
    $q = trim(((string)($row['address'] ?? '')) . ' ' . ((string)($row['city'] ?? '')) . ' Philippines');
    if ($q === '') {
      $payload['traffic_status'] = 'missing_location';
      $payload['traffic_source'] = 'city_fallback';
      $snap = traffic_city_snapshot($db, $bboxKm);
      $payload['congestion'] = $snap['congestion'];
      $payload['congestion_pct'] = $snap['congestion_pct'];
      $payload['incidents_count'] = $snap['incidents_count'];
      $payload['incident_samples'] = $snap['incident_samples'];
      $payload['points'] = $snap['points'];
      $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
      tmm_cache_set($db, $cacheKey, $payload, $ttl);
      traffic_send(true, 'ok', $payload);
    }
    $geo = tmm_tomtom_geocode($db, $q);
    if (!is_array($geo)) {
      $payload['traffic_status'] = 'geocode_failed';
      $payload['traffic_source'] = 'city_fallback';
      $snap = traffic_city_snapshot($db, $bboxKm);
      $payload['congestion'] = $snap['congestion'];
      $payload['congestion_pct'] = $snap['congestion_pct'];
      $payload['incidents_count'] = $snap['incidents_count'];
      $payload['incident_samples'] = $snap['incident_samples'];
      $payload['points'] = $snap['points'];
      $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
      tmm_cache_set($db, $cacheKey, $payload, $ttl);
      traffic_send(true, 'ok', $payload);
    }
    $lat = (float)$geo['lat'];
    $lon = (float)$geo['lon'];
    $flow = tmm_tomtom_traffic_flow($db, $lat, $lon);
    $inc = tmm_tomtom_traffic_incidents($db, $lat, $lon, $bboxKm);
    $fm = traffic_flow_metrics($flow);
    $im = traffic_incident_metrics($inc);
    $payload['congestion'] = $fm['congestion'];
    $payload['congestion_pct'] = $fm['congestion_pct'];
    $payload['incidents_count'] = (int)$im['incidents_count'];
    $payload['incident_samples'] = $im['samples'];
    $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
    $payload['points'] = [[
      'label' => 'terminal',
      'lat' => $lat,
      'lon' => $lon,
      'congestion' => $payload['congestion'],
      'congestion_pct' => $payload['congestion_pct'],
      'incidents_count' => $payload['incidents_count'],
    ]];
    tmm_cache_set($db, $cacheKey, $payload, $ttl);
    traffic_send(true, 'ok', $payload);
  }

  if ($areaType === 'route') {
    if ($areaRef === '') {
      $payload['traffic_status'] = 'missing_area_ref';
      tmm_cache_set($db, $cacheKey, $payload, 60);
      traffic_send(true, 'ok', $payload);
    }
    $stmt = $db->prepare("SELECT route_name, origin, destination FROM routes WHERE route_id=? LIMIT 1");
    if (!$stmt) traffic_send(false, 'db_prepare_failed', null, 500);
    $stmt->bind_param('s', $areaRef);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
      $payload['traffic_status'] = 'unknown_area_ref';
      tmm_cache_set($db, $cacheKey, $payload, 60);
      traffic_send(true, 'ok', $payload);
    }
    $payload['label'] = (string)($row['route_name'] ?? 'Route');
    $origin = trim((string)($row['origin'] ?? ''));
    $dest = trim((string)($row['destination'] ?? ''));
    if ($origin === '' && $dest === '') {
      $payload['traffic_status'] = 'missing_location';
      $payload['traffic_source'] = 'city_fallback';
      $snap = traffic_city_snapshot($db, $bboxKm);
      $payload['congestion'] = $snap['congestion'];
      $payload['congestion_pct'] = $snap['congestion_pct'];
      $payload['incidents_count'] = $snap['incidents_count'];
      $payload['incident_samples'] = $snap['incident_samples'];
      $payload['points'] = $snap['points'];
      $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
      tmm_cache_set($db, $cacheKey, $payload, $ttl);
      traffic_send(true, 'ok', $payload);
    }
    $points = [];
    if ($origin !== '') $points[] = ['label' => 'origin', 'query' => $origin . ' Philippines'];
    if ($dest !== '') $points[] = ['label' => 'destination', 'query' => $dest . ' Philippines'];
    $cong = [];
    $incTotal = 0;
    foreach ($points as $pt) {
      $geo = tmm_tomtom_geocode($db, (string)$pt['query']);
      if (!is_array($geo)) continue;
      $lat = (float)$geo['lat'];
      $lon = (float)$geo['lon'];
      $flow = tmm_tomtom_traffic_flow($db, $lat, $lon);
      $inc = tmm_tomtom_traffic_incidents($db, $lat, $lon, $bboxKm);
      $fm = traffic_flow_metrics($flow);
      $im = traffic_incident_metrics($inc);
      if ($fm['congestion_pct'] !== null) $cong[] = (float)$fm['congestion_pct'];
      $incTotal += (int)$im['incidents_count'];
      $payload['points'][] = [
        'label' => (string)$pt['label'],
        'lat' => $lat,
        'lon' => $lon,
        'congestion' => $fm['congestion'],
        'congestion_pct' => $fm['congestion_pct'],
        'incidents_count' => (int)$im['incidents_count'],
      ];
      foreach ($im['samples'] as $s) {
        if (!is_string($s) || $s === '') continue;
        $payload['incident_samples'][] = $s;
        if (count($payload['incident_samples']) >= 3) break;
      }
      if (count($payload['incident_samples']) >= 3) break;
    }
    if (empty($payload['points'])) {
      $payload['traffic_status'] = 'geocode_failed';
      $payload['traffic_source'] = 'city_fallback';
      $snap = traffic_city_snapshot($db, $bboxKm);
      $payload['congestion'] = $snap['congestion'];
      $payload['congestion_pct'] = $snap['congestion_pct'];
      $payload['incidents_count'] = $snap['incidents_count'];
      $payload['incident_samples'] = $snap['incident_samples'];
      $payload['points'] = $snap['points'];
      $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
      tmm_cache_set($db, $cacheKey, $payload, $ttl);
      traffic_send(true, 'ok', $payload);
    }
    $avgCong = !empty($cong) ? (array_sum($cong) / count($cong)) : null;
    $payload['congestion_pct'] = $avgCong !== null ? round((float)$avgCong, 1) : null;
    $payload['congestion'] = traffic_congestion_class_from_pct($payload['congestion_pct']);
    $payload['incidents_count'] = $incTotal;
    $payload['traffic_factor'] = round(traffic_factor_from_metrics($payload['congestion_pct'], (int)$payload['incidents_count']), 3);
    tmm_cache_set($db, $cacheKey, $payload, $ttl);
    traffic_send(true, 'ok', $payload);
  }

  traffic_send(true, 'ok', $payload);
} catch (Throwable $e) {
  traffic_send(false, 'Traffic fetch failed.', ['error' => $e->getMessage()], 500);
}
