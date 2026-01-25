<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_vehicle_id']);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$hasTable = function (string $table) use ($db): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return $r && (bool)$r->fetch_row();
};

$vehHasVehicleType = $hasCol('vehicles', 'vehicle_type');
$vehHasEngine = $hasCol('vehicles', 'engine_no');
$vehHasChassis = $hasCol('vehicles', 'chassis_no');
$vehHasMake = $hasCol('vehicles', 'make');
$vehHasModel = $hasCol('vehicles', 'model');
$vehHasYear = $hasCol('vehicles', 'year_model');
$vehHasFuel = $hasCol('vehicles', 'fuel_type');
$vehHasColor = $hasCol('vehicles', 'color');
$vehHasStatus = $hasCol('vehicles', 'status');
$vehHasCrNo = $hasCol('vehicles', 'cr_number');
$vehHasCrIssue = $hasCol('vehicles', 'cr_issue_date');
$vehHasOwner = $hasCol('vehicles', 'registered_owner');
$vehHasOperatorName = $hasCol('vehicles', 'operator_name');
$vehHasCurrentOp = $hasCol('vehicles', 'current_operator_id');

$vrHasOrNo = $hasCol('vehicle_registrations', 'or_number');
$vrHasOrDate = $hasCol('vehicle_registrations', 'or_date');
$vrHasOrExp = $hasCol('vehicle_registrations', 'or_expiry_date');
$vrHasRegYear = $hasCol('vehicle_registrations', 'registration_year');

$opIdExpr = $vehHasCurrentOp ? "COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0))" : "NULLIF(v.operator_id,0)";
$opNameExpr = "COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,'')," . ($vehHasOperatorName ? " NULLIF(v.operator_name,'')," : "") . " '-')";

$stmt = $db->prepare("SELECT v.id, v.plate_number" .
                             ($vehHasVehicleType ? ", v.vehicle_type" : ", '' AS vehicle_type") .
                             ($vehHasEngine ? ", v.engine_no" : ", '' AS engine_no") .
                             ($vehHasChassis ? ", v.chassis_no" : ", '' AS chassis_no") .
                             ($vehHasMake ? ", v.make" : ", '' AS make") .
                             ($vehHasModel ? ", v.model" : ", '' AS model") .
                             ($vehHasYear ? ", v.year_model" : ", '' AS year_model") .
                             ($vehHasFuel ? ", v.fuel_type" : ", '' AS fuel_type") .
                             ($vehHasColor ? ", v.color" : ", '' AS color") .
                             ($vehHasStatus ? ", v.status AS vehicle_status" : ", '' AS vehicle_status") .
                             ($vehHasCrNo ? ", v.cr_number" : ", '' AS cr_number") .
                             ($vehHasCrIssue ? ", v.cr_issue_date" : ", '' AS cr_issue_date") .
                             ($vehHasOwner ? ", v.registered_owner" : ", '' AS registered_owner") .
                             ",
                             {$opNameExpr} AS operator_name,
                             vr.registration_status,
                             vr.orcr_no, vr.orcr_date" .
                      ($vrHasOrNo ? ", vr.or_number" : ", '' AS or_number") .
                      ($vrHasOrDate ? ", vr.or_date" : ", '' AS or_date") .
                      ($vrHasOrExp ? ", vr.or_expiry_date" : ", '' AS or_expiry_date") .
                      ($vrHasRegYear ? ", vr.registration_year" : ", '' AS registration_year") . "
                      FROM vehicles v
                      LEFT JOIN operators o ON o.id={$opIdExpr}
                      LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id
                      WHERE v.id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $vehicleId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
  exit;
}

$plate = (string)($row['plate_number'] ?? '');
$crFile = '';
$orFile = '';
$hasDocs = $hasTable('documents');
$hasExpiry = $hasDocs ? $hasCol('documents', 'expiry_date') : false;
$hasUploadedAt = $hasDocs ? $hasCol('documents', 'uploaded_at') : false;
$hasId = $hasDocs ? $hasCol('documents', 'id') : false;

$orderParts = [];
if ($hasUploadedAt) $orderParts[] = "uploaded_at DESC";
if ($hasId) $orderParts[] = "id DESC";
$orderSql = $orderParts ? (" ORDER BY " . implode(", ", $orderParts)) : "";

$stmtDoc = $hasDocs ? $db->prepare("SELECT type, file_path" . ($hasExpiry ? ", expiry_date" : "") . " FROM documents WHERE plate_number=? AND LOWER(type) IN ('cr','or')" . $orderSql) : null;
if ($stmtDoc) {
  $stmtDoc->bind_param('s', $plate);
  $stmtDoc->execute();
  $res = $stmtDoc->get_result();
  while ($res && ($d = $res->fetch_assoc())) {
    $t = strtolower((string)($d['type'] ?? ''));
    if ($t === 'cr' && $crFile === '') $crFile = (string)($d['file_path'] ?? '');
    if ($t === 'or' && $orFile === '') $orFile = (string)($d['file_path'] ?? '');
    if ($crFile !== '' && $orFile !== '') break;
  }
  $stmtDoc->close();
}

$ownerFallback = trim((string)($row['registered_owner'] ?? ''));
$opNameFallback = trim((string)($row['operator_name'] ?? ''));
if ($ownerFallback === '' && $opNameFallback !== '' && $opNameFallback !== '-') $ownerFallback = $opNameFallback;

echo json_encode(['ok' => true, 'data' => [
  'vehicle' => [
    'id' => (int)$row['id'],
    'plate_number' => $plate,
    'vehicle_type' => (string)($row['vehicle_type'] ?? ''),
    'status' => (string)($row['vehicle_status'] ?? ''),
    'engine_no' => (string)($row['engine_no'] ?? ''),
    'chassis_no' => (string)($row['chassis_no'] ?? ''),
    'make' => (string)($row['make'] ?? ''),
    'model' => (string)($row['model'] ?? ''),
    'year_model' => (string)($row['year_model'] ?? ''),
    'fuel_type' => (string)($row['fuel_type'] ?? ''),
    'color' => (string)($row['color'] ?? ''),
    'operator_name' => (string)($row['operator_name'] ?? ''),
    'cr_number' => (string)($row['cr_number'] ?? ''),
    'cr_issue_date' => (string)($row['cr_issue_date'] ?? ''),
    'registered_owner' => $ownerFallback,
    'cr_file_path' => $crFile,
  ],
  'registration' => [
    'registration_status' => (string)($row['registration_status'] ?? ''),
    'or_number' => (string)($row['or_number'] ?? ''),
    'or_date' => (string)($row['or_date'] ?? ''),
    'or_expiry_date' => (string)($row['or_expiry_date'] ?? ''),
    'registration_year' => (string)($row['registration_year'] ?? ''),
    'or_file_path' => $orFile,
  ],
]]);
