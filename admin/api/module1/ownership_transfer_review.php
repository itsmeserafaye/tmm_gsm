<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$transferId = isset($_POST['transfer_id']) ? (int)$_POST['transfer_id'] : 0;
$action = strtolower(trim((string)($_POST['action'] ?? '')));
$remarks = trim((string)($_POST['remarks'] ?? ''));
$effectiveDate = trim((string)($_POST['effective_date'] ?? ''));

if ($transferId <= 0 || ($action !== 'approve' && $action !== 'reject')) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}
if ($effectiveDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $effectiveDate)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_effective_date']);
  exit;
}
if ($action === 'reject' && $remarks === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'remarks_required']);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $table = trim($table);
  $col = trim($col);
  if ($table === '' || $col === '') return false;
  $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
  return $res && ($res->num_rows ?? 0) > 0;
};

$tHasOrcr = $hasCol('vehicle_ownership_transfers', 'orcr_path');
$tHasOr = $hasCol('vehicle_ownership_transfers', 'or_path');
$tHasCr = $hasCol('vehicle_ownership_transfers', 'cr_path');

$stmtT = $db->prepare("SELECT transfer_id, vehicle_id, from_operator_id, to_operator_id, transfer_type, lto_reference_no, deed_of_sale_path, status, effective_date,
                              " . ($tHasOrcr ? "orcr_path" : "NULL") . " AS orcr_path,
                              " . ($tHasOr ? "or_path" : "NULL") . " AS or_path,
                              " . ($tHasCr ? "cr_path" : "NULL") . " AS cr_path
                       FROM vehicle_ownership_transfers WHERE transfer_id=? LIMIT 1");
if (!$stmtT) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtT->bind_param('i', $transferId);
$stmtT->execute();
$t = $stmtT->get_result()->fetch_assoc();
$stmtT->close();
if (!$t) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'transfer_not_found']); exit; }

$curStatus = (string)($t['status'] ?? 'Pending');
if ($curStatus !== 'Pending') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'already_reviewed']);
  exit;
}

$vehicleId = (int)($t['vehicle_id'] ?? 0);
$toOperatorId = (int)($t['to_operator_id'] ?? 0);
$toOperatorIdPost = (int)($_POST['to_operator_id'] ?? 0);
if ($action === 'approve' && $toOperatorId <= 0 && $toOperatorIdPost > 0) {
  $toOperatorId = $toOperatorIdPost;
}
if ($vehicleId <= 0 || $toOperatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_transfer']);
  exit;
}

$stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$plate = (string)($veh['plate_number'] ?? '');

if ($action === 'approve') {
  $hasVerifiedOrcr = function () use ($db, $vehicleId, $plate, $hasCol): bool {
    $vdExists = (bool)($db->query("SHOW TABLES LIKE 'vehicle_documents'")?->fetch_row());
    $docsExists = (bool)($db->query("SHOW TABLES LIKE 'documents'")?->fetch_row());

    $vdOrcrOk = 0;
    $vdOrOk = 0;
    $vdCrOk = 0;
    if ($vdExists) {
      $vdTypeCol = $hasCol('vehicle_documents', 'doc_type') ? 'doc_type'
        : ($hasCol('vehicle_documents', 'document_type') ? 'document_type'
        : ($hasCol('vehicle_documents', 'type') ? 'type' : ''));
      $vdVerifiedCol = $hasCol('vehicle_documents', 'is_verified') ? 'is_verified'
        : ($hasCol('vehicle_documents', 'verified') ? 'verified'
        : ($hasCol('vehicle_documents', 'isApproved') ? 'isApproved' : ''));
      $vdHasVehicleId = $hasCol('vehicle_documents', 'vehicle_id');
      $vdHasPlate = $hasCol('vehicle_documents', 'plate_number');
      if ($vdTypeCol !== '' && $vdVerifiedCol !== '' && ($vdHasVehicleId || $vdHasPlate)) {
        $where = $vdHasVehicleId ? "vehicle_id=?" : "plate_number=?";
        $types = $vdHasVehicleId ? 'i' : 's';
        $val = $vdHasVehicleId ? $vehicleId : $plate;
        $orcrCond = "LOWER(`{$vdTypeCol}`) IN ('orcr','or/cr')";
        $orCond = "LOWER(`{$vdTypeCol}`)='or'";
        $crCond = "LOWER(`{$vdTypeCol}`)='cr'";
        $verCond = "COALESCE(`{$vdVerifiedCol}`,0)=1";
        $sql = "SELECT MAX(CASE WHEN {$orcrCond} AND {$verCond} THEN 1 ELSE 0 END) AS orcr_ok,
                       MAX(CASE WHEN {$orCond} AND {$verCond} THEN 1 ELSE 0 END) AS or_ok,
                       MAX(CASE WHEN {$crCond} AND {$verCond} THEN 1 ELSE 0 END) AS cr_ok
                FROM vehicle_documents WHERE {$where}";
        $stmt = $db->prepare($sql);
        if ($stmt) {
          $stmt->bind_param($types, $val);
          $stmt->execute();
          $row = $stmt->get_result()->fetch_assoc();
          $stmt->close();
          $vdOrcrOk = (int)($row['orcr_ok'] ?? 0);
          $vdOrOk = (int)($row['or_ok'] ?? 0);
          $vdCrOk = (int)($row['cr_ok'] ?? 0);
        }
      }
    }

    $legacyOrOk = 0;
    $legacyCrOk = 0;
    $legacyOrcrOk = 0;
    if ($docsExists && $plate !== '' && $hasCol('documents', 'plate_number')) {
      $docsHasExpiry = $hasCol('documents', 'expiry_date');
      $legacyOrValidCond = $docsHasExpiry ? "(expiry_date IS NULL OR expiry_date >= CURDATE())" : "1=1";
      $sql = "SELECT MAX(CASE WHEN LOWER(type)='or' AND COALESCE(verified,0)=1 AND {$legacyOrValidCond} THEN 1 ELSE 0 END) AS or_ok,
                     MAX(CASE WHEN LOWER(type)='cr' AND COALESCE(verified,0)=1 THEN 1 ELSE 0 END) AS cr_ok,
                     MAX(CASE WHEN LOWER(type) IN ('orcr','or/cr') AND COALESCE(verified,0)=1 THEN 1 ELSE 0 END) AS orcr_ok
              FROM documents WHERE plate_number=?";
      $stmt = $db->prepare($sql);
      if ($stmt) {
        $stmt->bind_param('s', $plate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $legacyOrOk = (int)($row['or_ok'] ?? 0);
        $legacyCrOk = (int)($row['cr_ok'] ?? 0);
        $legacyOrcrOk = (int)($row['orcr_ok'] ?? 0);
      }
    }

    $orcrOk = ($vdOrcrOk === 1) || ($legacyOrcrOk === 1);
    $orOk = ($vdOrOk === 1) || ($legacyOrOk === 1);
    $crOk = ($vdCrOk === 1) || ($legacyCrOk === 1);
    return $orcrOk || ($orOk && $crOk);
  };

  if ($toOperatorId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_to_operator_id']);
    exit;
  }
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

  $stmtComp = $db->prepare("SELECT COALESCE(NULLIF(compliance_status,''),'Active') AS cs FROM vehicles WHERE id=? LIMIT 1");
  if ($stmtComp) {
    $stmtComp->bind_param('i', $vehicleId);
    $stmtComp->execute();
    $rowCs = $stmtComp->get_result()->fetch_assoc();
    $stmtComp->close();
    $cs = (string)($rowCs['cs'] ?? 'Active');
    if (in_array($cs, ['Suspended', 'For Review'], true)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'vehicle_under_compliance_action', 'compliance_status' => $cs]);
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
      $hasProof = false;
      if (!empty($t['orcr_path'])) $hasProof = true;
      if (!empty($t['or_path']) && !empty($t['cr_path'])) $hasProof = true;
      if (!($hasProof || $hasVerifiedOrcr())) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'orcr_not_valid']);
        exit;
      }
    }
  }
}

