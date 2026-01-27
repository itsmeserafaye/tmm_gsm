<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$db = db();
if (php_sapi_name() === 'cli') {
  if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  if (isset($argv) && is_array($argv)) {
    foreach ($argv as $i => $arg) {
      if ($i === 0) continue;
      if (!is_string($arg) || strpos($arg, '=') === false) continue;
      [$k, $v] = explode('=', $arg, 2);
      $k = trim((string)$k);
      if ($k !== '') $_GET[$k] = $v;
    }
  }
  if (empty($_SESSION['user_id'])) $_SESSION['user_id'] = 1;
  if (empty($_SESSION['role'])) $_SESSION['role'] = 'SuperAdmin';
}
require_role(['SuperAdmin']);
header('Content-Type: text/plain; charset=utf-8');

function tmm_ai_demo_ensure_terminals(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100),
    address TEXT,
    type ENUM('Terminal', 'Parking', 'LoadingBay') DEFAULT 'Terminal',
    capacity INT DEFAULT 0,
    status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
}

function tmm_ai_demo_ensure_routes(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id VARCHAR(64) UNIQUE,
    route_name VARCHAR(255) NOT NULL,
    max_vehicle_limit INT DEFAULT 50,
    origin VARCHAR(100),
    destination VARCHAR(100),
    status VARCHAR(32) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  foreach (['origin' => "VARCHAR(100)", 'destination' => "VARCHAR(100)"] as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM routes LIKE '" . $db->real_escape_string($col) . "'");
    if ($check && $check->num_rows === 0) $db->query("ALTER TABLE routes ADD COLUMN $col $def");
  }
}

function tmm_ai_demo_ensure_observations(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS puv_demand_observations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_type ENUM('terminal','route','parking_area') NOT NULL,
    area_ref VARCHAR(128) NOT NULL,
    observed_at DATETIME NOT NULL,
    demand_count INT NOT NULL DEFAULT 0,
    source VARCHAR(32) NOT NULL DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_area_hour (area_type, area_ref, observed_at),
    INDEX idx_area_time (area_type, area_ref, observed_at)
  ) ENGINE=InnoDB");
}

function tmm_ai_demo_noise(string $key, int $min, int $max): int {
  $h = crc32($key);
  if ($h < 0) $h = -$h;
  $span = max(1, ($max - $min) + 1);
  return $min + ($h % $span);
}

function tmm_ai_demo_noise_f(string $key, float $min, float $max): float {
  $h = crc32($key);
  if ($h < 0) $h = -$h;
  $u = ($h % 1000000) / 1000000.0;
  return $min + (($max - $min) * $u);
}

function tmm_ai_demo_is_payday(string $dateYmd): bool {
  $d = (int)date('j', strtotime($dateYmd));
  if ($d === 15) return true;
  if ($d === 30) return true;
  $lastDay = (int)date('t', strtotime($dateYmd));
  return $d === $lastDay;
}

function tmm_ai_demo_profile(string $label, string $areaType): float {
  $s = strtolower($label);
  $scale = 1.0;
  if (strpos($s, 'central') !== false || strpos($s, 'integrated') !== false) $scale = 1.45;
  elseif (strpos($s, 'north') !== false || strpos($s, 'bound') !== false) $scale = 1.20;
  elseif (strpos($s, 'barangay') !== false || strpos($s, 'hub') !== false) $scale = 0.80;

  if ($areaType === 'route') {
    if (strpos($s, 'loop') !== false) $scale = 1.25;
    elseif (strpos($s, 'corridor') !== false) $scale = 1.10;
    elseif (strpos($s, 'spur') !== false) $scale = 0.95;
  }
  return $scale;
}

function tmm_ai_demo_gauss(float $x, float $mu, float $sigma): float {
  if ($sigma <= 0.0) return 0.0;
  $z = ($x - $mu) / $sigma;
  return exp(-0.5 * $z * $z);
}

function tmm_ai_demo_hour_weights(): array {
  $w = [];
  $max = 0.0;
  for ($h = 0; $h <= 23; $h++) {
    $morning = 1.15 * tmm_ai_demo_gauss((float)$h, 8.0, 1.15);
    $evening = 1.30 * tmm_ai_demo_gauss((float)$h, 18.0, 1.20);
    $midday = 0.42 * tmm_ai_demo_gauss((float)$h, 12.5, 2.25);
    $late = 0.22 * tmm_ai_demo_gauss((float)$h, 21.0, 1.60);
    $base = 0.06;
    $val = $base + $morning + $evening + $midday + $late;
    $w[$h] = $val;
    if ($val > $max) $max = $val;
  }
  if ($max <= 0.0) $max = 1.0;
  foreach ($w as $h => $val) $w[$h] = $val / $max;
  return $w;
}

function tmm_ai_demo_month_factor(int $month, int $hour): float {
  $wetMonths = [6, 7, 8, 9, 10];
  $wet = in_array($month, $wetMonths, true);
  if (!$wet) return 1.0;
  $commute = ($hour >= 6 && $hour <= 9) || ($hour >= 16 && $hour <= 19);
  return $commute ? 1.06 : 1.02;
}

