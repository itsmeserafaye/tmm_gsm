<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$orNumberRaw = (string)($_POST['or_number'] ?? '');
$orNumber = preg_replace('/[^0-9]/', '', trim($orNumberRaw));
$orNumber = substr($orNumber, 0, 12);
$orDate = trim((string)($_POST['or_date'] ?? ''));
$orExpiry = trim((string)($_POST['or_expiry_date'] ?? ''));
$insuranceExpiry = trim((string)($_POST['insurance_expiry_date'] ?? ''));
$regYear = trim((string)($_POST['registration_year'] ?? ''));

$hasOrUploadFile = isset($_FILES['or_file']) && is_array($_FILES['or_file']) && (int)($_FILES['or_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
$hasInsuranceUploadFile = isset($_FILES['insurance_file']) && is_array($_FILES['insurance_file']) && (int)($_FILES['insurance_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

if ($vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_vehicle_id']);
  exit;
}

if ($orDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $orDate)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_or_date']);
  exit;
}
if ($orExpiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $orExpiry)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_or_expiry_date']);
  exit;
}
if ($insuranceExpiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $insuranceExpiry)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_insurance_expiry_date']);
  exit;
}
if ($orNumber !== '' && !preg_match('/^[0-9]{6,12}$/', $orNumber)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_or_number']);
  exit;
}
if ($regYear !== '' && !preg_match('/^\d{4}$/', $regYear)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_registration_year']);
  exit;
}
if ($hasOrUploadFile && $orExpiry === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'or_expiry_required']);
  exit;
}
if ($hasInsuranceUploadFile && $insuranceExpiry === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'insurance_expiry_required']);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$vehHasOrNumber = $hasCol('vehicles', 'or_number');
$vehHasOrDate = $hasCol('vehicles', 'or_date');
$vehHasOrExp = $hasCol('vehicles', 'or_expiry_date');
$vehHasRegYear = $hasCol('vehicles', 'registration_year');
$vehHasInsExp = $hasCol('vehicles', 'insurance_expiry_date');

$stmtV = $db->prepare("SELECT id, plate_number, operator_id" .
  ($vehHasOrNumber ? ", or_number" : ", '' AS or_number") .
  ($vehHasOrDate ? ", or_date" : ", '' AS or_date") .
  ($vehHasOrExp ? ", or_expiry_date" : ", '' AS or_expiry_date") .
  ($vehHasRegYear ? ", registration_year" : ", '' AS registration_year") .
  ($vehHasInsExp ? ", insurance_expiry_date" : ", '' AS insurance_expiry_date") .
  " FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
  exit;
}
$vehOrNumber = preg_replace('/[^0-9]/', '', trim((string)($veh['or_number'] ?? '')));
$vehOrNumber = substr($vehOrNumber, 0, 12);
$vehOrDate = trim((string)($veh['or_date'] ?? ''));
$vehOrExpiry = trim((string)($veh['or_expiry_date'] ?? ''));
$vehRegYear = trim((string)($veh['registration_year'] ?? ''));
$vehInsExpiry = trim((string)($veh['insurance_expiry_date'] ?? ''));

if ($orNumber === '' && $vehOrNumber !== '') $orNumber = $vehOrNumber;
if ($orDate === '' && $vehOrDate !== '') $orDate = $vehOrDate;
if ($orExpiry === '' && $vehOrExpiry !== '') $orExpiry = $vehOrExpiry;
if ($regYear === '' && $vehRegYear !== '') $regYear = $vehRegYear;
if ($insuranceExpiry === '' && $vehInsExpiry !== '') $insuranceExpiry = $vehInsExpiry;
$operatorId = (int)($veh['operator_id'] ?? 0);
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
  exit;
}

