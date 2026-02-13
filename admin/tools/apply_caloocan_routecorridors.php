<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
  require_login();
  require_permission('module1.routes.write');
  header('Content-Type: text/plain');
}

$dry = false;
if ($isCli) {
  $dry = in_array('--dry', $argv ?? [], true);
} else {
  $dry = ((string)($_GET['dry'] ?? '')) === '1';
}

$db = db();

$hasTable = function (string $table) use ($db): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return (bool)($r && $r->fetch_row());
};

if (!$hasTable('routes') || !$hasTable('route_vehicle_types') || !$hasTable('tricycle_service_areas')) {
  echo "missing_required_tables\n";
  exit(1);
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return (bool)($r && $r->num_rows > 0);
};
if (!$hasCol('routes', 'route_category')) {
  @$db->query("ALTER TABLE routes ADD COLUMN route_category VARCHAR(64) DEFAULT NULL");
}

$norm = function (string $s): string {
  $s = strtoupper(trim($s));
  $s = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", "—", "–"], '-', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  $s = preg_replace('/[^A-Z0-9 \-]/', '', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim((string)$s);
};

$pairKey = function (string $o, string $d) use ($norm): string {
  return $norm($o) . '|' . $norm($d);
};

$routes = [];
$byPair = [];
$res = $db->query("SELECT id, route_name, origin, destination, status FROM routes ORDER BY id ASC");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    $routes[$id] = $r;
    $k = $pairKey((string)($r['origin'] ?? ''), (string)($r['destination'] ?? ''));
    if (!isset($byPair[$k])) $byPair[$k] = [];
    $byPair[$k][] = $id;
  }
}

$pickRouteId = function (array $ids) use ($routes): int {
  if (!$ids) return 0;
  $best = 0;
  foreach ($ids as $id) {
    $st = (string)($routes[$id]['status'] ?? '');
    if ($st === 'Active') return (int)$id;
    $best = (int)$id;
  }
  return (int)$best;
};

$findId = function (string $o, string $d) use ($byPair, $pairKey, $pickRouteId): int {
  $k = $pairKey($o, $d);
  if (!isset($byPair[$k])) return 0;
  return $pickRouteId($byPair[$k]);
};

$allocs = [
  ['origin' => 'Bagumbong', 'destination' => 'Novaliches Bayan', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 100, 'fare_min' => 13, 'fare_max' => 18],
    ['vehicle_type' => 'Modern Jeepney', 'authorized_units' => 40, 'fare_min' => 13, 'fare_max' => 18],
  ]],
  ['origin' => 'Bagumbong', 'destination' => 'SM Fairview', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 15, 'fare_max' => 25],
    ['vehicle_type' => 'UV Express', 'authorized_units' => 40, 'fare_min' => 15, 'fare_max' => 25],
  ]],
  ['origin' => 'Bagumbong', 'destination' => 'Deparo', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 140, 'fare_min' => 13, 'fare_max' => 18],
  ]],
  ['origin' => 'Deparo', 'destination' => 'SM North', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 100, 'fare_min' => 18, 'fare_max' => 30],
    ['vehicle_type' => 'UV Express', 'authorized_units' => 30, 'fare_min' => 18, 'fare_max' => 30],
  ]],
  ['origin' => 'Deparo', 'destination' => 'Cubao', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'UV Express', 'authorized_units' => 40, 'fare_min' => 35, 'fare_max' => 50],
  ]],
  ['origin' => 'Deparo', 'destination' => 'Quezon Ave', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'UV Express', 'authorized_units' => 40, 'fare_min' => 35, 'fare_max' => 50],
  ]],
  ['origin' => 'Deparo', 'destination' => 'Novaliches Bayan', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 13, 'fare_max' => 18],
  ]],
  ['origin' => 'Sangandaan', 'destination' => 'Divisoria', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 15, 'fare_max' => 20],
  ]],
  ['origin' => 'Sangandaan', 'destination' => 'Recto', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 15, 'fare_max' => 20],
  ]],
  ['origin' => 'Sangandaan', 'destination' => 'Blumentritt', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 15, 'fare_max' => 20],
  ]],
  ['origin' => 'Sangandaan', 'destination' => 'Monumento', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 80, 'fare_min' => 13, 'fare_max' => 15],
  ]],
  ['origin' => 'SM City Caloocan', 'destination' => 'Monumento', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 60, 'fare_min' => 13, 'fare_max' => 15],
    ['vehicle_type' => 'Modern Jeepney', 'authorized_units' => 30, 'fare_min' => 13, 'fare_max' => 15],
  ]],
  ['origin' => 'SM City Caloocan', 'destination' => 'Novaliches Bayan', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 15, 'fare_max' => 20],
  ]],
  ['origin' => 'SM City Caloocan', 'destination' => 'SM Fairview', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 18, 'fare_max' => 30],
    ['vehicle_type' => 'UV Express', 'authorized_units' => 40, 'fare_min' => 18, 'fare_max' => 30],
  ]],
  ['origin' => 'SM City Caloocan', 'destination' => 'Blumentritt', 'category' => 'Urban PUV Corridor', 'allocations' => [
    ['vehicle_type' => 'Jeepney', 'authorized_units' => 120, 'fare_min' => 18, 'fare_max' => 30],
  ]],
  ['origin' => 'Monumento', 'destination' => 'PITX', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 200, 'fare_min' => 15, 'fare_max' => 75],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Baliwag', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 45, 'fare_min' => 110, 'fare_max' => 140],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Cabanatuan', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 45, 'fare_min' => 210, 'fare_max' => 260],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Gapan', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 45, 'fare_min' => 190, 'fare_max' => 240],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'San Jose, NE', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 45, 'fare_min' => 280, 'fare_max' => 340],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Baguio', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 25, 'fare_min' => 750, 'fare_max' => 900],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Olongapo', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 35, 'fare_min' => 300, 'fare_max' => 350],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Iba, Zambales', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 35, 'fare_min' => 450, 'fare_max' => 520],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Santa Cruz, Zambales', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 35, 'fare_min' => 480, 'fare_max' => 550],
  ]],
  ['origin' => 'Caloocan', 'destination' => 'Tuguegarao', 'category' => 'Provincial Bus Corridor', 'allocations' => [
    ['vehicle_type' => 'City Bus', 'authorized_units' => 25, 'fare_min' => 1000, 'fare_max' => 1300],
  ]],
];

