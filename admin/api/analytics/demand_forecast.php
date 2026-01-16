<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
require_once __DIR__ . '/../../../includes/env.php';
$db = db();
header('Content-Type: application/json');

tmm_load_env(__DIR__ . '/../../../.env');

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
$areaType = in_array($areaType, ['terminal', 'route'], true) ? $areaType : 'terminal';
$hoursAhead = (int)($_GET['hours'] ?? 24);
if ($hoursAhead < 6) $hoursAhead = 6;
if ($hoursAhead > 72) $hoursAhead = 72;

$now = time();
$startTs = $now - (28 * 24 * 3600);
$startStr = date('Y-m-d H:i:s', $startTs);

$lat = (float)tmm_setting($db, 'weather_lat', '14.5995');
$lon = (float)tmm_setting($db, 'weather_lon', '120.9842');
$weatherLabel = tmm_setting($db, 'weather_label', 'Manila, PH');
$eventsCountry = strtoupper(trim(tmm_setting($db, 'events_country', 'PH')));
if ($eventsCountry === '') $eventsCountry = 'PH';

function hour_key_from_dt(string $dt): string {
  $ts = strtotime($dt);
  $dow = (int)date('w', $ts);
  $hr = (int)date('G', $ts);
  return $dow . ':' . $hr;
}

function safe_int($v): int {
  if ($v === null) return 0;
  if (is_numeric($v)) return (int)$v;
  return 0;
}

function clamp_float(float $v, float $min, float $max): float {
  if ($v < $min) return $min;
  if ($v > $max) return $max;
  return $v;
}

function compute_trend_factor(array $rows): float {
  $now = time();
  $sum1 = 0; $n1 = 0;
  $sum0 = 0; $n0 = 0;
  $w1Start = $now - (24 * 3600);
  $w0Start = $now - (48 * 3600);
  foreach ($rows as $r) {
    $ts = strtotime((string)($r['hour_start'] ?? ''));
    if ($ts <= 0) continue;
    $cnt = (int)($r['cnt'] ?? 0);
    if ($ts >= $w1Start && $ts <= $now) { $sum1 += $cnt; $n1++; }
    elseif ($ts >= $w0Start && $ts < $w1Start) { $sum0 += $cnt; $n0++; }
  }
  if ($n1 < 6 || $n0 < 6) return 1.0;
  $avg1 = $sum1 / $n1;
  $avg0 = $sum0 / $n0;
  $ratio = ($avg1 + 1.0) / ($avg0 + 1.0);
  return clamp_float($ratio, 0.75, 1.25);
}

