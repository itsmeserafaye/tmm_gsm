<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/external_data.php';

if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "CLI only\n";
  exit(1);
}

$argvStr = implode(' ', array_slice($argv ?? [], 1));
$dryRun = strpos($argvStr, '--dry-run') !== false;
$rollbackId = null;
if (preg_match('/--rollback=(\d+)/', $argvStr, $m)) $rollbackId = (int)$m[1];

$db = db();

function tune_ensure_table(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS ai_weight_tuning_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    finished_at DATETIME DEFAULT NULL,
    status VARCHAR(40) NOT NULL,
    prev_weights JSON DEFAULT NULL,
    new_weights JSON DEFAULT NULL,
    metrics JSON DEFAULT NULL,
    error TEXT DEFAULT NULL,
    INDEX idx_started (started_at),
    INDEX idx_status (status)
  ) ENGINE=InnoDB");
}

function tune_setting(mysqli $db, string $key, string $default = ''): string {
  $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key=? LIMIT 1");
  if (!$stmt) return $default;
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $val = $row ? trim((string)($row['setting_value'] ?? '')) : '';
  return $val !== '' ? $val : $default;
}

function tune_set_setting(mysqli $db, string $key, string $value): bool {
  $stmt = $db->prepare("REPLACE INTO app_settings(setting_key, setting_value) VALUES(?,?)");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $key, $value);
  $ok = $stmt->execute();
  $stmt->close();
  return (bool)$ok;
}

function tune_insert_run(mysqli $db, string $status, array $prev, array $next, array $metrics, string $error = ''): int {
  $stmt = $db->prepare("INSERT INTO ai_weight_tuning_runs(started_at, status, prev_weights, new_weights, metrics, error) VALUES (NOW(), ?, ?, ?, ?, ?)");
  if (!$stmt) return 0;
  $p = json_encode($prev);
  $n = json_encode($next);
  $m = json_encode($metrics);
  $stmt->bind_param('sssss', $status, $p, $n, $m, $error);
  $stmt->execute();
  $id = (int)$stmt->insert_id;
  $stmt->close();
  return $id;
}

function tune_finish_run(mysqli $db, int $id, string $status, array $metrics = [], string $error = ''): void {
  $stmt = $db->prepare("UPDATE ai_weight_tuning_runs SET finished_at=NOW(), status=?, metrics=?, error=? WHERE id=?");
  if (!$stmt) return;
  $m = json_encode($metrics);
  $stmt->bind_param('sssi', $status, $m, $error, $id);
  $stmt->execute();
  $stmt->close();
}

function tune_weather_archive(mysqli $db, float $lat, float $lon, string $startDate, string $endDate): ?array {
  $cacheKey = 'weather:open-meteo:archive:v1:' . $lat . ',' . $lon . ':' . $startDate . ':' . $endDate;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;
  $url = "https://archive-api.open-meteo.com/v1/archive?latitude=" . rawurlencode((string)$lat) .
    "&longitude=" . rawurlencode((string)$lon) .
    "&start_date=" . rawurlencode($startDate) .
    "&end_date=" . rawurlencode($endDate) .
    "&hourly=temperature_2m,precipitation,precipitation_probability,weathercode" .
    "&timezone=auto" .
    "&temperature_unit=celsius" .
    "&windspeed_unit=kmh" .
    "&precipitation_unit=mm";
  $res = tmm_http_get_json($url, 20);
  if (!($res['ok'] ?? false) || !is_array($res['data'])) return null;
  $data = $res['data'];
  if (!is_array($data)) return null;
  tmm_cache_set($db, $cacheKey, $data, 24 * 3600);
  return $data;
}

function tune_holidays(mysqli $db, string $country, int $year): array {
  $cacheKey = 'events:nager:' . $country . ':' . $year;
  $cached = tmm_cache_get($db, $cacheKey);
  if (is_array($cached)) return $cached;
  $url = "https://date.nager.at/api/v3/PublicHolidays/" . rawurlencode((string)$year) . "/" . rawurlencode($country);
  $res = tmm_http_get_json($url, 20);
  if (!($res['ok'] ?? false) || !is_array($res['data'])) return [];
  $data = $res['data'];
  if (!is_array($data)) return [];
  tmm_cache_set($db, $cacheKey, $data, 7 * 24 * 3600);
  return $data;
}

