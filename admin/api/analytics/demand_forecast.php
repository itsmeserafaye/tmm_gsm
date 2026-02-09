<?php
require_once __DIR__ . '/../../../includes/cors.php';
tmm_apply_dev_cors();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/external_data.php';
$db = db();
// Only set header if not being included by another API endpoint
if (php_sapi_name() !== 'cli' && !ob_get_level()) {
    header('Content-Type: application/json');
}

$areaType = trim((string)($_GET['area_type'] ?? 'terminal'));
$areaType = $areaType === 'parking_area' ? 'route' : $areaType;
$areaType = in_array($areaType, ['terminal', 'route'], true) ? $areaType : 'terminal';
$hoursAhead = (int)($_GET['hours'] ?? 24);
if ($hoursAhead < 6) $hoursAhead = 6;
if ($hoursAhead > 72) $hoursAhead = 72;
$includeTraffic = ((int)($_GET['include_traffic'] ?? 0)) === 1;

$cacheKey = 'demand_forecast:v3:' . $areaType . ':' . $hoursAhead . ':it=' . ($includeTraffic ? '1' : '0');
$cached = tmm_cache_get($db, $cacheKey);
if (is_array($cached) && ($cached['ok'] ?? false)) {
  echo json_encode($cached);
  return;
}

$now = time();
$startTs = $now - (28 * 24 * 3600);
$startStr = date('Y-m-d H:i:s', $startTs);

$lat = (float)tmm_setting($db, 'weather_lat', '14.5995');
$lon = (float)tmm_setting($db, 'weather_lon', '120.9842');
$weatherLabel = tmm_setting($db, 'weather_label', 'Manila, PH');
$eventsCountry = strtoupper(trim(tmm_setting($db, 'events_country', 'PH')));
if ($eventsCountry === '') $eventsCountry = 'PH';
$eventsRssUrl = trim((string)tmm_setting($db, 'events_rss_url', ''));

$aiWeatherWeight = (float)tmm_setting($db, 'ai_weather_weight', '0.12');
$aiEventWeight = (float)tmm_setting($db, 'ai_event_weight', '0.10');
$aiTrafficWeight = (float)tmm_setting($db, 'ai_traffic_weight', '1.00');
if (!is_finite($aiWeatherWeight)) $aiWeatherWeight = 0.12;
if (!is_finite($aiEventWeight)) $aiEventWeight = 0.10;
if (!is_finite($aiTrafficWeight)) $aiTrafficWeight = 1.00;
$aiTrafficWeight = max(0.0, min(2.0, $aiTrafficWeight));

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
  $cacheKey = 'weather:open-meteo:v2:' . $lat . ',' . $lon;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;
  $url = "https://api.open-meteo.com/v1/forecast?latitude=" . rawurlencode((string)$lat) .
    "&longitude=" . rawurlencode((string)$lon) .
    "&hourly=temperature_2m,precipitation,precipitation_probability,weathercode" .
    "&current_weather=true" .
    "&timezone=auto" .
    "&temperature_unit=celsius" .
    "&windspeed_unit=kmh" .
    "&precipitation_unit=mm";
  $res = tmm_http_get_json($url, 12);
  if (!($res['ok'] ?? false)) return [];
  $data = $res['data'];
  if (is_array($data)) tmm_cache_set($db, $cacheKey, $data, 5 * 60);
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

$weather = tmm_get_weather_hourly($db, $lat, $lon);
$holidayMap = tmm_get_holidays_map($db, $eventsCountry, (int)date('Y'), $hoursAhead > 48 ? 7 : 3);

