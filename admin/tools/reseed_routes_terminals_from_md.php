<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

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

function ensure_column(mysqli $db, string $table, string $col, string $ddl): void {
  if (has_column($db, $table, $col)) return;
  $db->query("ALTER TABLE `$table` ADD COLUMN $ddl");
}

function truncate_if_exists(mysqli $db, string $table): void {
  if (!table_exists($db, $table)) return;
  $db->query("TRUNCATE TABLE `$table`");
}

function seed_terminals(mysqli $db, array $terminals): void {
  ensure_column($db, 'terminals', 'category', "category VARCHAR(100) DEFAULT NULL");

  $hasLocation = has_column($db, 'terminals', 'location');
  $hasCity = has_column($db, 'terminals', 'city');
  $hasAddress = has_column($db, 'terminals', 'address');
  $hasType = has_column($db, 'terminals', 'type');
  $hasCapacity = has_column($db, 'terminals', 'capacity');
  $hasCategory = has_column($db, 'terminals', 'category');

  foreach ($terminals as $t) {
    $name = (string)$t['name'];
    $location = (string)$t['location'];
    $city = (string)$t['city'];
    $address = (string)$t['address'];
    $type = (string)$t['type'];
    $capacity = (int)$t['capacity'];
    $category = (string)$t['category'];

    $cols = ['name'];
    $vals = ['?'];
    $types = 's';
    $params = [$name];

    if ($hasLocation) { $cols[] = 'location'; $vals[] = '?'; $types .= 's'; $params[] = $location; }
    if ($hasCity) { $cols[] = 'city'; $vals[] = '?'; $types .= 's'; $params[] = $city; }
    if ($hasAddress) { $cols[] = 'address'; $vals[] = '?'; $types .= 's'; $params[] = $address; }
    if ($hasCapacity) { $cols[] = 'capacity'; $vals[] = '?'; $types .= 'i'; $params[] = $capacity; }
    if ($hasType) { $cols[] = 'type'; $vals[] = '?'; $types .= 's'; $params[] = $type; }
    if ($hasCategory) { $cols[] = 'category'; $vals[] = '?'; $types .= 's'; $params[] = $category; }

    $sql = "INSERT INTO terminals (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) continue;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
  }
}

function seed_routes(mysqli $db, array $routes): void {
  ensure_column($db, 'routes', 'fare_min', "fare_min DECIMAL(10,2) DEFAULT NULL");
  ensure_column($db, 'routes', 'fare_max', "fare_max DECIMAL(10,2) DEFAULT NULL");

  $hasRouteCode = has_column($db, 'routes', 'route_code');
  $hasVehicleType = has_column($db, 'routes', 'vehicle_type');
  $hasOrigin = has_column($db, 'routes', 'origin');
  $hasDest = has_column($db, 'routes', 'destination');
  $hasStructure = has_column($db, 'routes', 'structure');
  $hasFare = has_column($db, 'routes', 'fare');
  $hasFareMin = has_column($db, 'routes', 'fare_min');
  $hasFareMax = has_column($db, 'routes', 'fare_max');
  $hasStatus = has_column($db, 'routes', 'status');
  $hasMaxLimit = has_column($db, 'routes', 'max_vehicle_limit');

  foreach ($routes as $r) {
    $routeId = (string)$r['route_id'];
    $routeName = (string)$r['route_name'];
    $vehicleType = (string)$r['vehicle_type'];
    $origin = (string)$r['origin'];
    $destination = (string)$r['destination'];
    $structure = (string)$r['structure'];
    $fareMin = $r['fare_min'] !== null ? (float)$r['fare_min'] : null;
    $fareMax = $r['fare_max'] !== null ? (float)$r['fare_max'] : null;
    $status = (string)$r['status'];
    $maxLimit = (int)$r['max_vehicle_limit'];

    $cols = ['route_id', 'route_name'];
    $vals = ['?', '?'];
    $types = 'ss';
    $params = [$routeId, $routeName];

    if ($hasRouteCode) { $cols[] = 'route_code'; $vals[] = '?'; $types .= 's'; $params[] = $routeId; }
    if ($hasVehicleType) { $cols[] = 'vehicle_type'; $vals[] = '?'; $types .= 's'; $params[] = $vehicleType; }
    if ($hasOrigin) { $cols[] = 'origin'; $vals[] = '?'; $types .= 's'; $params[] = $origin; }
    if ($hasDest) { $cols[] = 'destination'; $vals[] = '?'; $types .= 's'; $params[] = $destination; }
    if ($hasStructure) { $cols[] = 'structure'; $vals[] = '?'; $types .= 's'; $params[] = $structure; }
    if ($hasFareMin) { $cols[] = 'fare_min'; $vals[] = '?'; $types .= 'd'; $params[] = $fareMin; }
    if ($hasFareMax) { $cols[] = 'fare_max'; $vals[] = '?'; $types .= 'd'; $params[] = $fareMax; }
    if ($hasFare) { $cols[] = 'fare'; $vals[] = '?'; $types .= 'd'; $params[] = $fareMin; }
    if ($hasMaxLimit) { $cols[] = 'max_vehicle_limit'; $vals[] = '?'; $types .= 'i'; $params[] = $maxLimit; }
    if ($hasStatus) { $cols[] = 'status'; $vals[] = '?'; $types .= 's'; $params[] = $status; }

    $sql = "INSERT INTO routes (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) continue;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
  }
}