function tune_holiday_dates_map(array $holidays): array {
  $map = [];
  foreach ($holidays as $h) {
    if (!is_array($h)) continue;
    $d = (string)($h['date'] ?? '');
    if ($d === '') continue;
    $map[$d] = (string)($h['localName'] ?? ($h['name'] ?? 'Holiday'));
  }
  return $map;
}

function tune_weather_impact(?float $prob, ?float $mm, ?float $temp, int $hourOfDay): array {
  $severity = 0.0;
  if ($prob !== null) $severity = max($severity, max(0.0, min(1.0, $prob / 100.0)));
  if ($mm !== null && $mm > 0.0) $severity = max($severity, max(0.0, min(1.0, $mm / 6.0)));
  $tempSeverity = 0.0;
  if ($temp !== null) {
    if ($temp >= 36) $tempSeverity = min(1.0, ($temp - 36) / 6);
    elseif ($temp <= 20) $tempSeverity = min(1.0, (20 - $temp) / 8);
  }
  $commuteBoost = ($hourOfDay >= 6 && $hourOfDay <= 9) || ($hourOfDay >= 16 && $hourOfDay <= 19);
  $scale = $commuteBoost ? 1.0 : 0.7;
  $impact = ($severity * 0.85 + $tempSeverity * 0.15) * $scale;
  return ['impact' => $impact, 'severity' => $severity, 'temp_severity' => $tempSeverity];
}

function tune_event_score(bool $isHoliday, int $hourOfDay): float {
  if (!$isHoliday) return 0.0;
  if ($hourOfDay >= 10 && $hourOfDay <= 16) return 1.0;
  if (($hourOfDay >= 6 && $hourOfDay <= 9) || ($hourOfDay >= 16 && $hourOfDay <= 19)) return -0.4;
  return 0.35;
}

function tune_clamp(float $v, float $minV, float $maxV): float {
  return max($minV, min($maxV, $v));
}

function tune_factor(float $weight, float $impact, float $minF, float $maxF): float {
  $f = 1.0 + ($weight * $impact);
  return tune_clamp($f, $minF, $maxF);
}

function tune_mape(array $points, float $wWeather, float $wEvent): array {
  $sum = 0.0;
  $n = 0;
  foreach ($points as $p) {
    $actual = (float)$p['actual'];
    if ($actual <= 0) continue;
    $pred = (float)$p['baseline'];
    $wf = tune_factor($wWeather, (float)$p['weather_impact'], 0.70, 1.35);
    $ef = tune_factor($wEvent, (float)$p['event_score'], 0.70, 1.40);
    $adj = $pred * ($wf * $ef);
    $sum += abs($actual - $adj) / $actual;
    $n++;
  }
  if ($n === 0) return ['mape' => null, 'points' => 0];
  return ['mape' => $sum / $n, 'points' => $n];
}

tune_ensure_table($db);