function build_forecast(array $histHourCounts, array $histDailyHourCounts, int $hoursAhead, float $seasonWeight, float $trendFactor, array $weather, array $holidayByDate, float $rainProbThreshold, float $rainCoeff, float $holidayCoeff, array $traffic, float $trafficCongestionThreshold, float $trafficCoeff): array {
  $out = [];
  $baseByHour = [];
  $trafficCong = isset($traffic['congestion']) && $traffic['congestion'] !== null ? (float)$traffic['congestion'] : null;
  $trafficIncidents = isset($traffic['incidents_count']) ? (int)$traffic['incidents_count'] : null;
  $trafficMaxDelay = isset($traffic['max_delay']) ? (int)$traffic['max_delay'] : null;
  foreach ($histDailyHourCounts as $h => $vals) {
    $sum = 0;
    $n = 0;
    foreach ($vals as $v) { $sum += $v; $n++; }
    $baseByHour[$h] = $n ? ($sum / $n) : 0;
  }

  for ($i = 1; $i <= $hoursAhead; $i++) {
    $ts = time() + ($i * 3600);
    $key = (int)date('w', $ts) . ':' . (int)date('G', $ts);
    $hourOfDay = (int)date('G', $ts);

    $seasonVals = $histHourCounts[$key] ?? [];
    $seasonAvg = 0;
    if ($seasonVals) {
      $sum = 0; $n = 0;
      foreach ($seasonVals as $v) { $sum += $v; $n++; }
      $seasonAvg = $n ? ($sum / $n) : 0;
    }

    $recentVals = $histDailyHourCounts[$hourOfDay] ?? [];
    $recentAvg = 0;
    if ($recentVals) {
      $sum = 0; $n = 0;
      $slice = array_slice($recentVals, -7);
      foreach ($slice as $v) { $sum += $v; $n++; }
      $recentAvg = $n ? ($sum / $n) : 0;
    }

    $predBase = ($seasonWeight * $seasonAvg) + ((1.0 - $seasonWeight) * $recentAvg);
    $pred = $predBase * $trendFactor;
    $mult = 1.0;
    $w = tmm_weather_lookup($weather, $ts);
    $prob = $w['precip_prob'];
    if (is_numeric($prob)) {
      $p = (float)$prob;
      if ($p >= $rainProbThreshold) {
        $mult += ($rainCoeff * ($p / 100.0));
      }
    }
    $date = date('Y-m-d', $ts);
    if (isset($holidayByDate[$date])) {
      $mult += $holidayCoeff;
    }

    $trafficImpact = 0.0;
    if ($trafficCong !== null && $trafficCong >= $trafficCongestionThreshold) {
      $trafficImpact = $trafficCoeff * $trafficCong;
      $mult += $trafficImpact;
    }
    $predRounded = (int)max(0, round($pred * $mult));
    $baseline = (float)($baseByHour[$hourOfDay] ?? 0);

    $out[] = [
      'ts' => $ts,
      'hour_label' => date('D H:00', $ts),
      'predicted' => $predRounded,
      'baseline' => round($baseline, 2),
      'multiplier' => round($mult, 3),
      'traffic_congestion' => $trafficCong,
      'traffic_incidents' => $trafficIncidents,
      'traffic_max_delay' => $trafficMaxDelay,
      'traffic_impact' => round($trafficImpact, 3),
    ];
  }
  return $out;
}

function compute_accuracy(array $seriesByArea, float $seasonWeight, float $holidayCoeff, array $holidayByDate): array {
  $horizonHours = 7 * 24;
  $endTs = time();
  $startTs = $endTs - ($horizonHours * 3600);
  $mapeSum = 0.0;
  $n = 0;
  foreach ($seriesByArea as $areaKey => $rows) {
    $byHourKey = [];
    $byHourOfDay = [];
    foreach ($rows as $r) {
      $ts = strtotime($r['hour_start']);
      if ($ts < ($startTs - (28 * 24 * 3600))) continue;
      $k = (int)date('w', $ts) . ':' . (int)date('G', $ts);
      $h = (int)date('G', $ts);
      if (!isset($byHourKey[$k])) $byHourKey[$k] = [];
      $byHourKey[$k][] = (int)$r['cnt'];
      if (!isset($byHourOfDay[$h])) $byHourOfDay[$h] = [];
      $byHourOfDay[$h][] = (int)$r['cnt'];
    }

    foreach ($rows as $r) {
      $ts = strtotime($r['hour_start']);
      if ($ts < $startTs || $ts > $endTs) continue;
      $k = (int)date('w', $ts) . ':' . (int)date('G', $ts);
      $h = (int)date('G', $ts);
      $hist = $byHourKey[$k] ?? [];
      $season = 0.0;
      if ($hist) {
        $sum = 0; $c = 0;
        foreach ($hist as $v) { if (strtotime($r['hour_start']) <= $ts) { $sum += $v; $c++; } }
        $season = $c ? ($sum / $c) : 0;
      }
      $recent = 0.0;
      $vals = $byHourOfDay[$h] ?? [];
      if ($vals) {
        $slice = array_slice($vals, -7);
        $sum = 0; $c = 0;
        foreach ($slice as $v) { $sum += $v; $c++; }
        $recent = $c ? ($sum / $c) : 0;
      }
      $pred = ($seasonWeight * $season) + ((1.0 - $seasonWeight) * $recent);
      $date = date('Y-m-d', $ts);
      if (isset($holidayByDate[$date])) {
        $pred *= (1.0 + $holidayCoeff);
      }
      $actual = (int)$r['cnt'];
      if ($actual <= 0) continue;
      $mapeSum += abs($actual - $pred) / $actual;
      $n++;
    }
  }
  if ($n === 0) return ['accuracy' => 0.0, 'points' => 0];
  $mape = $mapeSum / $n;
  $acc = max(0.0, 1.0 - $mape) * 100.0;
  return ['accuracy' => round($acc, 2), 'points' => $n];
}