function seed_terminal_routes(mysqli $db, array $mappings): void {
  $stmtT = $db->prepare("SELECT id FROM terminals WHERE name=? LIMIT 1");
  $stmtIns = $db->prepare("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) VALUES (?, ?)");
  if (!$stmtT || !$stmtIns) return;

  foreach ($mappings as $m) {
    $terminalName = (string)$m['terminal_name'];
    $routeId = (string)$m['route_id'];

    $stmtT->bind_param('s', $terminalName);
    $stmtT->execute();
    $row = $stmtT->get_result()->fetch_assoc();
    $terminalId = (int)($row['id'] ?? 0);
    if ($terminalId <= 0) continue;

    $stmtIns->bind_param('is', $terminalId, $routeId);
    $stmtIns->execute();
  }

  $stmtT->close();
  $stmtIns->close();
}

function seed_lptrp_routes(mysqli $db, array $routes): void {
  if (!table_exists($db, 'lptrp_routes')) return;
  $hasRouteName = has_column($db, 'lptrp_routes', 'route_name');
  $hasStart = has_column($db, 'lptrp_routes', 'start_point');
  $hasEnd = has_column($db, 'lptrp_routes', 'end_point');
  $hasDesc = has_column($db, 'lptrp_routes', 'description');
  $hasMax = has_column($db, 'lptrp_routes', 'max_vehicle_capacity');
  $hasCur = has_column($db, 'lptrp_routes', 'current_vehicle_count');
  $hasApproval = has_column($db, 'lptrp_routes', 'approval_status');
  $hasStatus = has_column($db, 'lptrp_routes', 'status');

  foreach ($routes as $r) {
    $routeCode = (string)$r['route_id'];
    $routeName = (string)$r['route_name'];
    $start = (string)$r['origin'];
    $end = (string)$r['destination'];
    $desc = (string)$r['vehicle_type'];

    $cols = ['route_code'];
    $vals = ['?'];
    $types = 's';
    $params = [$routeCode];
    if ($hasStart) { $cols[] = 'start_point'; $vals[] = '?'; $types .= 's'; $params[] = $start; }
    if ($hasEnd) { $cols[] = 'end_point'; $vals[] = '?'; $types .= 's'; $params[] = $end; }
    if ($hasRouteName) { $cols[] = 'route_name'; $vals[] = '?'; $types .= 's'; $params[] = $routeName; }
    if ($hasDesc) { $cols[] = 'description'; $vals[] = '?'; $types .= 's'; $params[] = $desc; }
    if ($hasMax) { $cols[] = 'max_vehicle_capacity'; $vals[] = '?'; $types .= 'i'; $params[] = 0; }
    if ($hasCur) { $cols[] = 'current_vehicle_count'; $vals[] = '?'; $types .= 'i'; $params[] = 0; }
    if ($hasApproval) { $cols[] = 'approval_status'; $vals[] = '?'; $types .= 's'; $params[] = 'Approved'; }
    if ($hasStatus) { $cols[] = 'status'; $vals[] = '?'; $types .= 's'; $params[] = 'Approved'; }

    $sql = "INSERT INTO lptrp_routes (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) continue;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
  }
}