if ($rollbackId !== null && $rollbackId > 0) {
  $stmt = $db->prepare("SELECT prev_weights FROM ai_weight_tuning_runs WHERE id=? LIMIT 1");
  if (!$stmt) {
    echo "Failed to prepare rollback query\n";
    exit(1);
  }
  $stmt->bind_param('i', $rollbackId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $prev = $row ? json_decode((string)($row['prev_weights'] ?? ''), true) : null;
  if (!is_array($prev)) {
    echo "Rollback run not found or invalid\n";
    exit(1);
  }
  $cur = [
    'ai_weather_weight' => (float)tune_setting($db, 'ai_weather_weight', '0.12'),
    'ai_event_weight' => (float)tune_setting($db, 'ai_event_weight', '0.10'),
    'ai_traffic_weight' => (float)tune_setting($db, 'ai_traffic_weight', '1.00'),
  ];
  $runId = tune_insert_run($db, 'rollback_started', $cur, $prev, ['rollback_from' => $rollbackId], '');
  if (!$dryRun) {
    $db->begin_transaction();
    $ok = tune_set_setting($db, 'ai_weather_weight', (string)($prev['ai_weather_weight'] ?? $cur['ai_weather_weight']));
    $ok = $ok && tune_set_setting($db, 'ai_event_weight', (string)($prev['ai_event_weight'] ?? $cur['ai_event_weight']));
    $ok = $ok && tune_set_setting($db, 'ai_traffic_weight', (string)($prev['ai_traffic_weight'] ?? $cur['ai_traffic_weight']));
    if ($ok) $db->commit(); else $db->rollback();
  }
  tune_finish_run($db, $runId, $dryRun ? 'rollback_dry_run' : 'rollback_applied', ['rollback_from' => $rollbackId], '');
  echo ($dryRun ? "Rollback dry-run complete\n" : "Rollback applied\n");
  exit(0);
}

$current = [
  'ai_weather_weight' => (float)tune_setting($db, 'ai_weather_weight', '0.12'),
  'ai_event_weight' => (float)tune_setting($db, 'ai_event_weight', '0.10'),
  'ai_traffic_weight' => (float)tune_setting($db, 'ai_traffic_weight', '1.00'),
];
$runId = tune_insert_run($db, 'started', $current, $current, ['dry_run' => $dryRun], '');

$weatherLat = (float)tune_setting($db, 'weather_lat', '14.5995');
$weatherLon = (float)tune_setting($db, 'weather_lon', '120.9842');
$eventsCountry = tune_setting($db, 'events_country', 'PH');

$evalDays = 14;
$historyDays = 60;
$endTs = time();
$evalStartTs = $endTs - ($evalDays * 24 * 3600);
$historyStartTs = $endTs - ($historyDays * 24 * 3600);
$historyStartStr = date('Y-m-d H:i:s', $historyStartTs);

$obsExists = false;
$resT = $db->query("SHOW TABLES LIKE 'puv_demand_observations'");
if ($resT && $resT->fetch_row()) $obsExists = true;
if (!$obsExists) {
  tune_finish_run($db, $runId, 'skipped_no_table', ['reason' => 'puv_demand_observations_missing'], '');
  echo "Skipped: puv_demand_observations missing\n";
  exit(0);
}

$seriesByArea = [];
$sql = "
  SELECT area_type, area_ref,
         DATE_FORMAT(observed_at, '%Y-%m-%d %H:00:00') AS hour_start,
         SUM(demand_count) AS cnt
  FROM puv_demand_observations
  WHERE observed_at >= '{$historyStartStr}'
  GROUP BY area_type, area_ref, hour_start
  ORDER BY area_type, area_ref, hour_start
";
$res = $db->query($sql);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $areaType = (string)($r['area_type'] ?? '');
    $ref = (string)($r['area_ref'] ?? '');
    $hs = (string)($r['hour_start'] ?? '');
    $cnt = (int)($r['cnt'] ?? 0);
    if ($areaType === '' || $ref === '' || $hs === '') continue;
    $key = $areaType . ':' . $ref;
    if (!isset($seriesByArea[$key])) $seriesByArea[$key] = [];
    $seriesByArea[$key][] = ['hour_start' => $hs, 'cnt' => $cnt];
  }
}

$areaCount = count($seriesByArea);
if ($areaCount < 3) {
  tune_finish_run($db, $runId, 'skipped_low_data', ['areas' => $areaCount], '');
  echo "Skipped: not enough areas\n";
  exit(0);
}

$startDate = date('Y-m-d', $evalStartTs - (24 * 3600));
$endDate = date('Y-m-d', $endTs);
$weatherArchive = tune_weather_archive($db, $weatherLat, $weatherLon, $startDate, $endDate);
if (!is_array($weatherArchive) || !is_array($weatherArchive['hourly'] ?? null)) {
  tune_finish_run($db, $runId, 'skipped_external_unavailable', ['reason' => 'weather_archive_unavailable'], '');
  echo "Skipped: weather archive unavailable\n";
  exit(0);
}