$toName = '';
$stmtOp = $db->prepare("SELECT registered_name, name, full_name FROM operators WHERE id=? LIMIT 1");
if ($stmtOp) {
  $stmtOp->bind_param('i', $toOperatorId);
  $stmtOp->execute();
  $op = $stmtOp->get_result()->fetch_assoc();
  $stmtOp->close();
  $toName = trim((string)($op['registered_name'] ?? ''));
  if ($toName === '') $toName = trim((string)($op['name'] ?? ''));
  if ($toName === '') $toName = trim((string)($op['full_name'] ?? ''));
}
if ($toName === '') $toName = 'Operator #' . $toOperatorId;

$userId = (int)($_SESSION['user_id'] ?? 0);
$newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
$eff = $effectiveDate !== '' ? $effectiveDate : (string)($t['effective_date'] ?? '');
if ($eff === '') $eff = null;
$remarksBind = $remarks !== '' ? $remarks : null;

$db->begin_transaction();
try {
  $stmtUp = $db->prepare("UPDATE vehicle_ownership_transfers
                          SET status=?, reviewed_by=?, reviewed_at=NOW(), remarks=?, effective_date=COALESCE(?, effective_date), to_operator_id=COALESCE(NULLIF(?,0), to_operator_id)
                          WHERE transfer_id=?");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('sissii', $newStatus, $userId, $remarksBind, $eff, $toOperatorId, $transferId);
  if (!$stmtUp->execute()) throw new Exception('update_failed');
  $stmtUp->close();

  if ($action === 'approve') {
    $stmtVeh = $db->prepare("UPDATE vehicles
                             SET operator_id=?, current_operator_id=?, operator_name=?, record_status='Linked', ownership_status='Active'
                             WHERE id=?");
    if (!$stmtVeh) throw new Exception('db_prepare_failed');
    $stmtVeh->bind_param('iisi', $toOperatorId, $toOperatorId, $toName, $vehicleId);
    if (!$stmtVeh->execute()) throw new Exception('vehicle_update_failed');
    $stmtVeh->close();

    $stmtLog = $db->prepare("INSERT INTO ownership_transfers (plate_number, new_operator_name, deed_ref) VALUES (?, ?, ?)");
    if ($stmtLog) {
      $deedRef = (string)($t['lto_reference_no'] ?? '');
      if ($deedRef === '') $deedRef = (string)($t['deed_of_sale_path'] ?? '');
      $stmtLog->bind_param('sss', $plate, $toName, $deedRef);
      $stmtLog->execute();
      $stmtLog->close();
    }
  }

  $db->commit();
  echo json_encode(['ok' => true, 'transfer_id' => $transferId, 'status' => $newStatus]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
