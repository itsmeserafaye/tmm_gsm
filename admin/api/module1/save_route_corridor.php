<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_permission('module1.routes.write');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$db = db();

$corridorId = isset($_POST['corridor_id']) ? (int)$_POST['corridor_id'] : 0;
$routeCode = strtoupper(trim((string)($_POST['route_code'] ?? ($_POST['route_id'] ?? ''))));
$routeName = trim((string)($_POST['route_name'] ?? ''));
$origin = trim((string)($_POST['origin'] ?? ''));
$destination = trim((string)($_POST['destination'] ?? ''));
$via = trim((string)($_POST['via'] ?? ''));
$structure = trim((string)($_POST['structure'] ?? ''));
$distanceKm = isset($_POST['distance_km']) && $_POST['distance_km'] !== '' ? (float)$_POST['distance_km'] : null;
$status = trim((string)($_POST['status'] ?? 'Active'));

$allocJson = trim((string)($_POST['allocations'] ?? ''));
$allocations = [];
if ($allocJson !== '') {
  $decoded = json_decode($allocJson, true);
  if (is_array($decoded)) $allocations = $decoded;
}

if ($routeCode === '' || strlen($routeCode) < 2) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_route_code']);
  exit;
}
if ($routeName === '') $routeName = $routeCode;

$allowedStruct = ['Loop','Point-to-Point'];
$structOk = false;
foreach ($allowedStruct as $s) {
  if (strcasecmp($structure, $s) === 0) { $structure = $s; $structOk = true; break; }
}
if (!$structOk) $structure = null;

if (!in_array($status, ['Active','Inactive'], true)) $status = 'Active';

$allowedVehicleTypes = ['Jeepney','UV','Bus'];
$cleanAllocs = [];
foreach ($allocations as $a) {
  if (!is_array($a)) continue;
  $vt = trim((string)($a['vehicle_type'] ?? ''));
  if (!in_array($vt, $allowedVehicleTypes, true)) continue;

  $au = isset($a['authorized_units']) && $a['authorized_units'] !== '' ? (int)$a['authorized_units'] : null;
  if ($au !== null && $au < 0) $au = 0;

  $fareMin = isset($a['fare_min']) && $a['fare_min'] !== '' ? (float)$a['fare_min'] : null;
  $fareMax = isset($a['fare_max']) && $a['fare_max'] !== '' ? (float)$a['fare_max'] : null;
  if ($fareMin !== null && $fareMin < 0) $fareMin = 0.0;
  if ($fareMax !== null && $fareMax < 0) $fareMax = 0.0;
  if ($fareMin !== null && $fareMax !== null && $fareMax < $fareMin) {
    $tmp = $fareMin; $fareMin = $fareMax; $fareMax = $tmp;
  }
  if ($fareMin === null && $fareMax !== null) $fareMin = $fareMax;
  if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;

  $ast = trim((string)($a['status'] ?? 'Active'));
  if (!in_array($ast, ['Active','Inactive'], true)) $ast = 'Active';
  if ($ast === 'Active' && $fareMin === null && $fareMax === null) $ast = 'Inactive';

  $cleanAllocs[$vt] = [
    'vehicle_type' => $vt,
    'authorized_units' => $au,
    'fare_min' => $fareMin,
    'fare_max' => $fareMax,
    'status' => $ast,
  ];
}
$cleanAllocs = array_values($cleanAllocs);
$activeAllocCount = 0;
foreach ($cleanAllocs as $a) {
  if (($a['status'] ?? 'Inactive') === 'Active') $activeAllocCount++;
}
if ($activeAllocCount <= 0) $status = 'Inactive';

try {
  $db->begin_transaction();

  if ($corridorId > 0) {
    $stmtCur = $db->prepare("SELECT id FROM routes WHERE id=? LIMIT 1");
    if (!$stmtCur) throw new Exception('db_prepare_failed');
    $stmtCur->bind_param('i', $corridorId);
    $stmtCur->execute();
    $exists = $stmtCur->get_result()->fetch_assoc();
    $stmtCur->close();
    if (!$exists) throw new Exception('route_not_found');

    $stmtDup = $db->prepare("SELECT id FROM routes WHERE (route_id=? OR route_code=?) AND id<>? LIMIT 1");
    if (!$stmtDup) throw new Exception('db_prepare_failed');
    $stmtDup->bind_param('ssi', $routeCode, $routeCode, $corridorId);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) throw new Exception('duplicate_route_code');

    $viaBind = $via !== '' ? $via : null;
    $stmt = $db->prepare("UPDATE routes
                          SET route_id=?, route_code=?, route_name=?, vehicle_type=NULL, origin=?, destination=?, via=?, structure=?, distance_km=?, status=?
                          WHERE id=?");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('sssssssdsi', $routeCode, $routeCode, $routeName, $origin, $destination, $viaBind, $structure, $distanceKm, $status, $corridorId);
    $ok = $stmt->execute();
    $stmt->close();
  } else {
    $stmtDup = $db->prepare("SELECT id FROM routes WHERE route_id=? OR route_code=? LIMIT 1");
    if (!$stmtDup) throw new Exception('db_prepare_failed');
    $stmtDup->bind_param('ss', $routeCode, $routeCode);
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) throw new Exception('duplicate_route_code');

    $viaBind = $via !== '' ? $via : null;
    $maxLimit = 50;
    $stmt = $db->prepare("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, max_vehicle_limit, status)
                          VALUES(?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('sssssssdis', $routeCode, $routeCode, $routeName, $origin, $destination, $viaBind, $structure, $distanceKm, $maxLimit, $status);
    $ok = $stmt->execute();
    $corridorId = (int)$db->insert_id;
    $stmt->close();
  }

  if ($corridorId <= 0) throw new Exception('save_failed');

  $db->query("DELETE FROM route_vehicle_types WHERE route_id=" . (int)$corridorId);
  if ($cleanAllocs) {
    $stmtA = $db->prepare("INSERT INTO route_vehicle_types(route_id, vehicle_type, authorized_units, fare_min, fare_max, status)
                           VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmtA) throw new Exception('db_prepare_failed');
    foreach ($cleanAllocs as $a) {
      $vt = (string)$a['vehicle_type'];
      $au = $a['authorized_units'];
      $fmin = $a['fare_min'];
      $fmax = $a['fare_max'];
      $ast = (string)$a['status'];
      $stmtA->bind_param('isidds', $corridorId, $vt, $au, $fmin, $fmax, $ast);
      $stmtA->execute();
    }
    $stmtA->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'corridor_id' => $corridorId, 'route_code' => $routeCode]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage() ?: 'save_failed']);
}
?>
