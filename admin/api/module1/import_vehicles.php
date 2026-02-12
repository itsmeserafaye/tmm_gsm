<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/import.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.write','module1.vehicles.write']);

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

$submittedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
if ($submittedByName === '') $submittedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
if ($submittedByName === '') $submittedByName = 'Admin';
if ($submittedByName !== '' && strpos($submittedByName, ' ') !== false) {
  $parts = preg_split('/\s+/', $submittedByName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
  if ($parts) $submittedByName = (string)$parts[0];
}

$now = date('Y-m-d H:i:s');

$stmtFindOp = $db->prepare("SELECT id FROM operators WHERE COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name)=? OR full_name=? OR name=? LIMIT 1");
if (!$stmtFindOp) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}

$sql = "INSERT INTO vehicles (
          plate_number, vehicle_type, operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, color, route_id, record_status, status,
          submitted_by_name, submitted_at
        )
        VALUES (?, ?, NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          vehicle_type=VALUES(vehicle_type),
          operator_id=VALUES(operator_id),
          operator_name=VALUES(operator_name),
          engine_no=VALUES(engine_no),
          chassis_no=VALUES(chassis_no),
          make=VALUES(make),
          model=VALUES(model),
          year_model=VALUES(year_model),
          fuel_type=VALUES(fuel_type),
          color=VALUES(color),
          route_id=VALUES(route_id),
          record_status=VALUES(record_status),
          status=VALUES(status),
          submitted_by_name=COALESCE(NULLIF(submitted_by_name,''), VALUES(submitted_by_name)),
          submitted_at=COALESCE(submitted_at, VALUES(submitted_at))";

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

$db->begin_transaction();
try {
  foreach ($rows as $idx => $r) {
    $plate = strtoupper(trim((string)($r['plate_number'] ?? '')));
    if ($plate === '') { $skipped++; continue; }

    $vehicleType = trim((string)($r['vehicle_type'] ?? ''));
    if ($vehicleType === '') $vehicleType = 'Jeepney';

    $operatorId = 0;
    $operatorName = trim((string)($r['operator_name'] ?? ''));
    $opIdRaw = trim((string)($r['operator_id'] ?? ''));
    if ($opIdRaw !== '' && ctype_digit($opIdRaw)) {
      $operatorId = (int)$opIdRaw;
    } elseif ($operatorName !== '') {
      $stmtFindOp->bind_param('sss', $operatorName, $operatorName, $operatorName);
      $stmtFindOp->execute();
      $opRow = $stmtFindOp->get_result()->fetch_assoc();
      $operatorId = (int)($opRow['id'] ?? 0);
    }

    $engine = trim((string)($r['engine_no'] ?? ''));
    $chassis = trim((string)($r['chassis_no'] ?? ''));
    $make = trim((string)($r['make'] ?? ''));
    $model = trim((string)($r['model'] ?? ''));
    $year = trim((string)($r['year_model'] ?? ''));
    $fuel = trim((string)($r['fuel_type'] ?? ''));
    $color = trim((string)($r['color'] ?? ''));
    $routeId = trim((string)($r['route_id'] ?? ''));
    $recordStatus = trim((string)($r['record_status'] ?? 'Encoded'));
    $status = trim((string)($r['status'] ?? 'Declared/linked'));
    if ($status === '') $status = 'Declared/linked';
    if ($recordStatus === '') $recordStatus = 'Encoded';

    $stmt->bind_param(
      'ssisssssssssssss',
      $plate,
      $vehicleType,
      $operatorId,
      $operatorName,
      $engine,
      $chassis,
      $make,
      $model,
      $year,
      $fuel,
      $color,
      $routeId,
      $recordStatus,
      $status,
      $submittedByName,
      $now
    );

    $ok = $stmt->execute();
    if (!$ok) {
      $errors[] = ['row' => $idx + 2, 'error' => 'save_failed'];
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
  $stmtFindOp->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'import_failed']);
  exit;
}

$stmt->close();
$stmtFindOp->close();
echo json_encode([
  'ok' => true,
  'inserted' => $inserted,
  'updated' => $updated,
  'skipped' => $skipped,
  'errors' => $errors
]);