function tmm_get_events_map(mysqli $db, array $holidayMap, string $rssUrl, int $daysAhead): array {
  $map = [];
  foreach ($holidayMap as $d => $title) {
    $map[$d] = ['type' => 'holiday', 'title' => (string)$title, 'source' => 'nager'];
  }
  if ($rssUrl === '') return $map;

  $today = date('Y-m-d');
  $end = date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
  $rssKey = 'events:rss:' . sha1($rssUrl);
  $rssCached = tmm_cache_get($db, $rssKey);
  $rssItems = null;
  if (is_array($rssCached)) {
    $rssItems = $rssCached;
  } else {
    $raw = @file_get_contents($rssUrl);
    if (is_string($raw) && $raw !== '') {
      $xml = @simplexml_load_string($raw);
      if ($xml) {
        $items = [];
        if (isset($xml->channel->item)) {
          foreach ($xml->channel->item as $it) {
            $title = trim((string)$it->title);
            $pub = trim((string)$it->pubDate);
            $link = trim((string)$it->link);
            $dt = $pub ? date('Y-m-d', strtotime($pub)) : '';
            if ($dt !== '') $items[] = ['date' => $dt, 'title' => $title, 'link' => $link];
          }
        } elseif (isset($xml->entry)) {
          foreach ($xml->entry as $it) {
            $title = trim((string)$it->title);
            $updated = trim((string)$it->updated);
            $dt = $updated ? date('Y-m-d', strtotime($updated)) : '';
            $link = '';
            if (isset($it->link)) {
              foreach ($it->link as $lnk) {
                $href = (string)$lnk['href'];
                if ($href) { $link = $href; break; }
              }
            }
            if ($dt !== '') $items[] = ['date' => $dt, 'title' => $title, 'link' => $link];
          }
        }
        $rssItems = $items;
        tmm_cache_set($db, $rssKey, $rssItems, 30 * 60);
      }
    }
  }
  if (is_array($rssItems)) {
    foreach ($rssItems as $it) {
      $d = (string)($it['date'] ?? '');
      if ($d === '' || $d < $today || $d > $end) continue;
      if (!isset($map[$d]) || ($map[$d]['type'] ?? '') !== 'holiday') {
        $map[$d] = [
          'type' => 'event',
          'title' => (string)($it['title'] ?? 'Event'),
          'source' => 'rss',
          'link' => (string)($it['link'] ?? ''),
        ];
      }
    }
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

function tmm_compute_weather_factor(array $w, int $hourOfDay, float $weight): array {
  $prob = isset($w['precip_prob']) && $w['precip_prob'] !== null ? (float)$w['precip_prob'] : null;
  $mm = isset($w['precip_mm']) && $w['precip_mm'] !== null ? (float)$w['precip_mm'] : 0.0;
  $temp = isset($w['temp_c']) && $w['temp_c'] !== null ? (float)$w['temp_c'] : null;
  $severity = 0.0;
  if ($prob !== null) $severity = max($severity, max(0.0, min(1.0, $prob / 100.0)));
  if ($mm > 0.0) $severity = max($severity, max(0.0, min(1.0, $mm / 6.0)));
  $tempSeverity = 0.0;
  if ($temp !== null) {
    if ($temp >= 36) $tempSeverity = min(1.0, ($temp - 36) / 6);
    elseif ($temp <= 20) $tempSeverity = min(1.0, (20 - $temp) / 8);
  }

  $commuteBoost = ($hourOfDay >= 6 && $hourOfDay <= 9) || ($hourOfDay >= 16 && $hourOfDay <= 19);
  $scale = $commuteBoost ? 1.0 : 0.7;
  $impact = ($severity * 0.85 + $tempSeverity * 0.15) * $scale;
  $factor = 1.0 + ($weight * $impact);
  $factor = max(0.70, min(1.35, $factor));
  return ['factor' => round($factor, 3), 'severity' => round($severity, 3), 'temp_severity' => round($tempSeverity, 3)];
}

function tmm_compute_event_factor(?array $evt, int $hourOfDay, float $weight): array {
  if (!$evt || !is_array($evt) || empty($evt['type'])) return ['factor' => 1.0, 'score' => 0.0];
  $type = (string)($evt['type'] ?? '');
  $score = 0.0;
  if ($type === 'holiday') {
    if (($hourOfDay >= 10 && $hourOfDay <= 16)) $score = 1.0;
    elseif (($hourOfDay >= 6 && $hourOfDay <= 9) || ($hourOfDay >= 16 && $hourOfDay <= 19)) $score = -0.4;
    else $score = 0.35;
  } else {
    if ($hourOfDay >= 16 && $hourOfDay <= 22) $score = 0.9;
    elseif ($hourOfDay >= 11 && $hourOfDay <= 15) $score = 0.4;
    else $score = 0.2;
  }
  $factor = 1.0 + ($weight * $score);
  $factor = max(0.70, min(1.40, $factor));
  return ['factor' => round($factor, 3), 'score' => round($score, 3)];
}

$eventsMap = tmm_get_events_map($db, $holidayMap, $eventsRssUrl, $hoursAhead > 48 ? 7 : 3);

$areas = [];
$areaLists = ['terminal' => [], 'route' => []];
$obsAlias = ['terminal' => [], 'route' => []];
if ($areaType === 'route') {
  $res = $db->query("SELECT route_id, route_name, origin, destination, max_vehicle_limit FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name");
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = [
      'ref' => (string)$r['route_id'],
      'label' => (string)$r['route_name'],
      'capacity' => (int)($r['max_vehicle_limit'] ?? 0),
      'origin' => (string)($r['origin'] ?? ''),
      'destination' => (string)($r['destination'] ?? ''),
    ];
  }
} else {
  $res = $db->query("SELECT id, name, city, address, capacity FROM terminals ORDER BY name");
  while ($res && ($r = $res->fetch_assoc())) {
    $areas[] = [
      'ref' => (string)$r['id'],
      'label' => (string)$r['name'],
      'capacity' => (int)($r['capacity'] ?? 0),
      'city' => (string)($r['city'] ?? ''),
      'address' => (string)($r['address'] ?? ''),
    ];
  }
}
{
  $resT = $db->query("SELECT id, name FROM terminals ORDER BY name");
  while ($resT && ($r = $resT->fetch_assoc())) {
    $id = (string)$r['id'];
    $name = trim((string)$r['name']);
    $areaLists['terminal'][] = ['ref' => $id, 'label' => $name];
    if ($id !== '') {
      $obsAlias['terminal'][$id] = $id;
      $obsAlias['terminal'][strtolower($id)] = $id;
    }
    if ($name !== '') {
      $obsAlias['terminal'][$name] = $id;
      $obsAlias['terminal'][strtolower($name)] = $id;
    }
  }
  $resR = $db->query("SELECT route_id, route_name FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name");
  while ($resR && ($r = $resR->fetch_assoc())) {
    $id = (string)$r['route_id'];
    $name = trim((string)$r['route_name']);
    $areaLists['route'][] = ['ref' => $id, 'label' => $name];
    if ($id !== '') {
      $obsAlias['route'][$id] = $id;
      $obsAlias['route'][strtolower($id)] = $id;
    }
    if ($name !== '') {
      $obsAlias['route'][$name] = $id;
      $obsAlias['route'][strtolower($name)] = $id;
    }
  }
}

$seriesByArea = [];
function load_series_from_observations(mysqli $db, string $areaType, string $startStr, array $aliasMap): array {
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
    $raw = trim((string)$r['area_ref']);
    $key = $raw;
    if ($raw !== '') {
      if (isset($aliasMap[$raw])) $key = (string)$aliasMap[$raw];
      else {
        $low = strtolower($raw);
        if (isset($aliasMap[$low])) $key = (string)$aliasMap[$low];
      }
    }
    if (!isset($out[$key])) $out[$key] = [];
    $out[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => (int)($r['cnt'] ?? 0)];
  }
  $stmt->close();
  return $out;
}

