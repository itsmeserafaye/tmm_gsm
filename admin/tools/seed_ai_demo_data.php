<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$db = db();
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

function tmm_ai_demo_demand(string $areaType, string $label, string $dateYmd, int $hour): int {
  $dow = (int)date('N', strtotime($dateYmd));
  $scale = tmm_ai_demo_profile($label, $areaType);

  $base = 18.0;
  if ($hour <= 6) $base = 14.0;
  elseif ($hour >= 7 && $hour <= 9) $base = ($hour === 8) ? 120.0 : 95.0;
  elseif ($hour >= 10 && $hour <= 15) $base = 45.0;
  elseif ($hour === 16) $base = 58.0;
  elseif ($hour >= 17 && $hour <= 19) $base = ($hour === 18) ? 130.0 : 105.0;
  elseif ($hour >= 20) $base = 32.0;

  $dayFactor = 1.0;
  if ($dow === 5) $dayFactor = 1.10;
  if ($dow === 6) $dayFactor = ($hour >= 10 && $hour <= 16) ? 1.15 : 0.90;
  if ($dow === 7) $dayFactor = 0.70;

  $payFactor = 1.0;
  if (tmm_ai_demo_is_payday($dateYmd) && $hour >= 16 && $hour <= 21) $payFactor = 1.15;

  $eventFactor = 1.0;
  $lastSat = date('Y-m-d', strtotime('last saturday'));
  if ($dateYmd === $lastSat && $hour >= 16 && $hour <= 19) $eventFactor = 1.25;

  $raw = $base * $scale * $dayFactor * $payFactor * $eventFactor;
  $noise = tmm_ai_demo_noise($areaType . '|' . $label . '|' . $dateYmd . '|' . $hour, -10, 10);
  $raw = $raw + ($raw * ($noise / 100.0));
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
$resTerminals = $db->query("SELECT id, name FROM terminals WHERE status IS NULL OR status='Active' ORDER BY name LIMIT 10");
while ($resTerminals && ($r = $resTerminals->fetch_assoc())) $terminals[] = ['ref' => (string)$r['id'], 'label' => (string)$r['name']];

$routes = [];
$resRoutes = $db->query("SELECT route_id, route_name, origin, destination FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name LIMIT 10");
while ($resRoutes && ($r = $resRoutes->fetch_assoc())) $routes[] = ['ref' => (string)$r['route_id'], 'label' => (string)$r['route_name']];

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

$daysBack = 21;
$hours = range(5, 22);
$start = new DateTimeImmutable(date('Y-m-d', strtotime('-' . $daysBack . ' days')));
$end = new DateTimeImmutable(date('Y-m-d'));

$stmtUpsert = $db->prepare("INSERT INTO puv_demand_observations (area_type, area_ref, observed_at, demand_count, source)
  VALUES (?, ?, ?, ?, 'demo')
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
      $cnt = tmm_ai_demo_demand('terminal', $t['label'], $dateYmd, $h);
      $type = 'terminal';
      $stmtUpsert->bind_param('sssi', $type, $t['ref'], $observedAt, $cnt);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
  foreach ($routes as $r) {
    foreach ($hours as $h) {
      $observedAt = $dateYmd . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00';
      $cnt = tmm_ai_demo_demand('route', $r['label'], $dateYmd, $h);
      $type = 'route';
      $stmtUpsert->bind_param('sssi', $type, $r['ref'], $observedAt, $cnt);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
}

$stmtUpsert->close();

echo "Seeded/updated $inserted hourly AI demo observations.\n";
echo "You can now go to Dashboard to show Forecast + Insights.\n";

