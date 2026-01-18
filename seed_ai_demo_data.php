<?php
require_once __DIR__ . '/includes/env.php';
tmm_load_env(__DIR__ . '/.env');

function connect_seed_db(): mysqli {
  $host = trim((string)getenv('TMM_DB_HOST'));
  $user = trim((string)getenv('TMM_DB_USER'));
  $pass = (string)getenv('TMM_DB_PASS');
  $name = trim((string)getenv('TMM_DB_NAME'));

  if ($host === '') $host = 'localhost';

  $candidates = [];
  if ($user !== '' && $name !== '') {
    $candidates[] = [$host, $user, $pass, $name];
  }

  $fallbackDbs = [];
  if ($name !== '') $fallbackDbs[] = $name;
  $fallbackDbs[] = 'tmm';
  $fallbackDbs[] = 'tmm_tmm';
  $fallbackDbs[] = 'tmm_db';

  foreach (array_values(array_unique($fallbackDbs)) as $dbName) {
    $candidates[] = [$host, $user !== '' ? $user : 'root', $pass, $dbName];
    $candidates[] = [$host, 'root', '', $dbName];
  }

  $lastError = '';
  foreach ($candidates as $cfg) {
    [$h, $u, $p, $n] = $cfg;
    if ($u === '' || $n === '') continue;
    try {
      $conn = @new mysqli($h, $u, $p, $n);
      if ($conn->connect_error) {
        $lastError = $conn->connect_error;
        continue;
      }
      $conn->set_charset('utf8mb4');
      return $conn;
    } catch (Throwable $e) {
      $lastError = $e->getMessage();
      continue;
    }
  }

  echo "Unable to connect to the database for seeding.\n";
  echo "Tip: set TMM_DB_HOST / TMM_DB_USER / TMM_DB_PASS / TMM_DB_NAME for CLI, or run this from the web server context.\n";
  if ($lastError !== '') echo "Last error: " . $lastError . "\n";
  exit(1);
}

$db = connect_seed_db();

function ensure_table_terminals(mysqli $db): void {
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

function ensure_table_routes(mysqli $db): void {
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
}

function ensure_table_observations(mysqli $db): void {
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

function ensure_routes_columns(mysqli $db): void {
  $cols = [
    'origin' => "VARCHAR(100)",
    'destination' => "VARCHAR(100)",
  ];
  foreach ($cols as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM routes LIKE '" . $db->real_escape_string($col) . "'");
    if ($check && $check->num_rows === 0) {
      $db->query("ALTER TABLE routes ADD COLUMN $col $def");
    }
  }
}

function stable_noise(string $key, int $min, int $max): int {
  $h = crc32($key);
  if ($h < 0) $h = -$h;
  $span = max(1, ($max - $min) + 1);
  return $min + ($h % $span);
}

function is_payday(string $dateYmd): bool {
  $d = (int)date('j', strtotime($dateYmd));
  if ($d === 15) return true;
  if ($d === 30) return true;
  $lastDay = (int)date('t', strtotime($dateYmd));
  return $d === $lastDay;
}

function demand_profile(string $label, string $areaType): float {
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

function compute_demand(string $areaType, string $label, string $dateYmd, int $hour): int {
  $dow = (int)date('N', strtotime($dateYmd)); // 1=Mon
  $scale = demand_profile($label, $areaType);

  $base = 18.0;
  if ($hour <= 6) $base = 14.0;
  elseif ($hour >= 7 && $hour <= 9) $base = ($hour === 8) ? 120.0 : 95.0;
  elseif ($hour >= 10 && $hour <= 15) $base = 45.0;
  elseif ($hour === 16) $base = 58.0;
  elseif ($hour >= 17 && $hour <= 19) $base = ($hour === 18) ? 130.0 : 105.0;
  elseif ($hour >= 20) $base = 32.0;

  $dayFactor = 1.0;
  if ($dow === 5) $dayFactor = 1.10; // Fri
  if ($dow === 6) { // Sat
    $dayFactor = ($hour >= 10 && $hour <= 16) ? 1.15 : 0.90;
  }
  if ($dow === 7) $dayFactor = 0.70; // Sun

  $payFactor = 1.0;
  if (is_payday($dateYmd) && $hour >= 16 && $hour <= 21) $payFactor = 1.15;

  $eventFactor = 1.0;
  $lastSat = date('Y-m-d', strtotime('last saturday'));
  if ($dateYmd === $lastSat && $hour >= 16 && $hour <= 19) $eventFactor = 1.25;

  $raw = $base * $scale * $dayFactor * $payFactor * $eventFactor;
  $noise = stable_noise($areaType . '|' . $label . '|' . $dateYmd . '|' . $hour, -10, 10);
  $raw = $raw + ($raw * ($noise / 100.0));
  $out = (int)round(max(0.0, $raw));
  return $out;
}

ensure_table_terminals($db);
ensure_table_routes($db);
ensure_routes_columns($db);
ensure_table_observations($db);

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
while ($resTerminals && ($r = $resTerminals->fetch_assoc())) {
  $terminals[] = ['ref' => (string)$r['id'], 'label' => (string)$r['name']];
}

$routes = [];
$resRoutes = $db->query("SELECT route_id, route_name, origin, destination FROM routes WHERE status IS NULL OR status='Active' ORDER BY route_name LIMIT 10");
while ($resRoutes && ($r = $resRoutes->fetch_assoc())) {
  $routes[] = ['ref' => (string)$r['route_id'], 'label' => (string)$r['route_name']];
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

$daysBack = 21;
$hours = range(5, 22);
$start = new DateTimeImmutable(date('Y-m-d', strtotime('-' . $daysBack . ' days')));
$end = new DateTimeImmutable(date('Y-m-d'));

$inserted = 0;

$stmtUpsert = $db->prepare("INSERT INTO puv_demand_observations (area_type, area_ref, observed_at, demand_count, source)
  VALUES (?, ?, ?, ?, 'demo')
  ON DUPLICATE KEY UPDATE demand_count=VALUES(demand_count), source=VALUES(source)");
if (!$stmtUpsert) {
  echo "Failed to prepare upsert statement.\n";
  exit(1);
}

for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
  $dateYmd = $d->format('Y-m-d');
  foreach ($terminals as $t) {
    foreach ($hours as $h) {
      $observedAt = $dateYmd . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00';
      $cnt = compute_demand('terminal', $t['label'], $dateYmd, $h);
      $type = 'terminal';
      $stmtUpsert->bind_param('sssi', $type, $t['ref'], $observedAt, $cnt);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
  foreach ($routes as $r) {
    foreach ($hours as $h) {
      $observedAt = $dateYmd . ' ' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00:00';
      $cnt = compute_demand('route', $r['label'], $dateYmd, $h);
      $type = 'route';
      $stmtUpsert->bind_param('sssi', $type, $r['ref'], $observedAt, $cnt);
      if ($stmtUpsert->execute()) $inserted++;
    }
  }
}

$stmtUpsert->close();

echo "Seeded/updated $inserted hourly AI demo observations.\n";
echo "Go to Dashboard → Data Inputs / Forecast to show the AI working.\n";
?>
