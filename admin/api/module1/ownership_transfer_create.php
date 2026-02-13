<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_permission('module1.vehicles.write');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
$toOperatorId = isset($_POST['to_operator_id']) ? (int)$_POST['to_operator_id'] : 0;
$transferType = trim((string)($_POST['transfer_type'] ?? 'Reassignment'));
$ltoRef = trim((string)($_POST['lto_reference_no'] ?? ''));
$effectiveDate = trim((string)($_POST['effective_date'] ?? ''));

$allowedTypes = ['Sale','Donation','Inheritance','Reassignment'];
if (!in_array($transferType, $allowedTypes, true)) $transferType = 'Reassignment';
if ($vehicleId <= 0 || $toOperatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
if ($effectiveDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $effectiveDate)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_effective_date']);
  exit;
}

$stmtV = $db->prepare("SELECT id, plate_number, operator_id FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }

$plate = (string)($veh['plate_number'] ?? '');
$fromOperatorId = (int)($veh['operator_id'] ?? 0);
if ($fromOperatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
  exit;
}
if ($fromOperatorId === $toOperatorId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'same_operator']);
  exit;
}

$stmtTo = $db->prepare("SELECT id FROM operators WHERE id=? LIMIT 1");
if (!$stmtTo) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtTo->bind_param('i', $toOperatorId);
$stmtTo->execute();
$toOp = $stmtTo->get_result()->fetch_assoc();
$stmtTo->close();
if (!$toOp) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'to_operator_not_found']); exit; }

$stmtTix = $db->prepare("SELECT 1 FROM tickets WHERE vehicle_plate=? AND status IN ('Unpaid','Pending','Validated','Escalated') LIMIT 1");
if ($stmtTix) {
  $stmtTix->bind_param('s', $plate);
  $stmtTix->execute();
  $hasTix = $stmtTix->get_result()->fetch_row();
  $stmtTix->close();
  if ($hasTix) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'active_violations']);
    exit;
  }
}

$stmtReg = $db->prepare("SELECT registration_status FROM vehicle_registrations WHERE vehicle_id=? ORDER BY registration_id DESC LIMIT 1");
if ($stmtReg) {
  $stmtReg->bind_param('i', $vehicleId);
  $stmtReg->execute();
  $reg = $stmtReg->get_result()->fetch_assoc();
  $stmtReg->close();
  $rs = (string)($reg['registration_status'] ?? '');
  if ($rs === '' || strcasecmp($rs, 'Expired') === 0 || strcasecmp($rs, 'Pending') === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'orcr_not_valid']);
    exit;
  }
} else {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'orcr_not_valid']);
  exit;
}

  $frStatusCol = '';
  $chkFs = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchises' AND COLUMN_NAME='franchise_status' LIMIT 1");
  if ($chkFs && $chkFs->fetch_row()) $frStatusCol = 'franchise_status';
  $chkS = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='franchises' AND COLUMN_NAME='status' LIMIT 1");
  if ($frStatusCol === '' && $chkS && $chkS->fetch_row()) $frStatusCol = 'status';
if ($frStatusCol !== '') {
  $stmtF = $db->prepare("SELECT 1
                         FROM franchises f
                         JOIN franchise_applications a ON a.application_id=f.application_id
                         WHERE a.operator_id=?
                           AND f.$frStatusCol='Active'
                           AND (f.expiry_date IS NULL OR f.expiry_date >= CURDATE())
                         LIMIT 1");
  if ($stmtF) {
    $stmtF->bind_param('i', $fromOperatorId);
    $stmtF->execute();
    $row = $stmtF->get_result()->fetch_row();
    $stmtF->close();
    if ($row) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'franchise_active']);
      exit;
    }
  }
}

$uploadsDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadsDir)) {
  mkdir($uploadsDir, 0777, true);
}

$saveUpload = function (string $field, string $prefix) use ($uploadsDir): ?string {
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
  $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
    throw new Exception('invalid_file_type');
  }
  $filename = $prefix . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
  $dest = $uploadsDir . '/' . $filename;
  if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
    throw new Exception('move_failed');
  }
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) @unlink($dest);
    throw new Exception('security_scan_failed');
  }
  return $filename;
};

try {
  $deedPath = $saveUpload('deed_doc', 'transfer_deed_vehicle_' . $vehicleId);
  if (!$deedPath) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_deed_doc']);
    exit;
  }
  $orPath = $saveUpload('or_doc', 'transfer_or_vehicle_' . $vehicleId);
  $crPath = $saveUpload('cr_doc', 'transfer_cr_vehicle_' . $vehicleId);
  $orcrPath = $saveUpload('orcr_doc', 'transfer_orcr_vehicle_' . $vehicleId);

  $stmt = $db->prepare("INSERT INTO vehicle_ownership_transfers
                        (vehicle_id, from_operator_id, to_operator_id, transfer_type, lto_reference_no, deed_of_sale_path, orcr_path, or_path, cr_path, status, effective_date, remarks)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NULL)");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $effBind = $effectiveDate !== '' ? $effectiveDate : null;
  $ltoBind = $ltoRef !== '' ? $ltoRef : null;
  $orcrBind = $orcrPath !== null ? $orcrPath : null;
  $orBind = $orPath !== null ? $orPath : null;
  $crBind = $crPath !== null ? $crPath : null;
  $stmt->bind_param('iiisssssss', $vehicleId, $fromOperatorId, $toOperatorId, $transferType, $ltoBind, $deedPath, $orcrBind, $orBind, $crBind, $effBind);
  $ok = $stmt->execute();
  if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']); exit; }
  $id = (int)$stmt->insert_id;
  $stmt->close();

  echo json_encode(['ok' => true, 'transfer_id' => $id, 'plate_number' => $plate]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e instanceof Exception ? $e->getMessage() : 'upload_failed']);
}
