<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

function read_csv_rows(string $path): array {
  if (!is_file($path)) return [];
  $fh = fopen($path, 'r');
  if (!$fh) return [];
  $header = fgetcsv($fh);
  if (!is_array($header)) { fclose($fh); return []; }
  $rows = [];
  while (($row = fgetcsv($fh)) !== false) {
    if (!is_array($row)) continue;
    $assoc = [];
    foreach ($header as $i => $k) {
      $k = trim((string)$k);
      if ($k === '') continue;
      $assoc[$k] = isset($row[$i]) ? trim((string)$row[$i]) : '';
    }
    $rows[] = $assoc;
  }
  fclose($fh);
  return $rows;
}

function table_exists(mysqli $db, string $name): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $ok = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  return $ok;
}

function has_column(mysqli $db, string $table, string $col): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('ss', $table, $col);
  $stmt->execute();
  $ok = (bool)($stmt->get_result()->fetch_row());
  $stmt->close();
  return $ok;
}

function ensure_terminals_table(mysqli $db): void {
  if (table_exists($db, 'terminals')) return;
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

function ensure_routes_table(mysqli $db): void {
  if (table_exists($db, 'routes')) return;
  $db->query("CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id VARCHAR(64) UNIQUE,
    route_name VARCHAR(128),
    max_vehicle_limit INT DEFAULT 50,
    status VARCHAR(32) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
}

function seed_terminals(mysqli $db, array $rows): array {
  ensure_terminals_table($db);
  $hasCity = has_column($db, 'terminals', 'city');
  $hasAddress = has_column($db, 'terminals', 'address');
  $hasType = has_column($db, 'terminals', 'type');
  $hasCapacity = has_column($db, 'terminals', 'capacity');
  $hasStatus = has_column($db, 'terminals', 'status');

  $added = 0;
  $skipped = 0;
  foreach ($rows as $r) {
    $name = trim((string)($r['name'] ?? ''));
    if ($name === '') { $skipped++; continue; }
    $city = trim((string)($r['city'] ?? 'Caloocan City'));
    $address = trim((string)($r['address'] ?? ''));
    $type = trim((string)($r['type'] ?? 'Terminal'));
    $capacity = (int)($r['capacity'] ?? 0);
    $status = trim((string)($r['status'] ?? 'Active'));

    $stmt = $db->prepare("SELECT id FROM terminals WHERE name=? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $name);
      $stmt->execute();
      $exists = (bool)$stmt->get_result()->fetch_row();
      $stmt->close();
      if ($exists) { $skipped++; continue; }
    }

    $cols = ['name'];
    $vals = ['?'];
    $types = 's';
    $params = [$name];

    if ($hasCity) { $cols[] = 'city'; $vals[] = '?'; $types .= 's'; $params[] = $city; }
    if ($hasAddress) { $cols[] = 'address'; $vals[] = '?'; $types .= 's'; $params[] = $address; }
    if ($hasType) { $cols[] = 'type'; $vals[] = '?'; $types .= 's'; $params[] = $type; }
    if ($hasCapacity) { $cols[] = 'capacity'; $vals[] = '?'; $types .= 'i'; $params[] = $capacity; }
    if ($hasStatus) { $cols[] = 'status'; $vals[] = '?'; $types .= 's'; $params[] = $status; }

    $sql = "INSERT INTO terminals (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmtIns = $db->prepare($sql);
    if (!$stmtIns) { $skipped++; continue; }
    $stmtIns->bind_param($types, ...$params);
    $ok = $stmtIns->execute();
    $stmtIns->close();
    if ($ok) $added++; else $skipped++;
  }
  return ['added' => $added, 'skipped' => $skipped];
}

function seed_routes(mysqli $db, array $rows): array {
  ensure_routes_table($db);
  if (!table_exists($db, 'lptrp_routes')) {
    $db->query("CREATE TABLE IF NOT EXISTS lptrp_routes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      route_code VARCHAR(50) NOT NULL,
      description VARCHAR(255),
      start_point VARCHAR(255),
      end_point VARCHAR(255),
      max_vehicle_capacity INT DEFAULT 0,
      current_vehicle_count INT DEFAULT 0,
      status VARCHAR(50) DEFAULT 'Approved',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
  }
  $added = 0;
  $skipped = 0;

  $stmt = $db->prepare("INSERT IGNORE INTO routes(route_id, route_name, max_vehicle_limit, status) VALUES (?,?,?,?)");
  if (!$stmt) return ['added' => 0, 'skipped' => count($rows)];
  $selL = $db->prepare("SELECT id FROM lptrp_routes WHERE route_code=? LIMIT 1");
  $insL = $db->prepare("INSERT INTO lptrp_routes(route_code, description, max_vehicle_capacity, status) VALUES (?,?,?,?)");
  foreach ($rows as $r) {
    $routeId = trim((string)($r['route_id'] ?? ''));
    if ($routeId === '') { $skipped++; continue; }
    $routeName = trim((string)($r['route_name'] ?? $routeId));
    $max = (int)($r['max_vehicle_limit'] ?? 50);
    if ($max <= 0) $max = 50;
    $status = trim((string)($r['status'] ?? 'Active'));
    if ($status === '') $status = 'Active';
    $stmt->bind_param('ssis', $routeId, $routeName, $max, $status);
    $ok = $stmt->execute();
    if ($ok && $db->affected_rows > 0) $added++; else $skipped++;

    if ($selL && $insL) {
      $selL->bind_param('s', $routeId);
      $selL->execute();
      $exists = (bool)$selL->get_result()->fetch_row();
      if (!$exists) {
        $lstatus = ($status === 'Active') ? 'Approved' : 'Pending';
        $insL->bind_param('ssis', $routeId, $routeName, $max, $lstatus);
        $insL->execute();
      }
    }
  }
  $stmt->close();
  if ($selL) $selL->close();
  if ($insL) $insL->close();
  return ['added' => $added, 'skipped' => $skipped];
}

$confirm = (string)($_POST['confirm'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="max-width:720px;margin:40px auto;font-family:system-ui,Segoe UI,Arial,sans-serif">';
  echo '<h2 style="margin:0 0 12px 0">Seed Caloocan Terminals & Routes</h2>';
  echo '<p style="margin:0 0 16px 0;color:#444">Imports CSV files from admin/data and inserts missing routes/terminals.</p>';
  echo '<div style="margin:0 0 8px 0;font-weight:700">Files</div>';
  echo '<ul style="margin:0 0 16px 18px;color:#444">';
  echo '<li>admin/data/caloocan_terminals.csv</li>';
  echo '<li>admin/data/caloocan_routes.csv</li>';
  echo '</ul>';
  echo '<form method="post">';
  echo '<label style="display:block;margin:0 0 6px 0;font-weight:700">Type SEED_CALOOCAN to confirm</label>';
  echo '<input name="confirm" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px" />';
  echo '<button type="submit" style="margin-top:12px;padding:10px 14px;border:0;border-radius:8px;background:#0f766e;color:#fff;font-weight:800">Import</button>';
  echo '</form>';
  echo '</div>';
  exit;
}

header('Content-Type: application/json');
if ($confirm !== 'SEED_CALOOCAN') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'confirm_required']);
  exit;
}

$terminals = read_csv_rows(__DIR__ . '/../data/caloocan_terminals.csv');
$routes = read_csv_rows(__DIR__ . '/../data/caloocan_routes.csv');

$termRes = seed_terminals($db, $terminals);
$routeRes = seed_routes($db, $routes);

echo json_encode([
  'ok' => true,
  'terminals' => $termRes,
  'routes' => $routeRes,
]);