$tricyclePairs = [
  ['Bagumbong', 'Camarin'],
  ['Bagumbong', 'Tala Hospital'],
  ['Camarin', 'Bagumbong'],
  ['Camarin', 'Deparo'],
  ['Camarin', 'Tala'],
  ['Deparo', 'Bagumbong'],
  ['Deparo', 'Camarin'],
  ['Deparo', 'Susano Road'],
  ['Grace Park', '10th Ave'],
  ['Grace Park', '5th Ave'],
  ['Grace Park', 'Rizal Ave'],
  ['Sangandaan', '5th Ave'],
  ['Sangandaan', 'Grace Park'],
  ['Tala', 'Bagumbong'],
  ['Tala', 'Camarin'],
  ['Tala', 'Deparo'],
  ['5th Ave', 'Sangandaan'],
  ['5th Ave', 'Grace Park'],
  ['5th Ave', 'A. Mabini'],
];

$serviceAreas = [
  ['code' => 'BAGUMBONG-ZONE', 'name' => 'Bagumbong Zone', 'units' => 260, 'fare_min' => 15, 'fare_max' => 30],
  ['code' => 'CAMARIN-ZONE', 'name' => 'Camarin Zone', 'units' => 260, 'fare_min' => 15, 'fare_max' => 35],
  ['code' => 'DEPARO-ZONE', 'name' => 'Deparo Zone', 'units' => 260, 'fare_min' => 15, 'fare_max' => 35],
  ['code' => 'TALA-ZONE', 'name' => 'Tala Zone', 'units' => 260, 'fare_min' => 15, 'fare_max' => 35],
  ['code' => 'GRACE-PARK-ZONE', 'name' => 'Grace Park Zone', 'units' => 200, 'fare_min' => 15, 'fare_max' => 25],
  ['code' => 'SANGANDAAN-ZONE', 'name' => 'Sangandaan Zone', 'units' => 200, 'fare_min' => 15, 'fare_max' => 30],
  ['code' => '5TH-AVE-ZONE', 'name' => '5th Ave Zone', 'units' => 200, 'fare_min' => 15, 'fare_max' => 30],
];

$updatedRoutes = 0;
$upsertedAllocs = 0;
$deactivatedAllocs = 0;
$convertedTricycleRoutes = 0;
$createdServiceAreas = 0;
$updatedServiceAreas = 0;
$missingRoutes = 0;