$confirm = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = trim((string)($_POST['confirm'] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<h2>Reseed Routes & Terminals (Caloocan)</h2>';
  echo '<p>This will delete existing data in routes/terminals and related tables, then seed based on docs/routesandterminals.md.</p>';
  echo '<form method="post"><label>Type RESET to confirm:</label> <input name="confirm" /> <button type="submit">Run</button></form>';
  exit;
}

if ($confirm !== 'RESET') {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'confirm_required']);
  exit;
}

$terminals = [
  ['name' => 'Victory Liner - Caloocan (Monumento)', 'location' => 'Monumento', 'city' => 'Caloocan City', 'address' => 'Monumento area', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Provincial Bus Terminal'],
  ['name' => 'Baliwag Transit - Caloocan', 'location' => 'Caloocan', 'city' => 'Caloocan City', 'address' => 'Caloocan area', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Provincial Bus Terminal'],
  ['name' => 'Monumento Carousel Terminal', 'location' => 'Monumento', 'city' => 'Caloocan City', 'address' => 'EDSA Carousel - Monumento', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'City Transport Hub'],
  ['name' => 'SM City Caloocan Terminal', 'location' => 'North Caloocan', 'city' => 'Caloocan City', 'address' => 'SM City Caloocan', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'District Transport Terminal'],
  ['name' => 'Deparo UV Express Terminal', 'location' => 'Deparo', 'city' => 'Caloocan City', 'address' => 'Deparo', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'District Transport Terminal'],
  ['name' => 'Sangandaan / City Hall Jeep Terminal', 'location' => 'Sangandaan', 'city' => 'Caloocan City', 'address' => 'Sangandaan / City Hall', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Bagumbong - Novaliches Jeep Terminal', 'location' => 'Bagumbong', 'city' => 'Caloocan City', 'address' => 'Bagumbong / Novaliches', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Bagumbong Tricycle Terminal', 'location' => 'Bagumbong', 'city' => 'Caloocan City', 'address' => 'Bagumbong', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Deparo Tricycle Terminal', 'location' => 'Deparo', 'city' => 'Caloocan City', 'address' => 'Deparo', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Camarin Tricycle Terminal', 'location' => 'Camarin', 'city' => 'Caloocan City', 'address' => 'Camarin', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Tala Tricycle Terminal', 'location' => 'Tala', 'city' => 'Caloocan City', 'address' => 'Tala (Near Tala Hospital)', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Sangandaan Tricycle Terminal', 'location' => 'Sangandaan', 'city' => 'Caloocan City', 'address' => 'Sangandaan', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => 'Grace Park Tricycle Terminal', 'location' => 'Grace Park', 'city' => 'Caloocan City', 'address' => 'Grace Park', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
  ['name' => '5th Avenue Tricycle Terminal', 'location' => '5th Avenue', 'city' => 'Caloocan City', 'address' => '5th Avenue', 'capacity' => 0, 'type' => 'Terminal', 'category' => 'Barangay Transport Terminal'],
];

$routes = [
  ['route_id' => 'BUS-VICTORY-OLONGAPO', 'route_name' => 'Caloocan - Olongapo', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Olongapo', 'structure' => 'Point-to-Point', 'fare_min' => 300, 'fare_max' => 350, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-VICTORY-IBA_ZAMBALES', 'route_name' => 'Caloocan - Iba, Zambales', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Iba, Zambales', 'structure' => 'Point-to-Point', 'fare_min' => 450, 'fare_max' => 520, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-VICTORY-SANTA_CRUZ_ZAMBALES', 'route_name' => 'Caloocan - Santa Cruz, Zambales', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Santa Cruz, Zambales', 'structure' => 'Point-to-Point', 'fare_min' => 480, 'fare_max' => 550, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-VICTORY-BAGUIO', 'route_name' => 'Caloocan - Baguio', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Baguio', 'structure' => 'Point-to-Point', 'fare_min' => 750, 'fare_max' => 900, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-VICTORY-TUGUEGARAO', 'route_name' => 'Caloocan - Tuguegarao', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Tuguegarao', 'structure' => 'Point-to-Point', 'fare_min' => 1000, 'fare_max' => 1300, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-BALIWAG-BALIWAG', 'route_name' => 'Caloocan - Baliwag', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Baliwag', 'structure' => 'Point-to-Point', 'fare_min' => 110, 'fare_max' => 140, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-BALIWAG-CABANATUAN', 'route_name' => 'Caloocan - Cabanatuan', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Cabanatuan', 'structure' => 'Point-to-Point', 'fare_min' => 210, 'fare_max' => 260, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-BALIWAG-GAPAN', 'route_name' => 'Caloocan - Gapan', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'Gapan', 'structure' => 'Point-to-Point', 'fare_min' => 190, 'fare_max' => 240, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-BALIWAG-SAN_JOSE_NE', 'route_name' => 'Caloocan - San Jose, NE', 'vehicle_type' => 'Bus', 'origin' => 'Caloocan', 'destination' => 'San Jose, NE', 'structure' => 'Point-to-Point', 'fare_min' => 280, 'fare_max' => 340, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'BUS-CAROUSEL-MONUMENTO-PITX', 'route_name' => 'Monumento - PITX', 'vehicle_type' => 'Bus', 'origin' => 'Monumento', 'destination' => 'PITX', 'structure' => 'Point-to-Point', 'fare_min' => 75.50, 'fare_max' => 75.50, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-SM_CALOOCAN-NOVALICHES_BAYAN', 'route_name' => 'SM Caloocan - Novaliches Bayan', 'vehicle_type' => 'UV', 'origin' => 'SM City Caloocan', 'destination' => 'Novaliches Bayan', 'structure' => 'Point-to-Point', 'fare_min' => 25, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-SM_CALOOCAN-SM_FAIRVIEW', 'route_name' => 'SM Caloocan - SM Fairview', 'vehicle_type' => 'UV', 'origin' => 'SM City Caloocan', 'destination' => 'SM Fairview', 'structure' => 'Point-to-Point', 'fare_min' => 30, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-SM_CALOOCAN-BLUMENTRITT', 'route_name' => 'SM Caloocan - Blumentritt', 'vehicle_type' => 'UV', 'origin' => 'SM City Caloocan', 'destination' => 'Blumentritt', 'structure' => 'Point-to-Point', 'fare_min' => 35, 'fare_max' => 45, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-SM_CALOOCAN-MONUMENTO', 'route_name' => 'SM Caloocan - Monumento', 'vehicle_type' => 'UV', 'origin' => 'SM City Caloocan', 'destination' => 'Monumento', 'structure' => 'Point-to-Point', 'fare_min' => 30, 'fare_max' => 40, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-DEPARO-SM_NORTH', 'route_name' => 'Deparo - SM North', 'vehicle_type' => 'UV', 'origin' => 'Deparo', 'destination' => 'SM North', 'structure' => 'Point-to-Point', 'fare_min' => 45, 'fare_max' => 55, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-DEPARO-CUBAO', 'route_name' => 'Deparo - Cubao', 'vehicle_type' => 'UV', 'origin' => 'Deparo', 'destination' => 'Cubao', 'structure' => 'Point-to-Point', 'fare_min' => 50, 'fare_max' => 60, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-DEPARO-QUEZON_AVE', 'route_name' => 'Deparo - Quezon Ave', 'vehicle_type' => 'UV', 'origin' => 'Deparo', 'destination' => 'Quezon Ave', 'structure' => 'Point-to-Point', 'fare_min' => 45, 'fare_max' => 55, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'UV-DEPARO-NOVALICHES_BAYAN', 'route_name' => 'Deparo - Novaliches Bayan', 'vehicle_type' => 'UV', 'origin' => 'Deparo', 'destination' => 'Novaliches Bayan', 'structure' => 'Point-to-Point', 'fare_min' => 25, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-SANGANDAAN-DIVISORIA', 'route_name' => 'Sangandaan - Divisoria', 'vehicle_type' => 'Jeepney', 'origin' => 'Sangandaan', 'destination' => 'Divisoria', 'structure' => 'Point-to-Point', 'fare_min' => 30, 'fare_max' => 40, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-SANGANDAAN-RECTO', 'route_name' => 'Sangandaan - Recto', 'vehicle_type' => 'Jeepney', 'origin' => 'Sangandaan', 'destination' => 'Recto', 'structure' => 'Point-to-Point', 'fare_min' => 28, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-SANGANDAAN-BLUMENTRITT', 'route_name' => 'Sangandaan - Blumentritt', 'vehicle_type' => 'Jeepney', 'origin' => 'Sangandaan', 'destination' => 'Blumentritt', 'structure' => 'Point-to-Point', 'fare_min' => 20, 'fare_max' => 25, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-SANGANDAAN-MONUMENTO', 'route_name' => 'Sangandaan - Monumento', 'vehicle_type' => 'Jeepney', 'origin' => 'Sangandaan', 'destination' => 'Monumento', 'structure' => 'Point-to-Point', 'fare_min' => 13, 'fare_max' => 18, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-BAGUMBONG-NOVALICHES_BAYAN', 'route_name' => 'Bagumbong - Novaliches Bayan', 'vehicle_type' => 'Jeepney', 'origin' => 'Bagumbong', 'destination' => 'Novaliches Bayan', 'structure' => 'Point-to-Point', 'fare_min' => 13, 'fare_max' => 15, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-BAGUMBONG-SM_FAIRVIEW', 'route_name' => 'Bagumbong - SM Fairview', 'vehicle_type' => 'Jeepney', 'origin' => 'Bagumbong', 'destination' => 'SM Fairview', 'structure' => 'Point-to-Point', 'fare_min' => 18, 'fare_max' => 22, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'JEEP-BAGUMBONG-DEPARO', 'route_name' => 'Bagumbong - Deparo', 'vehicle_type' => 'Jeepney', 'origin' => 'Bagumbong', 'destination' => 'Deparo', 'structure' => 'Point-to-Point', 'fare_min' => 13, 'fare_max' => 18, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-BAGUMBONG-DEPARO', 'route_name' => 'Bagumbong - Deparo', 'vehicle_type' => 'Tricycle', 'origin' => 'Bagumbong', 'destination' => 'Deparo', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-BAGUMBONG-CAMARIN', 'route_name' => 'Bagumbong - Camarin', 'vehicle_type' => 'Tricycle', 'origin' => 'Bagumbong', 'destination' => 'Camarin', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-BAGUMBONG-TALA_HOSPITAL', 'route_name' => 'Bagumbong - Tala Hospital', 'vehicle_type' => 'Tricycle', 'origin' => 'Bagumbong', 'destination' => 'Tala Hospital', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-DEPARO-CAMARIN', 'route_name' => 'Deparo - Camarin', 'vehicle_type' => 'Tricycle', 'origin' => 'Deparo', 'destination' => 'Camarin', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-DEPARO-BAGUMBONG', 'route_name' => 'Deparo - Bagumbong', 'vehicle_type' => 'Tricycle', 'origin' => 'Deparo', 'destination' => 'Bagumbong', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-DEPARO-SUSANO_ROAD', 'route_name' => 'Deparo - Susano Road', 'vehicle_type' => 'Tricycle', 'origin' => 'Deparo', 'destination' => 'Susano Road', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-CAMARIN-DEPARO', 'route_name' => 'Camarin - Deparo', 'vehicle_type' => 'Tricycle', 'origin' => 'Camarin', 'destination' => 'Deparo', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-CAMARIN-BAGUMBONG', 'route_name' => 'Camarin - Bagumbong', 'vehicle_type' => 'Tricycle', 'origin' => 'Camarin', 'destination' => 'Bagumbong', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-CAMARIN-TALA', 'route_name' => 'Camarin - Tala', 'vehicle_type' => 'Tricycle', 'origin' => 'Camarin', 'destination' => 'Tala', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 35, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-TALA-CAMARIN', 'route_name' => 'Tala - Camarin', 'vehicle_type' => 'Tricycle', 'origin' => 'Tala', 'destination' => 'Camarin', 'structure' => 'Point-to-Point', 'fare_min' => 20, 'fare_max' => 40, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-TALA-BAGUMBONG', 'route_name' => 'Tala - Bagumbong', 'vehicle_type' => 'Tricycle', 'origin' => 'Tala', 'destination' => 'Bagumbong', 'structure' => 'Point-to-Point', 'fare_min' => 20, 'fare_max' => 40, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-TALA-DEPARO', 'route_name' => 'Tala - Deparo', 'vehicle_type' => 'Tricycle', 'origin' => 'Tala', 'destination' => 'Deparo', 'structure' => 'Point-to-Point', 'fare_min' => 20, 'fare_max' => 40, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-SANGANDAAN-GRACE_PARK', 'route_name' => 'Sangandaan - Grace Park', 'vehicle_type' => 'Tricycle', 'origin' => 'Sangandaan', 'destination' => 'Grace Park', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-SANGANDAAN-MONUMENTO', 'route_name' => 'Sangandaan - Monumento', 'vehicle_type' => 'Tricycle', 'origin' => 'Sangandaan', 'destination' => 'Monumento', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-SANGANDAAN-5TH_AVE', 'route_name' => 'Sangandaan - 5th Ave', 'vehicle_type' => 'Tricycle', 'origin' => 'Sangandaan', 'destination' => '5th Ave', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-GRACE_PARK-10TH_AVE', 'route_name' => 'Grace Park - 10th Ave', 'vehicle_type' => 'Tricycle', 'origin' => 'Grace Park', 'destination' => '10th Ave', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 25, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-GRACE_PARK-5TH_AVE', 'route_name' => 'Grace Park - 5th Ave', 'vehicle_type' => 'Tricycle', 'origin' => 'Grace Park', 'destination' => '5th Ave', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 25, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-GRACE_PARK-RIZAL_AVE', 'route_name' => 'Grace Park - Rizal Ave', 'vehicle_type' => 'Tricycle', 'origin' => 'Grace Park', 'destination' => 'Rizal Ave', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 25, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-5TH_AVE-A_MABINI', 'route_name' => '5th Ave - A. Mabini', 'vehicle_type' => 'Tricycle', 'origin' => '5th Ave', 'destination' => 'A. Mabini', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-5TH_AVE-SANGANDAAN', 'route_name' => '5th Ave - Sangandaan', 'vehicle_type' => 'Tricycle', 'origin' => '5th Ave', 'destination' => 'Sangandaan', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
  ['route_id' => 'TRI-5TH_AVE-GRACE_PARK', 'route_name' => '5th Ave - Grace Park', 'vehicle_type' => 'Tricycle', 'origin' => '5th Ave', 'destination' => 'Grace Park', 'structure' => 'Point-to-Point', 'fare_min' => 15, 'fare_max' => 30, 'status' => 'Active', 'max_vehicle_limit' => 0],
];

$mappings = [];
foreach ($routes as $r) {
  $id = (string)$r['route_id'];
  if (strpos($id, 'BUS-VICTORY-') === 0) $mappings[] = ['terminal_name' => 'Victory Liner - Caloocan (Monumento)', 'route_id' => $id];
  if (strpos($id, 'BUS-BALIWAG-') === 0) $mappings[] = ['terminal_name' => 'Baliwag Transit - Caloocan', 'route_id' => $id];
}
$mappings[] = ['terminal_name' => 'Monumento Carousel Terminal', 'route_id' => 'BUS-CAROUSEL-MONUMENTO-PITX'];
$mappings = array_merge($mappings, [
  ['terminal_name' => 'SM City Caloocan Terminal', 'route_id' => 'UV-SM_CALOOCAN-NOVALICHES_BAYAN'],
  ['terminal_name' => 'SM City Caloocan Terminal', 'route_id' => 'UV-SM_CALOOCAN-SM_FAIRVIEW'],
  ['terminal_name' => 'SM City Caloocan Terminal', 'route_id' => 'UV-SM_CALOOCAN-BLUMENTRITT'],
  ['terminal_name' => 'SM City Caloocan Terminal', 'route_id' => 'UV-SM_CALOOCAN-MONUMENTO'],
  ['terminal_name' => 'Deparo UV Express Terminal', 'route_id' => 'UV-DEPARO-SM_NORTH'],
  ['terminal_name' => 'Deparo UV Express Terminal', 'route_id' => 'UV-DEPARO-CUBAO'],
  ['terminal_name' => 'Deparo UV Express Terminal', 'route_id' => 'UV-DEPARO-QUEZON_AVE'],
  ['terminal_name' => 'Deparo UV Express Terminal', 'route_id' => 'UV-DEPARO-NOVALICHES_BAYAN'],
  ['terminal_name' => 'Sangandaan / City Hall Jeep Terminal', 'route_id' => 'JEEP-SANGANDAAN-DIVISORIA'],
  ['terminal_name' => 'Sangandaan / City Hall Jeep Terminal', 'route_id' => 'JEEP-SANGANDAAN-RECTO'],
  ['terminal_name' => 'Sangandaan / City Hall Jeep Terminal', 'route_id' => 'JEEP-SANGANDAAN-BLUMENTRITT'],
  ['terminal_name' => 'Sangandaan / City Hall Jeep Terminal', 'route_id' => 'JEEP-SANGANDAAN-MONUMENTO'],
  ['terminal_name' => 'Bagumbong - Novaliches Jeep Terminal', 'route_id' => 'JEEP-BAGUMBONG-NOVALICHES_BAYAN'],
  ['terminal_name' => 'Bagumbong - Novaliches Jeep Terminal', 'route_id' => 'JEEP-BAGUMBONG-SM_FAIRVIEW'],
  ['terminal_name' => 'Bagumbong - Novaliches Jeep Terminal', 'route_id' => 'JEEP-BAGUMBONG-DEPARO'],
  ['terminal_name' => 'Bagumbong Tricycle Terminal', 'route_id' => 'TRI-BAGUMBONG-DEPARO'],
  ['terminal_name' => 'Bagumbong Tricycle Terminal', 'route_id' => 'TRI-BAGUMBONG-CAMARIN'],
  ['terminal_name' => 'Bagumbong Tricycle Terminal', 'route_id' => 'TRI-BAGUMBONG-TALA_HOSPITAL'],
  ['terminal_name' => 'Deparo Tricycle Terminal', 'route_id' => 'TRI-DEPARO-CAMARIN'],
  ['terminal_name' => 'Deparo Tricycle Terminal', 'route_id' => 'TRI-DEPARO-BAGUMBONG'],
  ['terminal_name' => 'Deparo Tricycle Terminal', 'route_id' => 'TRI-DEPARO-SUSANO_ROAD'],
  ['terminal_name' => 'Camarin Tricycle Terminal', 'route_id' => 'TRI-CAMARIN-DEPARO'],
  ['terminal_name' => 'Camarin Tricycle Terminal', 'route_id' => 'TRI-CAMARIN-BAGUMBONG'],
  ['terminal_name' => 'Camarin Tricycle Terminal', 'route_id' => 'TRI-CAMARIN-TALA'],
  ['terminal_name' => 'Tala Tricycle Terminal', 'route_id' => 'TRI-TALA-CAMARIN'],
  ['terminal_name' => 'Tala Tricycle Terminal', 'route_id' => 'TRI-TALA-BAGUMBONG'],
  ['terminal_name' => 'Tala Tricycle Terminal', 'route_id' => 'TRI-TALA-DEPARO'],
  ['terminal_name' => 'Sangandaan Tricycle Terminal', 'route_id' => 'TRI-SANGANDAAN-GRACE_PARK'],
  ['terminal_name' => 'Sangandaan Tricycle Terminal', 'route_id' => 'TRI-SANGANDAAN-MONUMENTO'],
  ['terminal_name' => 'Sangandaan Tricycle Terminal', 'route_id' => 'TRI-SANGANDAAN-5TH_AVE'],
  ['terminal_name' => 'Grace Park Tricycle Terminal', 'route_id' => 'TRI-GRACE_PARK-10TH_AVE'],
  ['terminal_name' => 'Grace Park Tricycle Terminal', 'route_id' => 'TRI-GRACE_PARK-5TH_AVE'],
  ['terminal_name' => 'Grace Park Tricycle Terminal', 'route_id' => 'TRI-GRACE_PARK-RIZAL_AVE'],
  ['terminal_name' => '5th Avenue Tricycle Terminal', 'route_id' => 'TRI-5TH_AVE-A_MABINI'],
  ['terminal_name' => '5th Avenue Tricycle Terminal', 'route_id' => 'TRI-5TH_AVE-SANGANDAAN'],
  ['terminal_name' => '5th Avenue Tricycle Terminal', 'route_id' => 'TRI-5TH_AVE-GRACE_PARK'],
]);

$db->query("SET FOREIGN_KEY_CHECKS=0");
$tables = [
  'terminal_routes',
  'terminal_areas',
  'terminal_assignments',
  'parking_payments',
  'parking_slots',
  'parking_violations',
  'parking_transactions',
  'parking_rates',
  'parking_areas',
  'franchise_vehicles',
  'franchise_applications',
  'endorsement_records',
  'compliance_cases',
  'franchises',
  'lptrp_routes',
  'routes',
  'terminals',
];
foreach ($tables as $t) truncate_if_exists($db, $t);
$db->query("SET FOREIGN_KEY_CHECKS=1");

seed_terminals($db, $terminals);
seed_routes($db, $routes);
seed_terminal_routes($db, $mappings);
seed_lptrp_routes($db, $routes);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'terminals' => count($terminals), 'routes' => count($routes), 'terminal_routes' => count($mappings)]);
