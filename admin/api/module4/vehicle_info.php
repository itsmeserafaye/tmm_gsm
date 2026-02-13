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
$vehHasOrNumber = $hasCol('vehicles', 'or_number');
$vehHasOrDate = $hasCol('vehicles', 'or_date');
$vehHasOrExp = $hasCol('vehicles', 'or_expiry_date');
$vehHasRegYear = $hasCol('vehicles', 'registration_year');
$vehHasInsExp = $hasCol('vehicles', 'insurance_expiry_date');

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
                             ($vehHasOrNumber ? ", v.or_number AS veh_or_number" : ", '' AS veh_or_number") .
                             ($vehHasOrDate ? ", v.or_date AS veh_or_date" : ", '' AS veh_or_date") .
                             ($vehHasOrExp ? ", v.or_expiry_date AS veh_or_expiry_date" : ", '' AS veh_or_expiry_date") .
                             ($vehHasRegYear ? ", v.registration_year AS veh_registration_year" : ", '' AS veh_registration_year") .
                             ($vehHasInsExp ? ", v.insurance_expiry_date AS veh_insurance_expiry_date" : ", '' AS veh_insurance_expiry_date") .
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
$insuranceFile = '';
$orExpiryDoc = '';
$insExpiryDoc = '';
$hasDocs = $hasTable('documents');
$hasExpiry = $hasDocs ? $hasCol('documents', 'expiry_date') : false;
$hasUploadedAt = $hasDocs ? $hasCol('documents', 'uploaded_at') : false;
$hasId = $hasDocs ? $hasCol('documents', 'id') : false;

$orderParts = [];
if ($hasUploadedAt) $orderParts[] = "uploaded_at DESC";
if ($hasId) $orderParts[] = "id DESC";
$orderSql = $orderParts ? (" ORDER BY " . implode(", ", $orderParts)) : "";

$stmtDoc = $hasDocs ? $db->prepare("SELECT type, file_path" . ($hasExpiry ? ", expiry_date" : "") . " FROM documents WHERE plate_number=? AND LOWER(type) IN ('cr','or','insurance')" . $orderSql) : null;
if ($stmtDoc) {
  $stmtDoc->bind_param('s', $plate);
  $stmtDoc->execute();
  $res = $stmtDoc->get_result();
  while ($res && ($d = $res->fetch_assoc())) {
    $t = strtolower((string)($d['type'] ?? ''));
    $exp = $hasExpiry ? (string)($d['expiry_date'] ?? '') : '';
    if ($t === 'cr' && $crFile === '') $crFile = (string)($d['file_path'] ?? '');
    if ($t === 'or' && $orFile === '') $orFile = (string)($d['file_path'] ?? '');
    if ($t === 'insurance' && $insuranceFile === '') $insuranceFile = (string)($d['file_path'] ?? '');
    if ($t === 'or' && $orExpiryDoc === '' && $exp !== '') $orExpiryDoc = $exp;
    if ($t === 'insurance' && $insExpiryDoc === '' && $exp !== '') $insExpiryDoc = $exp;
  }
  $stmtDoc->close();
}

// Fallback to vehicle_documents if missing
if (($crFile === '' || $orFile === '' || $insuranceFile === '') && $hasTable('vehicle_documents')) {
    $vdCols = $db->query("SHOW COLUMNS FROM vehicle_documents");
    $cols = [];
    while ($vdCols && ($r = $vdCols->fetch_assoc())) {
        $cols[strtolower((string)($r['Field'] ?? ''))] = true;
    }
    $idCol = isset($cols['vehicle_id']) ? 'vehicle_id' : (isset($cols['plate_number']) ? 'plate_number' : null);
    $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : null));
    $pathCol = isset($cols['file_path']) ? 'file_path' : null;
    $dateCol = isset($cols['uploaded_at']) ? 'uploaded_at' : null;
    $expCol = isset($cols['expiry_date']) ? 'expiry_date' : (isset($cols['expiration_date']) ? 'expiration_date' : null);
    
    if ($idCol && $typeCol && $pathCol) {
        $orderSql2 = $dateCol ? " ORDER BY $dateCol DESC" : " ORDER BY $pathCol DESC";
        $sql = "SELECT {$typeCol} AS t, {$pathCol} AS fp" . ($expCol ? ", {$expCol} AS exp" : ", NULL AS exp") . " FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol}) IN ('CR','OR','INSURANCE') $orderSql2";
        $stmt2 = $db->prepare($sql);
        if ($stmt2) {
            if ($idCol === 'vehicle_id') $stmt2->bind_param('i', $vehicleId);
            else $stmt2->bind_param('s', $plate);
            
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($res2 && ($d = $res2->fetch_assoc())) {
                $t = strtoupper(trim((string)($d['t'] ?? '')));
                $fp = trim((string)($d['fp'] ?? ''));
                $exp = (string)($d['exp'] ?? '');
                if ($fp === '') continue;
                
                if ($t === 'CR' && $crFile === '') $crFile = $fp;
                elseif ($t === 'OR' && $orFile === '') $orFile = $fp;
                elseif ($t === 'INSURANCE' && $insuranceFile === '') $insuranceFile = $fp;
                if ($t === 'OR' && $orExpiryDoc === '' && $exp !== '') $orExpiryDoc = $exp;
                elseif ($t === 'INSURANCE' && $insExpiryDoc === '' && $exp !== '') $insExpiryDoc = $exp;
            }
            $stmt2->close();
        }
    }
}

$orNumberOut = trim((string)($row['or_number'] ?? ''));
$orDateOut = trim((string)($row['or_date'] ?? ''));
$orExpiryOut = trim((string)($row['or_expiry_date'] ?? ''));
$regYearOut = trim((string)($row['registration_year'] ?? ''));
$insExpiryOut = trim((string)($row['veh_insurance_expiry_date'] ?? ''));

if ($orNumberOut === '') $orNumberOut = trim((string)($row['veh_or_number'] ?? ''));
if ($orDateOut === '') $orDateOut = trim((string)($row['veh_or_date'] ?? ''));
if ($orExpiryOut === '') $orExpiryOut = trim((string)($row['veh_or_expiry_date'] ?? ''));
if ($regYearOut === '') $regYearOut = trim((string)($row['veh_registration_year'] ?? ''));
if ($orExpiryOut === '' && $orExpiryDoc !== '') $orExpiryOut = $orExpiryDoc;
if ($insExpiryOut === '' && $insExpiryDoc !== '') $insExpiryOut = $insExpiryDoc;

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
    'or_number' => $orNumberOut,
    'or_date' => $orDateOut,
    'or_expiry_date' => $orExpiryOut,
    'registration_year' => $regYearOut,
    'or_file_path' => $orFile,
    'insurance_file_path' => $insuranceFile,
    'insurance_expiry_date' => $insExpiryOut,
  ],
]]);
