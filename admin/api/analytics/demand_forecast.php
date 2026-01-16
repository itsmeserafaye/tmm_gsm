<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
header('Content-Type: application/json');

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
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

function build_forecast(array $histHourCounts, array $histDailyHourCounts, int $hoursAhead, float $seasonWeight): array {
  $out = [];
  $baseByHour = [];
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

    $pred = ($seasonWeight * $seasonAvg) + ((1.0 - $seasonWeight) * $recentAvg);
    $predRounded = (int)max(0, round($pred));
    $baseline = (float)($baseByHour[$hourOfDay] ?? 0);

    $out[] = [
      'ts' => $ts,
      'hour_label' => date('D H:00', $ts),
      'predicted' => $predRounded,
      'baseline' => round($baseline, 2),
    ];
  }
  return $out;
}

function compute_accuracy(array $seriesByArea, float $seasonWeight): array {
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
  if (is_array($cached)) return $cached;
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

  $today = date('Y-m-d');
  $end = date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
  $map = [];
  foreach ($holidays as $h) {
    if (!is_array($h)) continue;
    $d = (string)($h['date'] ?? '');
    if ($d === '' || $d < $today || $d > $end) continue;
    $map[$d] = (string)($h['localName'] ?? ($h['name'] ?? 'Holiday'));
  }
  return $map;
}

$areas = [];
$areaLists = ['terminal' => [], 'parking_area' => []];
if ($areaType === 'parking_area') {
  $res = $db->query("SELECT id, name, total_slots FROM parking_areas ORDER BY name");
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = ['ref' => (string)$r['id'], 'label' => (string)$r['name'], 'capacity' => (int)($r['total_slots'] ?? 0)];
  }
} else {
  $res = $db->query("SELECT id, name, capacity FROM terminals ORDER BY name");
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = ['ref' => (string)$r['id'], 'label' => (string)$r['name'], 'capacity' => (int)($r['capacity'] ?? 0)];
  }
}
{
  $resT = $db->query("SELECT id, name FROM terminals ORDER BY name");
  while ($resT && ($r = $resT->fetch_assoc())) $areaLists['terminal'][] = ['ref' => (string)$r['id'], 'label' => (string)$r['name']];
  $resP = $db->query("SELECT id, name FROM parking_areas ORDER BY name");
  while ($resP && ($r = $resP->fetch_assoc())) $areaLists['parking_area'][] = ['ref' => (string)$r['id'], 'label' => (string)$r['name']];
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
} elseif ($areaType === 'parking_area') {
  $sql = "
    SELECT 
      parking_area_id AS area_ref,
      DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM parking_transactions
    WHERE created_at >= '$startStr' AND parking_area_id IS NOT NULL
    GROUP BY parking_area_id, hour_start
  ";
  $res = $db->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $key = (string)$r['area_ref'];
      if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
      $seriesByArea[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => safe_int($r['cnt'])];
    }
  }
  $sqlV = "
    SELECT 
      parking_area_id AS area_ref,
      DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM parking_violations
    WHERE created_at >= '$startStr' AND parking_area_id IS NOT NULL
    GROUP BY parking_area_id, hour_start
  ";
  $resV = $db->query($sqlV);
  if ($resV) {
    while ($r = $resV->fetch_assoc()) {
      $key = (string)$r['area_ref'];
      if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
      $seriesByArea[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => safe_int($r['cnt'])];
    }
  }
} else {
  $sql = "
    SELECT 
      COALESCE(pt.terminal_id, pa.terminal_id) AS area_ref,
      DATE_FORMAT(pt.created_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM parking_transactions pt
    LEFT JOIN parking_areas pa ON pa.id = pt.parking_area_id
    WHERE pt.created_at >= '$startStr' AND COALESCE(pt.terminal_id, pa.terminal_id) IS NOT NULL
    GROUP BY area_ref, hour_start
  ";
  $res = $db->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $key = (string)$r['area_ref'];
      if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
      $seriesByArea[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => safe_int($r['cnt'])];
    }
  }
  $sqlV = "
    SELECT 
      pa.terminal_id AS area_ref,
      DATE_FORMAT(pv.created_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM parking_violations pv
    JOIN parking_areas pa ON pa.id = pv.parking_area_id
    WHERE pv.created_at >= '$startStr' AND pa.terminal_id IS NOT NULL
    GROUP BY pa.terminal_id, hour_start
  ";
  $resV = $db->query($sqlV);
  if ($resV) {
    while ($r = $resV->fetch_assoc()) {
      $key = (string)$r['area_ref'];
      if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
      $seriesByArea[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => safe_int($r['cnt'])];
    }
  }
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

$forecastItems = [];
$spikes = [];
$seasonWeights = [0.55, 0.65, 0.75, 0.85, 0.9];
$bestW = 0.75;
$bestAcc = -1.0;
$bestPoints = 0;
foreach ($seasonWeights as $w) {
  $eval = compute_accuracy($seriesByArea, (float)$w);
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

  $pred = build_forecast($histHourCounts, $histDailyHourCounts, $hoursAhead, $bestW);
  $forecastItems[] = [
    'area_ref' => $ref,
    'area_label' => (string)$a['label'],
    'capacity' => (int)$a['capacity'],
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

$eval = compute_accuracy($seriesByArea, $bestW);
$accuracy = (float)($eval['accuracy'] ?? 0);
$points = (int)($eval['points'] ?? 0);
$accuracyTarget = 80.0;
$accuracyOk = ($points >= 40) && ($accuracy >= $accuracyTarget);

$weather = tmm_get_weather_hourly($db, $lat, $lon);
$holidayMap = tmm_get_holidays_map($db, $eventsCountry, (int)date('Y'), $hoursAhead > 48 ? 7 : 3);

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
  'weather' => ['label' => $weatherLabel, 'lat' => $lat, 'lon' => $lon, 'current' => $weather['current_weather'] ?? null],
  'events' => ['country' => $eventsCountry],
  'spikes' => $spikes,
  'areas' => $forecastItems,
  'area_lists' => $areaLists,
]);