function tmm_ai_demo_day_factor(int $dowN, int $hour): float {
  $day = 1.0;
  if ($dowN === 1) $day = 1.02;
  if ($dowN === 5) $day = 1.10;
  if ($dowN === 6) $day = ($hour >= 10 && $hour <= 16) ? 1.05 : 0.92;
  if ($dowN === 7) $day = 0.72;
  return $day;
}

function tmm_ai_demo_area_peak_base(string $areaType, string $label, int $capacity): float {
  $scale = tmm_ai_demo_profile($label, $areaType);
  if ($areaType === 'terminal') {
    $cap = max(30, min(800, $capacity > 0 ? $capacity : 220));
    $peak = 55.0 + ($cap * 0.45);
    return $peak * $scale;
  }
  $cap = max(8, min(140, $capacity > 0 ? $capacity : 45));
  $peak = 38.0 + ($cap * 4.2);
  return $peak * $scale;
}

function tmm_ai_demo_special_event_factor(string $label, string $dateYmd, int $hour): float {
  $monthKey = date('Y-m', strtotime($dateYmd));
  $day = (int)date('j', strtotime($dateYmd));
  $pick = (int)(abs((int)crc32($label . '|' . $monthKey)) % 28) + 1;
  if ($day !== $pick) return 1.0;
  if ($hour >= 16 && $hour <= 22) return 1.20;
  if ($hour >= 11 && $hour <= 14) return 1.10;
  return 1.0;
}

function tmm_ai_demo_demand(string $areaType, string $label, int $capacity, string $dateYmd, int $hour, array $hourW): int {
  $dow = (int)date('N', strtotime($dateYmd));
  $month = (int)date('n', strtotime($dateYmd));
  $peakBase = tmm_ai_demo_area_peak_base($areaType, $label, $capacity);
  $w = $hourW[$hour] ?? 0.08;
  $raw = $peakBase * max(0.03, (float)$w);

  $dayFactor = tmm_ai_demo_day_factor($dow, $hour);
  $monthFactor = tmm_ai_demo_month_factor($month, $hour);
  $payFactor = 1.0;
  if (tmm_ai_demo_is_payday($dateYmd) && $hour >= 16 && $hour <= 21) $payFactor = 1.15;
  $eventFactor = tmm_ai_demo_special_event_factor($label, $dateYmd, $hour);
  $raw = $raw * $dayFactor * $monthFactor * $payFactor * $eventFactor;

  $dailyNoisePct = tmm_ai_demo_noise_f($areaType . '|' . $label . '|daily|' . $dateYmd, -0.09, 0.09);
  $raw = $raw * (1.0 + $dailyNoisePct);

  $hourNoisePct = tmm_ai_demo_noise_f($areaType . '|' . $label . '|hour|' . $dateYmd . '|' . $hour, -0.06, 0.06);
  $raw = $raw * (1.0 + $hourNoisePct);

  $shock = tmm_ai_demo_noise($areaType . '|' . $label . '|shock|' . $dateYmd, 0, 99);
  if ($shock >= 98) {
    if (($hour >= 6 && $hour <= 9) || ($hour >= 16 && $hour <= 19)) $raw = $raw * 1.35;
  }
  return (int)round(max(0.0, $raw));
}

tmm_ai_demo_ensure_terminals($db);
tmm_ai_demo_ensure_routes($db);
tmm_ai_demo_ensure_observations($db);