$wh = $weatherArchive['hourly'];
$times = is_array($wh['time'] ?? null) ? $wh['time'] : [];
$wTemp = is_array($wh['temperature_2m'] ?? null) ? $wh['temperature_2m'] : [];
$wPrec = is_array($wh['precipitation'] ?? null) ? $wh['precipitation'] : [];
$wProb = is_array($wh['precipitation_probability'] ?? null) ? $wh['precipitation_probability'] : [];
$wCode = is_array($wh['weathercode'] ?? null) ? $wh['weathercode'] : [];
$weatherByTime = [];
foreach ($times as $idx => $t) {
  $weatherByTime[(string)$t] = [
    'temp_c' => array_key_exists($idx, $wTemp) ? (float)$wTemp[$idx] : null,
    'precip_mm' => array_key_exists($idx, $wPrec) ? (float)$wPrec[$idx] : null,
    'precip_prob' => array_key_exists($idx, $wProb) ? (float)$wProb[$idx] : null,
    'weathercode' => array_key_exists($idx, $wCode) ? (float)$wCode[$idx] : null,
  ];
}

$years = [ (int)date('Y', $evalStartTs), (int)date('Y', $endTs) ];
$years = array_values(array_unique($years));
$holidayDates = [];
foreach ($years as $y) {
  $holidayDates += tune_holiday_dates_map(tune_holidays($db, $eventsCountry, $y));
}

$points = [];
$pointsByType = ['terminal' => 0, 'route' => 0];

foreach ($seriesByArea as $areaKey => $rows) {
  usort($rows, function ($a, $b) { return strcmp($a['hour_start'], $b['hour_start']); });
  $histByHourKey = [];
  $histByHourOfDay = [];
  foreach ($rows as $idx => $r) {
    $ts = strtotime((string)$r['hour_start']);
    if ($ts === false) continue;
    $k = (int)date('w', $ts) . ':' . (int)date('G', $ts);
    $h = (int)date('G', $ts);
    $val = (int)$r['cnt'];
    if ($ts >= $evalStartTs && $ts <= $endTs) {
      $seasonList = $histByHourKey[$k] ?? [];
      $season = 0.0;
      if (!empty($seasonList)) {
        $sum = 0.0; $c = 0;
        foreach ($seasonList as $v) { $sum += (float)$v; $c++; }
        $season = $c ? ($sum / $c) : 0.0;
      }
      $recentList = $histByHourOfDay[$h] ?? [];
      $recent = 0.0;
      if (!empty($recentList)) {
        $slice = array_slice($recentList, -7);
        $sum = 0.0; $c = 0;
        foreach ($slice as $v) { $sum += (float)$v; $c++; }
        $recent = $c ? ($sum / $c) : 0.0;
      }
      $baseline = (0.75 * $season) + (0.25 * $recent);
      $target = date('Y-m-d\\TH:00', $ts);
      $w = $weatherByTime[$target] ?? null;
      if (!is_array($w)) {
        $w = ['temp_c' => null, 'precip_mm' => null, 'precip_prob' => null, 'weathercode' => null];
      }
      $impact = tune_weather_impact(
        $w['precip_prob'] !== null ? (float)$w['precip_prob'] : null,
        $w['precip_mm'] !== null ? (float)$w['precip_mm'] : null,
        $w['temp_c'] !== null ? (float)$w['temp_c'] : null,
        $h
      );
      $d = date('Y-m-d', $ts);
      $isHoliday = isset($holidayDates[$d]);
      $evtScore = tune_event_score($isHoliday, $h);
      $actual = (int)$r['cnt'];
      if ($actual > 0 && ($season > 0 || $recent > 0)) {
        $type = explode(':', $areaKey, 2)[0] ?? '';
        if ($type === 'terminal' || $type === 'route') $pointsByType[$type]++;
        $points[] = [
          'baseline' => $baseline,
          'actual' => $actual,
          'weather_impact' => (float)$impact['impact'],
          'event_score' => (float)$evtScore,
        ];
      }
    }

    if (!isset($histByHourKey[$k])) $histByHourKey[$k] = [];
    $histByHourKey[$k][] = $val;
    if (!isset($histByHourOfDay[$h])) $histByHourOfDay[$h] = [];
    $histByHourOfDay[$h][] = $val;
  }
}

if (count($points) < 300 || ($pointsByType['terminal'] + $pointsByType['route']) < 300) {
  tune_finish_run($db, $runId, 'skipped_low_data', ['points' => count($points), 'by_type' => $pointsByType], '');
  echo "Skipped: not enough evaluation points\n";
  exit(0);
}

$curEval = tune_mape($points, (float)$current['ai_weather_weight'], (float)$current['ai_event_weight']);
if ($curEval['mape'] === null) {
  tune_finish_run($db, $runId, 'skipped_low_data', ['points' => $curEval['points']], '');
  echo "Skipped: no valid points\n";
  exit(0);
}

