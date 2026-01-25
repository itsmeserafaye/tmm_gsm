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

$vrHasOrNo = $hasCol('vehicle_registrations', 'or_number');
$vrHasOrDate = $hasCol('vehicle_registrations', 'or_date');
$vrHasOrExp = $hasCol('vehicle_registrations', 'or_expiry_date');
$vrHasRegYear = $hasCol('vehicle_registrations', 'registration_year');

$stmt = $db->prepare("SELECT v.id, v.plate_number, v.vehicle_type, v.engine_no, v.chassis_no, v.make, v.model, v.year_model, v.fuel_type, v.color,
                             v.cr_number, v.cr_issue_date, v.registered_owner,
                             COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '-') AS operator_name,
                             vr.registration_status,
                             vr.orcr_no, vr.orcr_date" .
                      ($vrHasOrNo ? ", vr.or_number" : ", '' AS or_number") .
                      ($vrHasOrDate ? ", vr.or_date" : ", '' AS or_date") .
                      ($vrHasOrExp ? ", vr.or_expiry_date" : ", '' AS or_expiry_date") .
                      ($vrHasRegYear ? ", vr.registration_year" : ", '' AS registration_year") . "
                      FROM vehicles v
                      LEFT JOIN operators o ON o.id=COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0))
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
$hasExpiry = $hasCol('documents', 'expiry_date');

$stmtDoc = $db->prepare("SELECT type, file_path" . ($hasExpiry ? ", expiry_date" : "") . " FROM documents WHERE plate_number=? AND LOWER(type) IN ('cr','or') ORDER BY uploaded_at DESC, id DESC");
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

echo json_encode(['ok' => true, 'data' => [
  'vehicle' => [
    'id' => (int)$row['id'],
    'plate_number' => $plate,
    'vehicle_type' => (string)($row['vehicle_type'] ?? ''),
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
    'registered_owner' => (string)($row['registered_owner'] ?? ''),
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