function tmm_get_weather_hourly(mysqli $db, float $lat, float $lon): array {
  $cacheKey = 'weather:open-meteo:' . $lat . ',' . $lon;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached) && array_key_exists('congestion', $cached) && $cached['congestion'] !== null) return $cached;
  $url = "https://api.open-meteo.com/v1/forecast?latitude=" . rawurlencode((string)$lat) .
    "&longitude=" . rawurlencode((string)$lon) .
    "&hourly=temperature_2m,precipitation,precipitation_probability,weathercode" .
    "&current_weather=true" .
    "&timezone=auto";
  $res = tmm_http_get_json($url, 12);
  if (!($res['ok'] ?? false)) return [];
  $data = $res['data'];
  if (is_array($data)) tmm_cache_set($db, $cacheKey, $data, 15 * 60);
  return is_array($data) ? $data : [];
}

function tmm_get_env(string $key, string $default = ''): string {
  $v = getenv($key);
  if ($v === false) return $default;
  return trim((string)$v);
}

function tmm_traffic_bbox_from_center(float $lat, float $lon, float $km): array {
  $km = max(0.5, min(50.0, $km));
  $dLat = $km / 111.0;
  $cos = cos(deg2rad($lat));
  $dLon = ($cos > 0.00001) ? ($km / (111.0 * $cos)) : ($km / 111.0);
  return [$lon - $dLon, $lat - $dLat, $lon + $dLon, $lat + $dLat];
}

function tmm_get_traffic_snapshot(mysqli $db, float $lat, float $lon): array {
  $provider = strtolower(trim((string)tmm_setting($db, 'traffic_provider', 'tomtom')));
  if ($provider !== 'tomtom') return [];

  $apiKey = tmm_get_env('TOMTOM_API_KEY', (string)tmm_setting($db, 'tomtom_api_key', ''));
  if ($apiKey === '') return [];

  $bboxKm = (float)tmm_setting($db, 'traffic_bbox_km', '5');
  $ttl = (int)tmm_setting($db, 'traffic_cache_ttl', '300');
  $ttl = max(60, min(1800, $ttl));

  $cacheKey = 'traffic:tomtom:' . number_format($lat, 4, '.', '') . ',' . number_format($lon, 4, '.', '') . ':km=' . number_format($bboxKm, 1, '.', '');
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;

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

  $bbox = tmm_traffic_bbox_from_center($lat, $lon, $bboxKm);
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
    'provider' => 'tomtom',
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
  return $payload;
}

function tmm_get_holidays_map(mysqli $db, string $country, int $year, int $daysAhead): array {
  $cacheKey = 'events:nager:' . $country . ':' . $year;
  $holidays = tmm_cache_get($db, $cacheKey);
  if (!is_array($holidays)) {
    $url = "https://date.nager.at/api/v3/PublicHolidays/" . rawurlencode((string)$year) . "/" . rawurlencode($country);
    $res = tmm_http_get_json($url, 12);
    if (($res['ok'] ?? false) && is_array($res['data'])) {
      $holidays = $res['data'];
      tmm_cache_set($db, $cacheKey, $holidays, 24 * 3600);
    } else {
      $holidays = [];
    }
  }

  $map = [];
  foreach ($holidays as $h) {
    if (!is_array($h)) continue;
    $d = (string)($h['date'] ?? '');
    if ($d === '') continue;
    $map[$d] = (string)($h['localName'] ?? ($h['name'] ?? 'Holiday'));
  }
  return $map;
}

function tmm_weather_lookup(array $weather, int $ts): array {
  $out = ['temp_c' => null, 'precip_mm' => null, 'precip_prob' => null, 'weathercode' => null];
  if (!isset($weather['hourly']) || !is_array($weather['hourly'])) return $out;
  $h = $weather['hourly'];
  $times = $h['time'] ?? null;
  if (!is_array($times)) return $out;
  $target = date('Y-m-d\\TH:00', $ts);
  $idx = array_search($target, $times, true);
  if ($idx === false) return $out;
  foreach (['temperature_2m' => 'temp_c', 'precipitation' => 'precip_mm', 'precipitation_probability' => 'precip_prob', 'weathercode' => 'weathercode'] as $src => $dst) {
    $arr = $h[$src] ?? null;
    if (is_array($arr) && array_key_exists($idx, $arr)) $out[$dst] = $arr[$idx];
  }
  return $out;
}

