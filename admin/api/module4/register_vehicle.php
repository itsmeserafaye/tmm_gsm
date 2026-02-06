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
$regYear = trim((string)($_POST['registration_year'] ?? ''));

$hasOrFile = isset($_FILES['or_file']) && is_array($_FILES['or_file']) && (int)($_FILES['or_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

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
if ($hasOrFile && $orExpiry === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'or_expiry_required']);
  exit;
}

$stmtV = $db->prepare("SELECT id, plate_number, operator_id FROM vehicles WHERE id=? LIMIT 1");
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
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'cr_not_found_in_puv_db']);
  exit;
}

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

$computeRegStatus = function () use ($orExpiry, $orNumber, $orDate, $hasOrFile): string {
  $today = date('Y-m-d');
  if ($orExpiry !== '' && $orExpiry < $today) return 'Expired';
  $hasMeta = ($orNumber !== '' || $orDate !== '');
  if ($hasOrFile || $hasMeta) return 'Registered';
  return 'Pending';
};

$registrationStatus = $computeRegStatus();

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

  if ($hasOrFile) {
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
    if ($frOk && $insp === 'Passed' && $regOk) $next = 'Active';
    else if ($insp === 'Passed' && $regOk) $next = 'Registered';
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
  echo json_encode(['ok' => true, 'message' => 'Vehicle registration saved', 'vehicle_id' => $vehicleId, 'registration_status' => $registrationStatus]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(400);
  $msg = $e->getMessage();
  echo json_encode(['ok' => false, 'error' => $msg !== '' ? $msg : 'db_error']);
}
