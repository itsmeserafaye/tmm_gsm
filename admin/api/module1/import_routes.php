<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/import.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.write');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

[$tmp, $err] = tmm_import_get_uploaded_csv('file');
if ($err) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err]);
  exit;
}

[, $rows, $err2] = tmm_import_read_csv($tmp);
if ($err2) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $err2]);
  exit;
}

$existingCols = [];
$resC = $db->query("SHOW COLUMNS FROM routes");
if ($resC) while ($c = $resC->fetch_assoc()) $existingCols[(string)($c['Field'] ?? '')] = true;

$allCols = ['route_id','route_code','route_name','vehicle_type','origin','destination','via','structure','distance_km','authorized_units','fare','fare_min','fare_max','status'];
$cols = array_values(array_filter($allCols, fn($c) => isset($existingCols[$c])));

if (!in_array('route_id', $cols, true)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'schema_missing_route_id']);
  exit;
}

$placeholders = [];
$updates = [];
foreach ($cols as $c) {
  $placeholders[] = '?';
  if ($c !== 'route_id') $updates[] = "`$c`=VALUES(`$c`)";
}
$sql = "INSERT INTO routes (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeholders) . ")
        ON DUPLICATE KEY UPDATE " . implode(',', $updates);

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];

$allowedVehicleTypes = ['Tricycle','Jeepney','UV','Bus'];
$allowedStructures = ['Loop','Point-to-Point'];

$db->begin_transaction();
try {
  foreach ($rows as $idx => $r) {
    $routeId = trim((string)($r['route_id'] ?? ''));
    if ($routeId === '') $routeId = trim((string)($r['route_code'] ?? ''));
    if ($routeId === '') { $skipped++; continue; }

    $vals = [];
    $types = '';
    foreach ($cols as $c) {
      $v = trim((string)($r[$c] ?? ''));
      if ($c === 'route_id') $v = $routeId;
      if ($c === 'vehicle_type') {
        if (!in_array($v, $allowedVehicleTypes, true)) $v = '';
      }
      if ($c === 'structure') {
        if (!in_array($v, $allowedStructures, true)) $v = '';
      }
      if ($c === 'authorized_units') {
        $v = $v === '' ? null : (int)$v;
      }
      if ($c === 'distance_km' || $c === 'fare' || $c === 'fare_min' || $c === 'fare_max') {
        $v = $v === '' ? null : (float)$v;
      }
      if ($c === 'status') {
        if ($v !== 'Active' && $v !== 'Inactive') $v = 'Active';
      }

      if ($v === null) {
        $types .= 's';
        $vals[] = null;
      } else if (is_int($v)) {
        $types .= 'i';
        $vals[] = $v;
      } else if (is_float($v)) {
        $types .= 'd';
        $vals[] = $v;
      } else {
        $types .= 's';
        $vals[] = $v;
      }
    }

    $stmt->bind_param($types, ...$vals);
    $ok = $stmt->execute();
    if (!$ok) {
      $errors[] = ['row' => $idx + 2, 'error' => 'save_failed', 'route_id' => $routeId];
      $skipped++;
      continue;
    }
    $aff = (int)$stmt->affected_rows;
    if ($aff >= 2) $updated++;
    else $inserted++;
  }
  $db->commit();
} catch (Throwable $e) {
  $db->rollback();
  $stmt->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'import_failed']);
  exit;
}

$stmt->close();
echo json_encode([
  'ok' => true,
  'inserted' => $inserted,
  'updated' => $updated,
  'skipped' => $skipped,
  'errors' => $errors
]);