$resT = $db->query("SELECT COUNT(*) AS c FROM terminals");
$tCount = 0;
if ($resT && ($r = $resT->fetch_assoc())) $tCount = (int)($r['c'] ?? 0);
if ($tCount === 0) {
  $db->query("INSERT INTO terminals (name, city, address, type, capacity, status) VALUES
    ('Central Integrated Terminal', 'Caloocan City', 'Rizal Ave Ext, Caloocan City', 'Terminal', 500, 'Active'),
    ('North Bound Terminal', 'Caloocan City', 'EDSA Extension, Caloocan City', 'Terminal', 320, 'Active'),
    ('Barangay 101 Tricycle Hub', 'Caloocan City', 'Brgy 101, Caloocan City', 'Terminal', 80, 'Active')
  ");
}

$terminals = [];
$resTerminals = $db->query("SELECT id, name, city, address, capacity FROM terminals WHERE status IS NULL OR status='Active' ORDER BY name");
while ($resTerminals && ($r = $resTerminals->fetch_assoc())) {
  $terminals[] = [
    'ref' => (string)$r['id'],
    'label' => (string)$r['name'],
    'capacity' => (int)($r['capacity'] ?? 0),
    'city' => (string)($r['city'] ?? ''),
    'address' => (string)($r['address'] ?? ''),
  ];
}

$routes = [];
$resRoutes = $db->query("SELECT route_id, route_name, origin, destination, max_vehicle_limit FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name");
while ($resRoutes && ($r = $resRoutes->fetch_assoc())) {
  $routes[] = [
    'ref' => (string)$r['route_id'],
    'label' => (string)$r['route_name'],
    'origin' => (string)($r['origin'] ?? ''),
    'destination' => (string)($r['destination'] ?? ''),
    'capacity' => (int)($r['max_vehicle_limit'] ?? 0),
  ];
}

if (empty($routes)) {
  $db->query("INSERT IGNORE INTO routes (route_id, route_name, max_vehicle_limit, origin, destination, status) VALUES
    ('R-01','Monumento – City Hall Corridor',45,'Monumento, Caloocan City','Caloocan City Hall, Caloocan City','Active'),
    ('R-02','EDSA North Loop',38,'Balintawak, Quezon City','Monumento, Caloocan City','Active'),
    ('R-03','Bagong Silang – Monumento Express',55,'Bagong Silang, Caloocan City','Monumento, Caloocan City','Active'),
    ('R-04','University Belt Link',32,'Caloocan City','University Belt, Manila','Active'),
    ('R-05','Divisoria Market Run',40,'Monumento, Caloocan City','Divisoria, Manila','Active'),
    ('R-06','South Station Connector',28,'Caloocan City','EDSA, Pasay City','Active')
  ");
  $routes = [];
  $resRoutes = $db->query("SELECT route_id, route_name, origin, destination, max_vehicle_limit FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name");
  while ($resRoutes && ($r = $resRoutes->fetch_assoc())) {
    $routes[] = [
      'ref' => (string)$r['route_id'],
      'label' => (string)$r['route_name'],
      'origin' => (string)($r['origin'] ?? ''),
      'destination' => (string)($r['destination'] ?? ''),
      'capacity' => (int)($r['max_vehicle_limit'] ?? 0),
    ];
  }
}

if (!empty($routes)) {
  foreach ($routes as $rt) {
    $rid = $rt['ref'];
    $stmt = $db->prepare("UPDATE routes SET origin=COALESCE(NULLIF(origin,''), ?), destination=COALESCE(NULLIF(destination,''), ?) WHERE route_id=?");
    if ($stmt) {
      $origin = 'City Center';
      $dest = 'North District';
      $name = strtolower($rt['label']);
      if (strpos($name, 'east') !== false) { $origin = 'City Center'; $dest = 'East District'; }
      elseif (strpos($name, 'north') !== false) { $origin = 'City Center'; $dest = 'North District'; }
      elseif (strpos($name, 'loop') !== false) { $origin = 'City Center'; $dest = 'City Center'; }
      $stmt->bind_param('sss', $origin, $dest, $rid);
      $stmt->execute();
      $stmt->close();
    }
  }
}

$daysBack = (int)($_GET['days'] ?? 180);
if ($daysBack < 30) $daysBack = 30;
if ($daysBack > 365) $daysBack = 365;
$source = trim((string)($_GET['source'] ?? 'synthetic'));
$source = preg_replace('/[^a-zA-Z0-9_\-]/', '', $source);
if ($source === '') $source = 'synthetic';
$wipe = ((string)($_GET['wipe'] ?? '')) === '1';

$hours = range(0, 23);
$end = new DateTimeImmutable(date('Y-m-d'));
$start = $end->modify('-' . $daysBack . ' days');
$hourW = tmm_ai_demo_hour_weights();

if ($wipe) {
  $stmtDel = $db->prepare("DELETE FROM puv_demand_observations WHERE source=?");
  if ($stmtDel) {
    $stmtDel->bind_param('s', $source);
    $stmtDel->execute();
    $stmtDel->close();
  }
}

$stmtUpsert = $db->prepare("INSERT INTO puv_demand_observations (area_type, area_ref, observed_at, demand_count, source)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE demand_count=VALUES(demand_count), source=VALUES(source)");
if (!$stmtUpsert) {
  echo "Failed to prepare upsert statement.\n";
  exit;
}

$inserted = 0;
for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
  $dateYmd = $d->format('Y-m-d');
  foreach ($terminals as $t) {
    foreach ($hours as $h) {
      $observedAt = $dateYmd . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00';
      $cnt = tmm_ai_demo_demand('terminal', (string)$t['label'], (int)($t['capacity'] ?? 0), $dateYmd, (int)$h, $hourW);
      $type = 'terminal';
      $stmtUpsert->bind_param('sssis', $type, $t['ref'], $observedAt, $cnt, $source);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
  foreach ($routes as $r) {
    foreach ($hours as $h) {
      $observedAt = $dateYmd . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00';
      $cnt = tmm_ai_demo_demand('route', (string)$r['label'], (int)($r['capacity'] ?? 0), $dateYmd, (int)$h, $hourW);
      $type = 'route';
      $stmtUpsert->bind_param('sssis', $type, $r['ref'], $observedAt, $cnt, $source);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
}

$stmtUpsert->close();

echo "Seeded/updated $inserted hourly demand observations.\n";
echo "Source: $source\n";
echo "Range: " . $start->format('Y-m-d') . " to " . $end->format('Y-m-d') . "\n";
echo "Terminals: " . count($terminals) . "\n";
echo "Routes: " . count($routes) . "\n";
echo "Dashboard: Forecast + Insights will use puv_demand_observations when available.\n";