$areas = [];
$areaLists = ['terminal' => [], 'route' => []];
if ($areaType === 'route') {
  $hasLptrpRes = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1");
  $hasLptrp = (bool)($hasLptrpRes && $hasLptrpRes->fetch_row());
  if ($hasLptrp) {
    $res = $db->query("SELECT r.route_id, r.route_name, r.max_vehicle_limit FROM routes r JOIN lptrp_routes lr ON lr.route_code=r.route_id ORDER BY r.route_name, r.route_id");
  } else {
    $res = $db->query("SELECT route_id, route_name, max_vehicle_limit FROM routes ORDER BY route_name, route_id");
  }
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = ['ref' => (string)$r['route_id'], 'label' => (string)($r['route_name'] ?? $r['route_id']), 'capacity' => (int)($r['max_vehicle_limit'] ?? 0)];
  }
} else {
  $city = trim((string)tmm_setting($db, 'events_city', ''));
  $where = "";
  if ($city !== '') {
    $colRes = $db->query("SHOW COLUMNS FROM terminals LIKE 'city'");
    if ($colRes && $colRes->num_rows > 0) {
      $where = " WHERE city LIKE '" . $db->real_escape_string($city) . "%'";
    }
  }
  $res = $db->query("SELECT id, name, capacity FROM terminals" . $where . " ORDER BY name");
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = ['ref' => (string)$r['id'], 'label' => (string)$r['name'], 'capacity' => (int)($r['capacity'] ?? 0)];
  }
}
{
  $city = trim((string)tmm_setting($db, 'events_city', ''));
  $where = "";
  if ($city !== '') {
    $colRes = $db->query("SHOW COLUMNS FROM terminals LIKE 'city'");
    if ($colRes && $colRes->num_rows > 0) {
      $where = " WHERE city LIKE '" . $db->real_escape_string($city) . "%'";
    }
  }
  $resT = $db->query("SELECT id, name FROM terminals" . $where . " ORDER BY name");
  while ($resT && ($r = $resT->fetch_assoc())) $areaLists['terminal'][] = ['ref' => (string)$r['id'], 'label' => (string)$r['name']];
  if (!isset($hasLptrp)) {
    $hasLptrpRes = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1");
    $hasLptrp = (bool)($hasLptrpRes && $hasLptrpRes->fetch_row());
  }
  if ($hasLptrp) {
    $resR = $db->query("SELECT r.route_id, r.route_name FROM routes r JOIN lptrp_routes lr ON lr.route_code=r.route_id ORDER BY r.route_name, r.route_id");
  } else {
    $resR = $db->query("SELECT route_id, route_name FROM routes ORDER BY route_name, route_id");
  }
  while ($resR && ($r = $resR->fetch_assoc())) $areaLists['route'][] = ['ref' => (string)$r['route_id'], 'label' => (string)($r['route_name'] ?? $r['route_id'])];
}

$seriesByArea = [];
function load_series_from_observations(mysqli $db, string $areaType, string $startStr): array {
  $out = [];
  $stmt = $db->prepare("
    SELECT area_ref, DATE_FORMAT(observed_at, '%Y-%m-%d %H:00:00') AS hour_start, SUM(demand_count) AS cnt
    FROM puv_demand_observations
    WHERE area_type=? AND observed_at >= ?
    GROUP BY area_ref, hour_start
  ");
  if (!$stmt) return [];
  $stmt->bind_param('ss', $areaType, $startStr);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) {
    $key = (string)$r['area_ref'];
    if (!isset($out[$key])) $out[$key] = [];
    $out[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => (int)($r['cnt'] ?? 0)];
  }
  $stmt->close();
  return $out;
}

$seriesObs = load_series_from_observations($db, $areaType, $startStr);
$useObservations = !empty($seriesObs);
if ($useObservations) {
  $seriesByArea = $seriesObs;
}