$plate = (string)($veh['plate_number'] ?? '');
$opNameResolved = '';
$stmtOp = $db->prepare("SELECT name, full_name FROM operators WHERE id=? LIMIT 1");
if ($stmtOp) {
  $stmtOp->bind_param('i', $operatorId);
  $stmtOp->execute();
  $op = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
  if ($op) {
    $opNameResolved = trim((string)($op['name'] ?? ''));
    if ($opNameResolved === '') $opNameResolved = trim((string)($op['full_name'] ?? ''));
  }
}
$hasCr = false;
$stmtCr = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND LOWER(type)='cr' LIMIT 1");
if ($stmtCr) {
  $stmtCr->bind_param('s', $plate);
  $stmtCr->execute();
  $hasCr = (bool)$stmtCr->get_result()->fetch_assoc();
  $stmtCr->close();
}
if (!$hasCr) {
  $vd = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($vd && $vd->fetch_row()) {
    $colsRes = $db->query("SHOW COLUMNS FROM vehicle_documents");
    $cols = [];
    while ($colsRes && ($r = $colsRes->fetch_assoc())) {
      $cols[strtolower((string)($r['Field'] ?? ''))] = true;
    }
    $idCol = isset($cols['vehicle_id']) ? 'vehicle_id' : (isset($cols['plate_number']) ? 'plate_number' : null);
    $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : null));
    if ($idCol && $typeCol) {
      $sql = "SELECT 1 FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol})='CR' LIMIT 1";
      $stmtVd = $db->prepare($sql);
      if ($stmtVd) {
        if ($idCol === 'vehicle_id') $stmtVd->bind_param('i', $vehicleId);
        else $stmtVd->bind_param('s', $plate);
        $stmtVd->execute();
        $hasCr = (bool)$stmtVd->get_result()->fetch_row();
        $stmtVd->close();
      }
    }
  }
}
if (!$hasCr) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'cr_not_found_in_puv_db']);
  exit;
}

$hasOrExisting = false;
$stmtOr = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND LOWER(type)='or' LIMIT 1");
if ($stmtOr) {
  $stmtOr->bind_param('s', $plate);
  $stmtOr->execute();
  $hasOrExisting = (bool)$stmtOr->get_result()->fetch_assoc();
  $stmtOr->close();
}
$hasInsuranceExisting = false;
$stmtIns = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND LOWER(type)='insurance' LIMIT 1");
if ($stmtIns) {
  $stmtIns->bind_param('s', $plate);
  $stmtIns->execute();
  $hasInsuranceExisting = (bool)$stmtIns->get_result()->fetch_assoc();
  $stmtIns->close();
}
if ((!$hasOrExisting || !$hasInsuranceExisting)) {
  $vd2 = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($vd2 && $vd2->fetch_row()) {
  $colsRes = $db->query("SHOW COLUMNS FROM vehicle_documents");
  $cols = [];
  while ($colsRes && ($r = $colsRes->fetch_assoc())) {
    $cols[strtolower((string)($r['Field'] ?? ''))] = true;
  }
  $idCol = isset($cols['vehicle_id']) ? 'vehicle_id' : (isset($cols['plate_number']) ? 'plate_number' : null);
  $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : null));
  if ($idCol && $typeCol) {
    if (!$hasOrExisting) {
      $sql = "SELECT 1 FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol})='OR' LIMIT 1";
      $st = $db->prepare($sql);
      if ($st) {
        if ($idCol === 'vehicle_id') $st->bind_param('i', $vehicleId);
        else $st->bind_param('s', $plate);
        $st->execute();
        $hasOrExisting = (bool)$st->get_result()->fetch_row();
        $st->close();
      }
    }
    if (!$hasInsuranceExisting) {
      $sql = "SELECT 1 FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol})='INSURANCE' LIMIT 1";
      $st = $db->prepare($sql);
      if ($st) {
        if ($idCol === 'vehicle_id') $st->bind_param('i', $vehicleId);
        else $st->bind_param('s', $plate);
        $st->execute();
        $hasInsuranceExisting = (bool)$st->get_result()->fetch_row();
        $st->close();
      }
    }
  }
  }
}

$hasOrDoc = $hasOrUploadFile || $hasOrExisting;
$hasInsuranceDoc = $hasInsuranceUploadFile || $hasInsuranceExisting;