$seriesObs = load_series_from_observations($db, $areaType, $startStr, $obsAlias[$areaType] ?? []);
$knownRefs = [];
foreach ($areas as $a) {
  if (is_array($a) && isset($a['ref'])) $knownRefs[(string)$a['ref']] = true;
}
$seriesObsKnown = [];
foreach ($seriesObs as $k => $rows) {
  if (isset($knownRefs[(string)$k])) $seriesObsKnown[(string)$k] = $rows;
}
$useObservations = !empty($seriesObsKnown);
if ($useObservations) {
  $seriesByArea = $seriesObsKnown;
} elseif ($areaType === 'route') {
  $sqlA = "
    SELECT 
      route_id AS area_ref,
      DATE_FORMAT(assigned_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM terminal_assignments
    WHERE assigned_at >= '$startStr' AND route_id IS NOT NULL
    GROUP BY route_id, hour_start
  ";
  $resA = $db->query($sqlA);
  if ($resA) {
    while ($r = $resA->fetch_assoc()) {
      $key = (string)$r['area_ref'];
      if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
      $seriesByArea[$key][] = ['hour_start' => (string)$r['hour_start'], 'cnt' => safe_int($r['cnt'])];
    }
  }
  $sqlV = "
    SELECT 
      route_id AS area_ref,
      DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour_start,
      COUNT(*) AS cnt
    FROM vehicles
    WHERE created_at >= '$startStr' AND route_id IS NOT NULL
    GROUP BY route_id, hour_start
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

$tomtomConfigured = $includeTraffic && (tmm_tomtom_api_key($db) !== '');

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

  $trafficStatus = $tomtomConfigured ? 'ok' : 'unavailable';
  $traffic = null;
  $trafficFactorBase = 1.0;
  if ($tomtomConfigured) {
    if ($areaType === 'terminal') {
      $q = trim(((string)($a['address'] ?? '')) . ' ' . ((string)($a['city'] ?? '')) . ' Philippines');
      if ($q !== '') {
        $geo = tmm_tomtom_geocode($db, $q);
        if (is_array($geo)) {
          $flow = tmm_tomtom_traffic_flow($db, (float)$geo['lat'], (float)$geo['lon']);
          $inc = tmm_tomtom_traffic_incidents($db, (float)$geo['lat'], (float)$geo['lon'], 2.5);
          $congestionPct = null;
          $currentSpeed = null;
          $freeFlowSpeed = null;
          if (is_array($flow) && is_array($flow['flowSegmentData'] ?? null)) {
            $fsd = $flow['flowSegmentData'];
            $currentSpeed = isset($fsd['currentSpeed']) ? (float)$fsd['currentSpeed'] : null;
            $freeFlowSpeed = isset($fsd['freeFlowSpeed']) ? (float)$fsd['freeFlowSpeed'] : null;
            if ($currentSpeed !== null && $freeFlowSpeed !== null && $freeFlowSpeed > 0.0) {
              $congestionPct = round(max(0.0, min(100.0, (1.0 - ($currentSpeed / $freeFlowSpeed)) * 100.0)), 1);
            }
          }
          $incCount = (is_array($inc) && is_array($inc['incidents'] ?? null)) ? count($inc['incidents']) : 0;
          $impact = 0.0;
          if (is_numeric($congestionPct)) $impact += ((float)$congestionPct / 100.0) * 0.20;
          $impact += min(0.10, $incCount * 0.01);
          $impact *= $aiTrafficWeight;
          $trafficFactorBase = 1.0 + min(0.25, max(0.0, $impact));
          $traffic = [
            'point' => ['lat' => (float)$geo['lat'], 'lon' => (float)$geo['lon']],
            'congestion_pct' => $congestionPct,
            'incidents_count' => $incCount,
            'current_speed_kph' => $currentSpeed,
            'free_flow_speed_kph' => $freeFlowSpeed,
          ];
        } else {
          $trafficStatus = 'geocode_failed';
        }
      } else {
        $trafficStatus = 'missing_location';
      }
    } else {
      $o = trim(((string)($a['origin'] ?? '')) . ' Philippines');
      $d = trim(((string)($a['destination'] ?? '')) . ' Philippines');
      $oGeo = $o !== '' ? tmm_tomtom_geocode($db, $o) : null;
      $dGeo = $d !== '' ? tmm_tomtom_geocode($db, $d) : null;
      $points = [];
      if (is_array($oGeo)) $points[] = ['label' => 'origin', 'geo' => $oGeo];
      if (is_array($dGeo)) $points[] = ['label' => 'destination', 'geo' => $dGeo];
      if (!empty($points)) {
        $congPcts = [];
        $incTotal = 0;
        $trafficPoints = [];
        foreach ($points as $pt) {
          $geo = $pt['geo'];
          $flow = tmm_tomtom_traffic_flow($db, (float)$geo['lat'], (float)$geo['lon']);
          $inc = tmm_tomtom_traffic_incidents($db, (float)$geo['lat'], (float)$geo['lon'], 2.5);
          $congestionPct = null;
          $currentSpeed = null;
          $freeFlowSpeed = null;
          if (is_array($flow) && is_array($flow['flowSegmentData'] ?? null)) {
            $fsd = $flow['flowSegmentData'];
            $currentSpeed = isset($fsd['currentSpeed']) ? (float)$fsd['currentSpeed'] : null;
            $freeFlowSpeed = isset($fsd['freeFlowSpeed']) ? (float)$fsd['freeFlowSpeed'] : null;
            if ($currentSpeed !== null && $freeFlowSpeed !== null && $freeFlowSpeed > 0.0) {
              $congestionPct = round(max(0.0, min(100.0, (1.0 - ($currentSpeed / $freeFlowSpeed)) * 100.0)), 1);
              $congPcts[] = (float)$congestionPct;
            }
          }
          $incCount = (is_array($inc) && is_array($inc['incidents'] ?? null)) ? count($inc['incidents']) : 0;
          $incTotal += $incCount;
          $trafficPoints[] = [
            'label' => (string)$pt['label'],
            'point' => ['lat' => (float)$geo['lat'], 'lon' => (float)$geo['lon']],
            'congestion_pct' => $congestionPct,
            'incidents_count' => $incCount,
            'current_speed_kph' => $currentSpeed,
            'free_flow_speed_kph' => $freeFlowSpeed,
          ];
        }
        $avgCong = !empty($congPcts) ? (array_sum($congPcts) / count($congPcts)) : null;
        $impact = 0.0;
        if (is_numeric($avgCong)) $impact += ((float)$avgCong / 100.0) * 0.20;
        $impact += min(0.10, $incTotal * 0.01);
        $impact *= $aiTrafficWeight;
        $trafficFactorBase = 1.0 + min(0.25, max(0.0, $impact));
        $traffic = [
          'points' => $trafficPoints,
          'congestion_pct_avg' => $avgCong !== null ? round((float)$avgCong, 1) : null,
          'incidents_count_total' => $incTotal,
        ];
      } else {
        $trafficStatus = 'missing_location';
      }
    }
  }

  foreach ($pred as $i => &$pp) {
    $hAhead = $i + 1;
    $trafficFactor = 1.0;
    if ($trafficFactorBase > 1.0) {
      if ($hAhead <= 6) $trafficFactor = $trafficFactorBase;
      elseif ($hAhead <= 12) $trafficFactor = 1.0 + (($trafficFactorBase - 1.0) * 0.5);
    }
    $ts = (int)($pp['ts'] ?? 0);
    $hourOfDay = $ts > 0 ? (int)date('G', $ts) : 0;
    $date = $ts > 0 ? date('Y-m-d', $ts) : '';
    $w = $ts > 0 ? tmm_weather_lookup($weather, $ts) : ['temp_c' => null, 'precip_mm' => null, 'precip_prob' => null, 'weathercode' => null];
    $evt = ($date !== '' && isset($eventsMap[$date])) ? $eventsMap[$date] : null;
    $wf = tmm_compute_weather_factor($w, $hourOfDay, $aiWeatherWeight);
    $ef = tmm_compute_event_factor($evt, $hourOfDay, $aiEventWeight);
    $combined = $trafficFactor * (float)($wf['factor'] ?? 1.0) * (float)($ef['factor'] ?? 1.0);
    $combined = max(0.50, min(2.00, $combined));

    $pp['traffic_factor'] = round($trafficFactor, 3);
    $pp['weather'] = $w;
    $pp['event'] = $evt;
    $pp['weather_factor'] = round((float)($wf['factor'] ?? 1.0), 3);
    $pp['event_factor'] = round((float)($ef['factor'] ?? 1.0), 3);
    $pp['combined_factor'] = round((float)$combined, 3);
    $pp['predicted_adjusted'] = (int)max(0, round(((int)($pp['predicted'] ?? 0)) * $combined));
  }
  unset($pp);
  $forecastItems[] = [
    'area_ref' => $ref,
    'area_label' => (string)$a['label'],
    'capacity' => (int)$a['capacity'],
    'forecast' => $pred,
    'traffic_status' => $trafficStatus,
    'traffic_factor_now' => round($trafficFactorBase, 3),
    'traffic' => $traffic,
  ];

  $next6 = array_slice($pred, 0, 6);
  $peak = 0;
  $peakHour = '';
  $baseAtPeak = 0.0;
  foreach ($next6 as $p) {
    $pv = (int)($p['predicted_adjusted'] ?? $p['predicted'] ?? 0);
    if ($pv > $peak) {
      $peak = $pv;
      $peakHour = (string)($p['hour_label'] ?? '');
      $baseAtPeak = (float)($p['baseline'] ?? 0.0);
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

$dataSource = $useObservations ? 'puv_demand_observations' : ($areaType === 'route' ? 'terminal_assignments_and_vehicles' : 'parking_transactions_and_violations');

$payload = [
  'ok' => true,
  'area_type' => $areaType,
  'hours' => $hoursAhead,
  'accuracy' => $accuracy,
  'accuracy_target' => $accuracyTarget,
  'accuracy_ok' => $accuracyOk,
  'data_points' => $points,
  'data_source' => $dataSource,
  'weather' => [
    'provider' => 'open-meteo',
    'label' => $weatherLabel,
    'lat' => $lat,
    'lon' => $lon,
    'current' => $weather['current_weather'] ?? null,
  ],
  'events' => ['country' => $eventsCountry, 'rss' => $eventsRssUrl !== '' ? true : false],
  'model' => [
    'ai_weather_weight' => $aiWeatherWeight,
    'ai_event_weight' => $aiEventWeight,
    'ai_traffic_weight' => $aiTrafficWeight,
  ],
  'spikes' => $spikes,
  'areas' => $forecastItems,
  'area_lists' => $areaLists,
];

tmm_cache_set($db, $cacheKey, $payload, 60);
echo json_encode($payload);