foreach ($seriesByArea as $k => $rows) {
  $agg = [];
  foreach ($rows as $r) {
    $hs = $r['hour_start'];
    if (!isset($agg[$hs])) $agg[$hs] = 0;
    $agg[$hs] += (int)$r['cnt'];
  }
  $out = [];
  foreach ($agg as $hs => $cnt) $out[] = ['hour_start' => $hs, 'cnt' => $cnt];
  usort($out, function ($a, $b) { return strcmp($a['hour_start'], $b['hour_start']); });
  $seriesByArea[$k] = $out;
}

$weather = tmm_get_weather_hourly($db, $lat, $lon);
$holidayMap = tmm_get_holidays_map($db, $eventsCountry, (int)date('Y'), 365);
$traffic = tmm_get_traffic_snapshot($db, $lat, $lon);

$rainProbThreshold = (float)tmm_setting($db, 'ai_rain_prob_threshold', '60');
if ($rainProbThreshold <= 0) $rainProbThreshold = 60.0;
if ($rainProbThreshold > 100) $rainProbThreshold = 100.0;
$rainCoeff = (float)tmm_setting($db, 'ai_rain_coeff', '0.25');
if ($rainCoeff < 0) $rainCoeff = 0.0;
$holidayCoeff = (float)tmm_setting($db, 'ai_holiday_coeff', '0.20');
if ($holidayCoeff < 0) $holidayCoeff = 0.0;
if ($holidayCoeff > 2) $holidayCoeff = 2.0;

$trafficThreshold = (float)tmm_setting($db, 'ai_traffic_congestion_threshold', '0.25');
if ($trafficThreshold < 0) $trafficThreshold = 0.0;
if ($trafficThreshold > 1) $trafficThreshold = 1.0;
$trafficCoeff = (float)tmm_setting($db, 'ai_traffic_coeff', '0.15');
if ($trafficCoeff < 0) $trafficCoeff = 0.0;
if ($trafficCoeff > 2) $trafficCoeff = 2.0;

$forecastItems = [];
$spikes = [];
$seasonWeights = [0.55, 0.65, 0.75, 0.85, 0.9];
$bestW = 0.75;
$bestAcc = -1.0;
$bestPoints = 0;
foreach ($seasonWeights as $w) {
  $eval = compute_accuracy($seriesByArea, (float)$w, $holidayCoeff, $holidayMap);
  $acc = (float)($eval['accuracy'] ?? 0);
  $pts = (int)($eval['points'] ?? 0);
  if ($pts > $bestPoints && $acc >= $bestAcc) { $bestW = (float)$w; $bestAcc = $acc; $bestPoints = $pts; }
  if ($pts === $bestPoints && $acc > $bestAcc) { $bestW = (float)$w; $bestAcc = $acc; }
}

foreach ($areas as $a) {
  $ref = (string)$a['ref'];
  $rows = $seriesByArea[$ref] ?? [];
  $histHourCounts = [];
  $histDailyHourCounts = [];
  foreach ($rows as $r) {
    $k = hour_key_from_dt($r['hour_start']);
    if (!isset($histHourCounts[$k])) $histHourCounts[$k] = [];
    $histHourCounts[$k][] = (int)$r['cnt'];
    $h = (int)date('G', strtotime($r['hour_start']));
    if (!isset($histDailyHourCounts[$h])) $histDailyHourCounts[$h] = [];
    $histDailyHourCounts[$h][] = (int)$r['cnt'];
  }

  $trendFactor = compute_trend_factor($rows);
  $pred = build_forecast($histHourCounts, $histDailyHourCounts, $hoursAhead, $bestW, $trendFactor, $weather, $holidayMap, $rainProbThreshold, $rainCoeff, $holidayCoeff, $traffic, $trafficThreshold, $trafficCoeff);
  $forecastItems[] = [
    'area_ref' => $ref,
    'area_label' => (string)$a['label'],
    'capacity' => (int)$a['capacity'],
    'trend_factor' => round($trendFactor, 3),
    'forecast' => $pred,
  ];

  $next6 = array_slice($pred, 0, 6);
  $peak = 0;
  $peakHour = '';
  $baseAtPeak = 0.0;
  foreach ($next6 as $p) {
    if ((int)$p['predicted'] > $peak) {
      $peak = (int)$p['predicted'];
      $peakHour = (string)$p['hour_label'];
      $baseAtPeak = (float)$p['baseline'];
    }
  }
  $baseline = max(1.0, (float)$baseAtPeak);
  $isSpike = $peak >= 3 && ($peak >= (int)ceil($baseline * 1.3));
  if ($isSpike) {
    $spikes[] = [
      'area_ref' => $ref,
      'area_label' => (string)$a['label'],
      'peak_hour' => $peakHour,
      'predicted_peak' => $peak,
      'baseline' => round($baseline, 2),
    ];
  }
}