$stmtF = $db->prepare("SELECT f.franchise_id
                       FROM franchises f
                       JOIN franchise_applications a ON a.application_id=f.application_id
                       WHERE a.operator_id=? AND a.status IN ('Approved','LTFRB-Approved')
                         AND f.status='Active'
                         AND (f.expiry_date IS NULL OR f.expiry_date >= CURDATE())
                       LIMIT 1");
if ($stmtF) {
  $stmtF->bind_param('i', $operatorId);
  $stmtF->execute();
  $fr = $stmtF->get_result()->fetch_assoc();
  $stmtF->close();
  if (!$fr) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'franchise_not_active']);
    exit;
  }
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$ensureRegCols = function () use ($db, $hasCol): void {
  if (!$hasCol('vehicle_registrations', 'or_number')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN or_number VARCHAR(64) NULL"); }
  if (!$hasCol('vehicle_registrations', 'or_date')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN or_date DATE NULL"); }
  if (!$hasCol('vehicle_registrations', 'or_expiry_date')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN or_expiry_date DATE NULL"); }
  if (!$hasCol('vehicle_registrations', 'registration_year')) { @$db->query("ALTER TABLE vehicle_registrations ADD COLUMN registration_year VARCHAR(4) NULL"); }
};

$ensureOwnerCol = function () use ($db, $hasCol): void {
  if (!$hasCol('vehicles', 'registered_owner')) { @$db->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(150) NULL"); }
};

$ensureDocExpiry = function () use ($db, $hasCol): void {
  if (!$hasCol('documents', 'expiry_date')) { @$db->query("ALTER TABLE documents ADD COLUMN expiry_date DATE NULL"); }
};

$computeRegStatus = function () use ($orExpiry, $hasOrDoc): string {
  $today = date('Y-m-d');
  if ($orExpiry !== '' && $orExpiry < $today) return 'Expired';
  if ($hasOrDoc) return 'Registered';
  return 'Pending';
};

$registrationStatus = $computeRegStatus();

$getDocState = function () use ($db, $vehicleId, $plate, $registrationStatus, $hasCol): array {
  $today = date('Y-m-d');
  $out = [
    'cr_present' => false,
    'or_present' => false,
    'or_valid' => false,
    'insurance_present' => false,
    'insurance_valid' => false,
  ];

  $docsHasExpiry = $hasCol('documents', 'expiry_date');
  $expSel = $docsHasExpiry ? 'expiry_date' : 'NULL';
  $stmt = $db->prepare("SELECT LOWER(type) AS t, {$expSel} AS expiry_date FROM documents WHERE plate_number=? AND LOWER(type) IN ('cr','or','insurance')");
  if ($stmt) {
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $t = (string)($row['t'] ?? '');
      $exp = (string)($row['expiry_date'] ?? '');
      if ($t === 'cr') {
        $out['cr_present'] = true;
      } elseif ($t === 'or') {
        $out['or_present'] = true;
        if ($exp === '' || $exp >= $today) $out['or_valid'] = true;
      } elseif ($t === 'insurance') {
        $out['insurance_present'] = true;
        if ($exp === '' || $exp >= $today) $out['insurance_valid'] = true;
      }
    }
    $stmt->close();
  }

  $vd = $db->query("SHOW TABLES LIKE 'vehicle_documents'");
  if ($vd && $vd->fetch_row()) {
    $colsRes = $db->query("SHOW COLUMNS FROM vehicle_documents");
    $cols = [];
    while ($colsRes && ($r = $colsRes->fetch_assoc())) {
      $cols[strtolower((string)($r['Field'] ?? ''))] = true;
    }
    $idCol = isset($cols['vehicle_id']) ? 'vehicle_id' : (isset($cols['plate_number']) ? 'plate_number' : null);
    $typeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : null));
    $expCol = isset($cols['expiry_date']) ? 'expiry_date' : (isset($cols['expiration_date']) ? 'expiration_date' : null);
    if ($idCol && $typeCol) {
      $idIsInt = $idCol === 'vehicle_id';
      $sql = "SELECT {$typeCol} AS t" . ($expCol ? ", {$expCol} AS exp" : ", NULL AS exp") .
             " FROM vehicle_documents WHERE {$idCol}=? AND UPPER({$typeCol}) IN ('CR','OR','INSURANCE')";
      $stmt2 = $db->prepare($sql);
      if ($stmt2) {
        if ($idIsInt) $stmt2->bind_param('i', $vehicleId);
        else $stmt2->bind_param('s', $plate);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
          $t = strtoupper(trim((string)($row['t'] ?? '')));
          $exp = (string)($row['exp'] ?? '');
          if ($t === 'CR') {
            $out['cr_present'] = true;
          } elseif ($t === 'OR') {
            $out['or_present'] = true;
            if ($exp === '' || $exp >= $today) $out['or_valid'] = true;
          } elseif ($t === 'INSURANCE') {
            $out['insurance_present'] = true;
            if ($exp === '' || $exp >= $today) $out['insurance_valid'] = true;
          }
        }
        $stmt2->close();
      }
    }
  }

  if ($out['insurance_present'] && !$out['insurance_valid']) {
    $out['insurance_valid'] = false;
  }
  if ($out['insurance_present'] === false) {
    $out['insurance_valid'] = false;
  }
  return $out;
};