$best = [
  'w_weather' => (float)$current['ai_weather_weight'],
  'w_event' => (float)$current['ai_event_weight'],
  'mape' => (float)$curEval['mape'],
  'points' => (int)$curEval['points'],
];

$weatherRange = [];
for ($x = -0.30; $x <= 0.30001; $x += 0.02) $weatherRange[] = round($x, 2);
$eventRange = [];
for ($x = -0.30; $x <= 0.30001; $x += 0.05) $eventRange[] = round($x, 2);

foreach ($weatherRange as $ww) {
  foreach ($eventRange as $ew) {
    $eval = tune_mape($points, (float)$ww, (float)$ew);
    if ($eval['mape'] === null) continue;
    $mape = (float)$eval['mape'];
    if ($mape < $best['mape']) {
      $best['w_weather'] = (float)$ww;
      $best['w_event'] = (float)$ew;
      $best['mape'] = $mape;
    }
  }
}

$improvePct = (($curEval['mape'] - $best['mape']) / $curEval['mape']) * 100.0;
$minImprovePct = 2.0;
if ($improvePct < $minImprovePct) {
  $metrics = [
    'status' => 'no_change',
    'eval_days' => $evalDays,
    'history_days' => $historyDays,
    'points' => count($points),
    'by_type' => $pointsByType,
    'current' => ['mape' => $curEval['mape'], 'weights' => $current],
    'best' => $best,
    'improvement_pct' => round($improvePct, 2),
    'threshold_pct' => $minImprovePct,
  ];
  tune_finish_run($db, $runId, 'no_change', $metrics, '');
  echo "No change (improvement " . round($improvePct, 2) . "%)\n";
  exit(0);
}

$maxDelta = 0.10;
$newWeather = tune_clamp($best['w_weather'], $current['ai_weather_weight'] - $maxDelta, $current['ai_weather_weight'] + $maxDelta);
$newEvent = tune_clamp($best['w_event'], $current['ai_event_weight'] - $maxDelta, $current['ai_event_weight'] + $maxDelta);

$next = [
  'ai_weather_weight' => round($newWeather, 3),
  'ai_event_weight' => round($newEvent, 3),
  'ai_traffic_weight' => (float)$current['ai_traffic_weight'],
];

$newEval = tune_mape($points, (float)$next['ai_weather_weight'], (float)$next['ai_event_weight']);
$metrics = [
  'eval_days' => $evalDays,
  'history_days' => $historyDays,
  'points' => count($points),
  'by_type' => $pointsByType,
  'current' => ['mape' => $curEval['mape'], 'weights' => $current],
  'best_grid' => $best,
  'proposed' => ['mape' => $newEval['mape'], 'weights' => $next],
  'improvement_pct' => round((($curEval['mape'] - (float)$newEval['mape']) / $curEval['mape']) * 100.0, 2),
  'max_delta' => $maxDelta,
  'min_improvement_pct' => $minImprovePct,
  'dry_run' => $dryRun,
];

if ($dryRun) {
  tune_finish_run($db, $runId, 'dry_run', $metrics, '');
  echo "Dry-run complete\n";
  exit(0);
}

$db->begin_transaction();
$ok = tune_set_setting($db, 'ai_weather_weight', (string)$next['ai_weather_weight']);
$ok = $ok && tune_set_setting($db, 'ai_event_weight', (string)$next['ai_event_weight']);
if ($ok) $db->commit(); else $db->rollback();

if (!$ok) {
  tune_finish_run($db, $runId, 'failed', $metrics, 'db_update_failed');
  echo "Failed updating settings\n";
  exit(1);
}

$stmt = $db->prepare("UPDATE ai_weight_tuning_runs SET new_weights=?, metrics=?, finished_at=NOW(), status=? WHERE id=?");
if ($stmt) {
  $n = json_encode($next);
  $m = json_encode($metrics);
  $status = 'applied';
  $stmt->bind_param('sssi', $n, $m, $status, $runId);
  $stmt->execute();
  $stmt->close();
}

echo "Applied: weather=" . $next['ai_weather_weight'] . " event=" . $next['ai_event_weight'] . "\n";

