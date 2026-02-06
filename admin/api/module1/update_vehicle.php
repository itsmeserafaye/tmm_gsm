<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.vehicles.write');
$plate = trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? '')));
$vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
$status = trim((string)($_POST['status'] ?? ''));
$type = trim((string)($_POST['vehicle_type'] ?? ''));
$engineNoRaw = (string)($_POST['engine_no'] ?? '');
$engineNo = strtoupper(preg_replace('/\s+/', '', trim($engineNoRaw)));
$engineNo = preg_replace('/[^A-Z0-9\-]/', '', $engineNo);
$chassisNoRaw = (string)($_POST['chassis_no'] ?? '');
$chassisNo = strtoupper(preg_replace('/\s+/', '', trim($chassisNoRaw)));
$chassisNo = preg_replace('/[^A-HJ-NPR-Z0-9]/', '', $chassisNo);
$make = trim((string)($_POST['make'] ?? ''));
$model = trim((string)($_POST['model'] ?? ''));
$yearModel = trim((string)($_POST['year_model'] ?? ''));
$fuelType = trim((string)($_POST['fuel_type'] ?? ''));
$color = trim((string)($_POST['color'] ?? ''));
$orNumberRaw = (string)($_POST['or_number'] ?? '');
$orNumber = preg_replace('/[^0-9]/', '', trim($orNumberRaw));
$orNumber = substr($orNumber, 0, 12);
$crNumberRaw = (string)($_POST['cr_number'] ?? '');
$crNumber = strtoupper(preg_replace('/\s+/', '', trim($crNumberRaw)));
$crNumber = preg_replace('/[^A-Z0-9\-]/', '', $crNumber);
$crNumber = substr($crNumber, 0, 20);
$crIssueDate = trim((string)($_POST['cr_issue_date'] ?? ''));
$registeredOwner = trim((string)($_POST['registered_owner'] ?? ''));

if ($vehicleId <= 0 && $plate === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_vehicle']); exit; }

if ($engineNo !== '' && !preg_match('/^[A-Z0-9\-]{5,20}$/', $engineNo)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_engine_no']); exit; }
if ($chassisNo !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassisNo)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_chassis_no']); exit; }
if ($crIssueDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $crIssueDate)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_cr_issue_date']); exit; }
if ($orNumber !== '' && !preg_match('/^[0-9]{6,12}$/', $orNumber)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_or_number']); exit; }
if ($crNumber !== '' && !preg_match('/^[A-Z0-9\-]{6,20}$/', $crNumber)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_cr_number']); exit; }

$hasCols = function (string $col) use ($db): bool {
  $res = $db->query("SHOW COLUMNS FROM vehicles LIKE '" . $db->real_escape_string($col) . "'");
  return $res && ($res->num_rows ?? 0) > 0;
};
if (($orNumber !== '') && !$hasCols('or_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN or_number VARCHAR(12) NULL"); }
if (($crNumber !== '' || $crIssueDate !== '' || $registeredOwner !== '') && !$hasCols('cr_number')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_number VARCHAR(64) NULL"); }
if (($crNumber !== '' || $crIssueDate !== '' || $registeredOwner !== '') && !$hasCols('cr_issue_date')) { @$db->query("ALTER TABLE vehicles ADD COLUMN cr_issue_date DATE NULL"); }
if (($crNumber !== '' || $crIssueDate !== '' || $registeredOwner !== '') && !$hasCols('registered_owner')) { @$db->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(120) NULL"); }

$sets = [];
$params = [];
$types = '';
if ($status !== '' && $status !== 'Status') {
  if (!in_array($status, ['Active','Inactive'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_status']); exit; }
  $sets[] = "status=?";
  $params[] = $status;
  $types .= 's';
}
if ($type !== '' && $type !== 'Select vehicle type') { $sets[] = "vehicle_type=?"; $params[] = $type; $types .= 's'; }
if ($engineNo !== '') { $sets[] = "engine_no=?"; $params[] = $engineNo; $types .= 's'; }
if ($chassisNo !== '') { $sets[] = "chassis_no=?"; $params[] = $chassisNo; $types .= 's'; }
if ($make !== '') { $sets[] = "make=?"; $params[] = $make; $types .= 's'; }
if ($model !== '') { $sets[] = "model=?"; $params[] = $model; $types .= 's'; }
if ($yearModel !== '') { $sets[] = "year_model=?"; $params[] = $yearModel; $types .= 's'; }
if ($fuelType !== '') { $sets[] = "fuel_type=?"; $params[] = $fuelType; $types .= 's'; }
if ($color !== '') { $sets[] = "color=?"; $params[] = $color; $types .= 's'; }
if ($orNumber !== '') { $sets[] = "or_number=?"; $params[] = $orNumber; $types .= 's'; }
if ($crNumber !== '') { $sets[] = "cr_number=?"; $params[] = $crNumber; $types .= 's'; }
if ($crIssueDate !== '') { $sets[] = "cr_issue_date=?"; $params[] = $crIssueDate; $types .= 's'; }
if ($registeredOwner !== '') { $sets[] = "registered_owner=?"; $params[] = $registeredOwner; $types .= 's'; }
if (!$sets) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'nothing_to_update']); exit; }
$whereSql = '';
if ($vehicleId > 0) {
  $whereSql = " WHERE id=?";
  $params[] = $vehicleId;
  $types .= 'i';
} else {
  $whereSql = " WHERE plate_number=?";
  $params[] = $plate;
  $types .= 's';
}
$sql = "UPDATE vehicles SET ".implode(",", $sets).$whereSql;
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'vehicle_id'=>$vehicleId]);
?> 