$db->begin_transaction();
try {
  $ensureRegCols();
  $ensureDocExpiry();
  $ensureOwnerCol();

  $orcrNoLegacy = $orNumber !== '' ? $orNumber : (trim((string)($_POST['orcr_no'] ?? '')));
  $orcrDateLegacy = $orDate !== '' ? $orDate : (trim((string)($_POST['orcr_date'] ?? '')));

  $stmtUp = $db->prepare("INSERT INTO vehicle_registrations (vehicle_id, orcr_no, orcr_date, registration_status, created_at, or_number, or_date, or_expiry_date, registration_year)
                          VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE
                            orcr_no=CASE WHEN VALUES(orcr_no)<>'' THEN VALUES(orcr_no) ELSE orcr_no END,
                            orcr_date=CASE WHEN VALUES(orcr_date)<>'' THEN VALUES(orcr_date) ELSE orcr_date END,
                            registration_status=VALUES(registration_status),
                            or_number=CASE WHEN VALUES(or_number)<>'' THEN VALUES(or_number) ELSE or_number END,
                            or_date=CASE WHEN VALUES(or_date)<>'' THEN VALUES(or_date) ELSE or_date END,
                            or_expiry_date=CASE WHEN VALUES(or_expiry_date)<>'' THEN VALUES(or_expiry_date) ELSE or_expiry_date END,
                            registration_year=CASE WHEN VALUES(registration_year)<>'' THEN VALUES(registration_year) ELSE registration_year END");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('isssssss', $vehicleId, $orcrNoLegacy, $orcrDateLegacy, $registrationStatus, $orNumber, $orDate, $orExpiry, $regYear);
  if (!$stmtUp->execute()) throw new Exception('insert_failed');
  $stmtUp->close();

  if ($hasOrUploadFile) {
    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0777, true); }
    $name = (string)($_FILES['or_file']['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) throw new Exception('invalid_or_file_type');
    $filename = $plate . '_or_' . time() . '.' . $ext;
    $dest = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file((string)$_FILES['or_file']['tmp_name'], $dest)) throw new Exception('or_upload_move_failed');
    $safe = tmm_scan_file_for_viruses($dest);
    if (!$safe) { if (is_file($dest)) @unlink($dest); throw new Exception('file_failed_security_scan'); }

    $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path, expiry_date) VALUES (?, 'or', ?, ?)");
    if (!$stmtD) throw new Exception('db_prepare_failed');
    $stmtD->bind_param('sss', $plate, $filename, $orExpiry);
    if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
    $stmtD->close();
  }

  if ($hasInsuranceUploadFile) {
    $uploadsDir = __DIR__ . '/../../uploads';
    if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0777, true); }
    $name = (string)($_FILES['insurance_file']['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) throw new Exception('invalid_insurance_file_type');
    $filename = $plate . '_insurance_' . time() . '.' . $ext;
    $dest = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file((string)$_FILES['insurance_file']['tmp_name'], $dest)) throw new Exception('insurance_upload_move_failed');
    $safe = tmm_scan_file_for_viruses($dest);
    if (!$safe) { if (is_file($dest)) @unlink($dest); throw new Exception('file_failed_security_scan'); }

    $stmtD = $db->prepare("INSERT INTO documents (plate_number, type, file_path, expiry_date) VALUES (?, 'insurance', ?, ?)");
    if (!$stmtD) throw new Exception('db_prepare_failed');
    $stmtD->bind_param('sss', $plate, $filename, $insuranceExpiry);
    if (!$stmtD->execute()) { $stmtD->close(); throw new Exception('db_insert_failed'); }
    $stmtD->close();
  }

  if ($opNameResolved !== '' && $hasCol('vehicles', 'registered_owner')) {
    $stmtOw = $db->prepare("UPDATE vehicles SET registered_owner=CASE WHEN COALESCE(NULLIF(registered_owner,''),'')='' THEN ? ELSE registered_owner END WHERE id=?");
    if ($stmtOw) {
      $stmtOw->bind_param('si', $opNameResolved, $vehicleId);
      $stmtOw->execute();
      $stmtOw->close();
    }
  }

  $stmtSt = $db->prepare("SELECT
                            MAX(CASE WHEN LOWER(type)='or' THEN 1 ELSE 0 END) AS has_or,
                            MAX(CASE WHEN LOWER(type)='or' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) THEN 1 ELSE 0 END) AS or_valid
                          FROM documents WHERE plate_number=?");
  if ($stmtSt) {
    $stmtSt->bind_param('s', $plate);
    $stmtSt->execute();
    $r = $stmtSt->get_result()->fetch_assoc();
    $stmtSt->close();
    $regOk = $registrationStatus === 'Registered';
    $insp = '';
    $frRef = '';
    $stmtV2 = $db->prepare("SELECT inspection_status, franchise_id FROM vehicles WHERE id=? LIMIT 1");
    if ($stmtV2) {
      $stmtV2->bind_param('i', $vehicleId);
      $stmtV2->execute();
      $v2 = $stmtV2->get_result()->fetch_assoc();
      $stmtV2->close();
      $insp = (string)($v2['inspection_status'] ?? '');
      $frRef = trim((string)($v2['franchise_id'] ?? ''));
    }
    $frOk = false;
    if ($frRef !== '') {
      $stmtFa = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
      if ($stmtFa) {
        $stmtFa->bind_param('s', $frRef);
        $stmtFa->execute();
        $fa = $stmtFa->get_result()->fetch_assoc();
        $stmtFa->close();
        $frOk = $fa && in_array((string)($fa['status'] ?? ''), ['Approved','LTFRB-Approved'], true);
      }
    }
    $next = null;
    $docs = $getDocState();
    $orOk = $docs['or_present'] && $docs['or_valid'];
    $insOk = $docs['insurance_present'] && $docs['insurance_valid'];
    $crOk = $docs['cr_present'];
    if ($frOk && $insp === 'Passed' && $regOk && $crOk && $orOk && $insOk) $next = 'Active';
    else if ($insp === 'Passed' && $regOk && $crOk && $orOk && $insOk) $next = 'Registered';
    if ($next !== null) {
      $stmtU = $db->prepare("UPDATE vehicles SET status=? WHERE id=?");
      if ($stmtU) {
        $stmtU->bind_param('si', $next, $vehicleId);
        $stmtU->execute();
        $stmtU->close();
      }
    }
  }

  $db->commit();
  $docs = $getDocState();
  $req = [
    'cr' => $docs['cr_present'],
    'or' => $docs['or_present'] && $docs['or_valid'],
    'insurance' => $docs['insurance_present'] && $docs['insurance_valid'],
  ];
  echo json_encode(['ok' => true, 'message' => 'Vehicle registration saved', 'vehicle_id' => $vehicleId, 'registration_status' => $registrationStatus, 'requirements' => $req]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(400);
  $msg = $e->getMessage();
  echo json_encode(['ok' => false, 'error' => $msg !== '' ? $msg : 'db_error']);
}
