<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/external_data.php';

header('Content-Type: application/json');
require_permission('analytics.view');

$db = db();

function ap_send(bool $ok, array $data = [], string $error = '', int $code = 200): void {
  http_response_code($code);
  $out = ['ok' => $ok];
  if ($error !== '') $out['error'] = $error;
  foreach ($data as $k => $v) $out[$k] = $v;
  echo json_encode($out);
  exit;
}

function ap_table_exists(mysqli $db, string $name): bool {
  $stmt = $db->prepare("SHOW TABLES LIKE ?");
  if (!$stmt) return false;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_row();
  $stmt->close();
  return (bool)$row;
}

function ap_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $val = $row ? (string)($row['setting_value'] ?? '') : '';
  $val = trim($val);
  return $val !== '' ? $val : $default;
}

function ap_cache_meta(mysqli $db, array $keys): array {
  $out = [];
  foreach ($keys as $k) {
    $stmt = $db->prepare("SELECT fetched_at, expires_at FROM external_data_cache WHERE cache_key=? LIMIT 1");
    if (!$stmt) continue;
    $stmt->bind_param('s', $k);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) continue;
    $out[$k] = [
      'fetched_at' => (string)($row['fetched_at'] ?? ''),
      'expires_at' => (string)($row['expires_at'] ?? ''),
    ];
  }
  return $out;
}

function ap_capture_forecast_summary(string $areaType, int $hours = 24, bool $includeTraffic = false): ?array {
  $old = $_GET;
  $_GET = [
    'area_type' => $areaType,
    'hours' => (string)$hours,
    'include_traffic' => $includeTraffic ? '1' : '0',
  ];
  ob_start();
  include __DIR__ . '/demand_forecast.php';
  $raw = ob_get_clean();
  $_GET = $old;
  $data = json_decode((string)$raw, true);
  if (!is_array($data) || !($data['ok'] ?? false)) return null;
  return [
    'accuracy' => isset($data['accuracy']) ? (float)$data['accuracy'] : null,
    'data_points' => isset($data['data_points']) ? (int)$data['data_points'] : null,
    'accuracy_ok' => isset($data['accuracy_ok']) ? (bool)$data['accuracy_ok'] : null,
    'data_source' => isset($data['data_source']) ? (string)$data['data_source'] : null,
    'traffic_included' => $includeTraffic,
  ];
}

$now = date('c');

$weatherLat = ap_setting($db, 'weather_lat', '14.5995');
$weatherLon = ap_setting($db, 'weather_lon', '120.9842');
$weatherLabel = ap_setting($db, 'weather_label', 'Manila, PH');
$eventsCountry = ap_setting($db, 'events_country', 'PH');
$eventsRssUrl = ap_setting($db, 'events_rss_url', '');

$aiWeather = (float)ap_setting($db, 'ai_weather_weight', '0.12');
$aiEvent = (float)ap_setting($db, 'ai_event_weight', '0.10');
$aiTraffic = (float)ap_setting($db, 'ai_traffic_weight', '1.00');
$tomtomKey = tmm_tomtom_api_key($db);

$observations = [
  'table_exists' => ap_table_exists($db, 'puv_demand_observations'),
  'total' => 0,
  'by_area_type' => [],
  'distinct_areas' => 0,
  'min_observed_at' => null,
  'max_observed_at' => null,
];
if ($observations['table_exists']) {
  $res = $db->query("SELECT area_type, COUNT(*) AS c FROM puv_demand_observations GROUP BY area_type");
  $by = [];
  $total = 0;
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $k = (string)($r['area_type'] ?? '');
      $c = (int)($r['c'] ?? 0);
      if ($k !== '') $by[$k] = $c;
      $total += $c;
    }
  }
  $observations['by_area_type'] = $by;
  $observations['total'] = $total;
  $res2 = $db->query("SELECT COUNT(DISTINCT CONCAT(area_type,':',area_ref)) AS c, MIN(observed_at) AS min_dt, MAX(observed_at) AS max_dt FROM puv_demand_observations");
  if ($res2 && ($r = $res2->fetch_assoc())) {
    $observations['distinct_areas'] = (int)($r['c'] ?? 0);
    $observations['min_observed_at'] = $r['min_dt'] ? (string)$r['min_dt'] : null;
    $observations['max_observed_at'] = $r['max_dt'] ? (string)$r['max_dt'] : null;
  }
}

$fallbacks = [];
$fallbackTables = [
  'terminal_assignments' => 'assigned_at',
  'parking_transactions' => 'created_at',
  'parking_violations' => 'created_at',
  'vehicles' => 'created_at',
];
foreach ($fallbackTables as $t => $dtCol) {
  $exists = ap_table_exists($db, $t);
  $count28d = null;
  if ($exists) {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM {$t} WHERE {$dtCol} >= DATE_SUB(NOW(), INTERVAL 28 DAY)");
    if ($stmt) {
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $count28d = $row ? (int)($row['c'] ?? 0) : 0;
    }
  }
  $fallbacks[$t] = ['table_exists' => $exists, 'count_28d' => $count28d];
}

$cacheKeys = [];
$cacheKeys[] = 'weather:open-meteo:v2:' . $weatherLat . ',' . $weatherLon;
$cacheKeys[] = 'events:nager:' . $eventsCountry . ':' . date('Y');
if ($eventsRssUrl !== '') $cacheKeys[] = 'events:rss:' . sha1($eventsRssUrl);
$cacheMeta = ap_cache_meta($db, $cacheKeys);

$forecastTerminal = ap_capture_forecast_summary('terminal', 24, false);
$forecastRoute = ap_capture_forecast_summary('route', 24, false);

$out = [
  'generated_at' => $now,
  'model' => [
    'type' => 'statistical_blend_with_exogenous_factors',
    'weights' => [
      'weather' => $aiWeather,
      'events' => $aiEvent,
      'traffic' => $aiTraffic,
    ],
    'tomtom_configured' => $tomtomKey !== '',
  ],
  'internal_sources' => [
    'observations' => $observations,
    'fallback_tables' => $fallbacks,
  ],
  'external_sources' => [
    'weather' => [
      'provider' => 'open-meteo',
      'location' => ['label' => $weatherLabel, 'lat' => $weatherLat, 'lon' => $weatherLon],
    ],
    'events' => [
      'holidays_provider' => 'nager-date',
      'country' => $eventsCountry,
      'rss_url_configured' => $eventsRssUrl !== '',
    ],
    'traffic' => [
      'provider' => 'tomtom',
      'api_key_configured' => $tomtomKey !== '',
    ],
    'cache' => $cacheMeta,
  ],
  'forecast_health' => [
    'terminal' => $forecastTerminal,
    'route' => $forecastRoute,
  ],
];

ap_send(true, $out);