try {
  if (!$dry) $db->begin_transaction();

  $stmtUpdateRoute = $db->prepare("UPDATE routes SET vehicle_type=NULL, route_category=?, status=? WHERE id=?");
  $stmtUpsertAlloc = $db->prepare("INSERT INTO route_vehicle_types(route_id, vehicle_type, authorized_units, fare_min, fare_max, status)
                                   VALUES (?, ?, ?, ?, ?, 'Active')
                                   ON DUPLICATE KEY UPDATE authorized_units=VALUES(authorized_units), fare_min=VALUES(fare_min), fare_max=VALUES(fare_max), status='Active'");
  $stmtListAllocs = $db->prepare("SELECT vehicle_type FROM route_vehicle_types WHERE route_id=?");
  $stmtDeactivateAlloc = $db->prepare("UPDATE route_vehicle_types SET status='Inactive', authorized_units=0, fare_min=NULL, fare_max=NULL WHERE route_id=? AND vehicle_type=?");
  $stmtDeleteAllocs = $db->prepare("DELETE FROM route_vehicle_types WHERE route_id=?");
  $stmtConvertTri = $db->prepare("UPDATE routes SET route_category='Tricycle Service Area', status='Inactive' WHERE id=?");

  $stmtUpsertArea = $db->prepare("INSERT INTO tricycle_service_areas(area_code, area_name, authorized_units, fare_min, fare_max, status)
                                  VALUES (?, ?, ?, ?, ?, 'Active')
                                  ON DUPLICATE KEY UPDATE area_name=VALUES(area_name), authorized_units=VALUES(authorized_units), fare_min=VALUES(fare_min), fare_max=VALUES(fare_max), status='Active'");

  foreach ($allocs as $item) {
    $rid = $findId((string)$item['origin'], (string)$item['destination']);
    if ($rid <= 0) { $missingRoutes++; continue; }

    $cat = (string)($item['category'] ?? '');
    $st = 'Active';
    $stmtUpdateRoute->bind_param('ssi', $cat, $st, $rid);
    $stmtUpdateRoute->execute();
    $updatedRoutes++;

    $existing = [];
    $stmtListAllocs->bind_param('i', $rid);
    $stmtListAllocs->execute();
    $resA = $stmtListAllocs->get_result();
    while ($resA && ($row = $resA->fetch_assoc())) {
      $existing[] = (string)($row['vehicle_type'] ?? '');
    }

    $wanted = [];
    foreach (($item['allocations'] ?? []) as $a) {
      $vt = (string)($a['vehicle_type'] ?? '');
      if ($vt === '') continue;
      $wanted[$vt] = true;
      $au = (int)($a['authorized_units'] ?? 0);
      $fmin = (float)($a['fare_min'] ?? 0);
      $fmax = (float)($a['fare_max'] ?? $fmin);
      $stmtUpsertAlloc->bind_param('isidd', $rid, $vt, $au, $fmin, $fmax);
      $stmtUpsertAlloc->execute();
      $upsertedAllocs++;
    }

    foreach ($existing as $vt) {
      if ($vt === '' || isset($wanted[$vt])) continue;
      $stmtDeactivateAlloc->bind_param('is', $rid, $vt);
      $stmtDeactivateAlloc->execute();
      $deactivatedAllocs++;
    }
  }

  foreach ($tricyclePairs as $p) {
    $rid = $findId((string)($p[0] ?? ''), (string)($p[1] ?? ''));
    if ($rid <= 0) continue;
    $stmtConvertTri->bind_param('i', $rid);
    $stmtConvertTri->execute();
    $stmtDeleteAllocs->bind_param('i', $rid);
    $stmtDeleteAllocs->execute();
    $convertedTricycleRoutes++;
  }

  foreach ($serviceAreas as $a) {
    $code = (string)($a['code'] ?? '');
    $name = (string)($a['name'] ?? '');
    $units = (int)($a['units'] ?? 0);
    $fmin = (float)($a['fare_min'] ?? 0);
    $fmax = (float)($a['fare_max'] ?? $fmin);
    $stmtUpsertArea->bind_param('ssidd', $code, $name, $units, $fmin, $fmax);
    $stmtUpsertArea->execute();
    if (($db->affected_rows ?? 0) === 1) $createdServiceAreas++;
    else $updatedServiceAreas++;
  }

  if (!$dry) $db->commit();

  echo "dry_run=" . ($dry ? '1' : '0') . "\n";
  echo "routes_updated=$updatedRoutes\n";
  echo "allocations_upserted=$upsertedAllocs\n";
  echo "allocations_deactivated=$deactivatedAllocs\n";
  echo "tricycle_routes_converted=$convertedTricycleRoutes\n";
  echo "service_areas_created=$createdServiceAreas\n";
  echo "service_areas_updated=$updatedServiceAreas\n";
  echo "routes_missing=$missingRoutes\n";
} catch (Throwable $e) {
  if (!$dry) $db->rollback();
  echo "failed:" . ($e->getMessage() ?: 'error') . "\n";
  exit(1);
}
?>