usort($spikes, function ($a, $b) {
  if ($a['predicted_peak'] === $b['predicted_peak']) return 0;
  return ($a['predicted_peak'] > $b['predicted_peak']) ? -1 : 1;
});
$spikes = array_slice($spikes, 0, 8);

$eval = compute_accuracy($seriesByArea, $bestW, $holidayCoeff, $holidayMap);
$accuracy = (float)($eval['accuracy'] ?? 0);
$points = (int)($eval['points'] ?? 0);
$accuracyTarget = 80.0;
$accuracyOk = ($points >= 40) && ($accuracy >= $accuracyTarget);

foreach ($forecastItems as &$it) {
  if (!isset($it['forecast']) || !is_array($it['forecast'])) continue;
  foreach ($it['forecast'] as &$p) {
    $ts = (int)($p['ts'] ?? 0);
    if ($ts <= 0) continue;
    $date = date('Y-m-d', $ts);
    $w = tmm_weather_lookup($weather, $ts);
    $p['weather'] = $w;
    if (isset($holidayMap[$date])) {
      $p['event'] = ['type' => 'holiday', 'title' => $holidayMap[$date]];
    } else {
      $p['event'] = null;
    }
  }
}
unset($it); unset($p);

foreach ($spikes as &$s) {
  $ph = (string)($s['peak_hour'] ?? '');
  $ts = strtotime($ph);
  if ($ts) {
    $date = date('Y-m-d', $ts);
    $s['event'] = isset($holidayMap[$date]) ? ['type' => 'holiday', 'title' => $holidayMap[$date]] : null;
    $s['weather'] = tmm_weather_lookup($weather, $ts);
  } else {
    $s['event'] = null;
    $s['weather'] = ['temp_c' => null, 'precip_mm' => null, 'precip_prob' => null, 'weathercode' => null];
  }
}
unset($s);

echo json_encode([
  'ok' => true,
  'area_type' => $areaType,
  'hours' => $hoursAhead,
  'accuracy' => $accuracy,
  'accuracy_target' => $accuracyTarget,
  'accuracy_ok' => $accuracyOk,
  'data_points' => $points,
  'data_source' => $useObservations ? 'ridership_logs' : 'system_activity',
  'model' => [
    'season_weight' => $bestW,
    'rain_prob_threshold' => $rainProbThreshold,
    'rain_coeff' => $rainCoeff,
    'holiday_coeff' => $holidayCoeff,
    'traffic_congestion_threshold' => $trafficThreshold,
    'traffic_coeff' => $trafficCoeff,
  ],
  'weather' => ['label' => $weatherLabel, 'lat' => $lat, 'lon' => $lon, 'current' => $weather['current_weather'] ?? null],
  'traffic' => [
    'label' => $weatherLabel,
    'lat' => $lat,
    'lon' => $lon,
    'provider' => $traffic['provider'] ?? null,
    'congestion' => $traffic['congestion'] ?? null,
    'incidents_count' => $traffic['incidents_count'] ?? null,
    'max_delay' => $traffic['max_delay'] ?? null,
    'flow_status' => $traffic['flow_status'] ?? null,
    'incidents_status' => $traffic['incidents_status'] ?? null,
    'fetched_at' => $traffic['fetched_at'] ?? null,
  ],
  'events' => ['country' => $eventsCountry],
  'spikes' => $spikes,
  'areas' => $forecastItems,
  'area_lists' => $areaLists,
]);
