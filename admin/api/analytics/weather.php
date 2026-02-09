<?php
require_once __DIR__ . '/../../../includes/cors.php';
tmm_apply_dev_cors();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
header('Content-Type: application/json');

$lat = (float)tmm_setting($db, 'weather_lat', '14.5995');
$lon = (float)tmm_setting($db, 'weather_lon', '120.9842');
$label = tmm_setting($db, 'weather_label', 'Manila, PH');

$cacheKey = 'weather:open-meteo:v2:' . $lat . ',' . $lon;
$cached = tmm_cache_get($db, $cacheKey);
if ($cached) {
  echo json_encode(['ok' => true, 'source' => 'cache', 'label' => $label, 'lat' => $lat, 'lon' => $lon, 'data' => $cached]);
  exit;
}

$url = "https://api.open-meteo.com/v1/forecast?latitude=" . rawurlencode((string)$lat) .
  "&longitude=" . rawurlencode((string)$lon) .
  "&hourly=temperature_2m,precipitation,precipitation_probability,weathercode" .
  "&current_weather=true" .
  "&timezone=auto" .
  "&temperature_unit=celsius" .
  "&windspeed_unit=kmh" .
  "&precipitation_unit=mm";

$res = tmm_http_get_json($url, 12);
if (!($res['ok'] ?? false)) {
  http_response_code(502);
  echo json_encode(['ok' => false, 'error' => 'weather_fetch_failed', 'detail' => $res]);
  exit;
}

$data = $res['data'];
tmm_cache_set($db, $cacheKey, $data, 5 * 60);

echo json_encode(['ok' => true, 'source' => 'live', 'label' => $label, 'lat' => $lat, 'lon' => $lon, 'data' => $data]);

