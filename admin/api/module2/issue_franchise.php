<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
 
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}
 
$appId = (int)($_POST['application_id'] ?? 0);
$approvedAreaId = (int)($_POST['approved_service_area_id'] ?? 0);
$approvedUnitsIn = (int)($_POST['approved_units'] ?? 0);
$remarks = trim((string)($_POST['remarks'] ?? ''));
$remarks = substr($remarks, 0, 1500);
$issueDateRaw = trim((string)($_POST['issue_date'] ?? ''));
$validYears = (int)($_POST['validity_years'] ?? 1);
if ($validYears <= 0) $validYears = 1;
if ($validYears > 5) $validYears = 5;
 
if ($appId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}
 
$issueDate = $issueDateRaw !== '' ? $issueDateRaw : date('Y-m-d');
if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $issueDate)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_issue_date']);
  exit;
}
 
$dt = DateTime::createFromFormat('Y-m-d', $issueDate);
if (!$dt) {
  echo json_encode(['ok' => false, 'error' => 'invalid_issue_date']);
  exit;
}
$expiry = (clone $dt)->modify('+' . $validYears . ' year')->format('Y-m-d');
 
$db->begin_transaction();
try {
  $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, operator_id, service_area_id, vehicle_type, vehicle_count, approved_vehicle_count, status
                         FROM franchise_applications
                         WHERE application_id=? FOR UPDATE");
  if (!$stmtA) throw new Exception('db_prepare_failed');
  $stmtA->bind_param('i', $appId);
  $stmtA->execute();
  $app = $stmtA->get_result()->fetch_assoc();
  $stmtA->close();
 
  if (!$app) { $db->rollback(); echo json_encode(['ok' => false, 'error' => 'application_not_found']); exit; }
  if ((string)($app['status'] ?? '') !== 'Approved') {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'invalid_status']);
    exit;
  }
  $vehicleType = (string)($app['vehicle_type'] ?? '');
  if ($vehicleType !== '' && stripos($vehicleType, 'tricycle') === false) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'tricycle_only']);
    exit;
  }
 
  $chk = $db->prepare("SELECT franchise_id FROM franchises WHERE application_id=? LIMIT 1");
  if ($chk) {
    $chk->bind_param('i', $appId);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($existing) {
      $db->rollback();
      echo json_encode(['ok' => false, 'error' => 'already_issued']);
      exit;
    }
  }
 
  $approvedUnits = (int)($app['approved_vehicle_count'] ?? 0);
  if ($approvedUnits <= 0) $approvedUnits = (int)($app['vehicle_count'] ?? 0);
  if ($approvedUnits <= 0) $approvedUnits = 1;
  if ($approvedUnitsIn > 0) $approvedUnits = $approvedUnitsIn;
  if ($approvedUnits <= 0) $approvedUnits = 1;

  $areaId = $approvedAreaId > 0 ? $approvedAreaId : (int)($app['service_area_id'] ?? 0);
  if ($areaId <= 0) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => 'missing_service_area']);
    exit;
  }

  $capCheck = tmm_service_area_capacity_check($db, $areaId, $approvedUnits, $appId);
  if (!($capCheck['ok'] ?? false)) {
    $db->rollback();
    echo json_encode(['ok' => false, 'error' => (string)($capCheck['error'] ?? 'capacity_error'), 'cap' => $capCheck['cap'] ?? null, 'used' => $capCheck['used'] ?? null, 'want' => $capCheck['want'] ?? null]);
    exit;
  }
 
  $issuedByUserId = (int)($_SESSION['user_id'] ?? 0);
  $issuedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($issuedByName === '') $issuedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($issuedByName === '') $issuedByName = 'Staff';
 
  $certificateNo = 'TRI-' . date('Y') . '-' . str_pad((string)$appId, 6, '0', STR_PAD_LEFT);
 
  $stmtF = $db->prepare("INSERT INTO franchises
                         (application_id, issue_date, expiry_date, status, certificate_no, approved_units, issued_at, issued_by_user_id, issued_by_name, remarks)
                         VALUES (?, ?, ?, 'Active', ?, ?, NOW(), ?, ?, ?)");
  if (!$stmtF) throw new Exception('db_prepare_failed');
  $stmtF->bind_param('isssiiss', $appId, $issueDate, $expiry, $certificateNo, $approvedUnits, $issuedByUserId, $issuedByName, $remarks);
  if (!$stmtF->execute()) {
    $err = $stmtF->error;
    throw new Exception('insert_failed:' . ($err ?: ''));
  }
  $stmtF->close();
 
  $stmtU = $db->prepare("UPDATE franchise_applications
                         SET status='Active',
                             approved_vehicle_count=?,
                             approved_service_area_id=?,
                             approved_at=NOW(),
                             approved_by_user_id=?,
                             approved_by_name=?,
                             remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                         WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('iiisssi', $approvedUnits, $areaId, $issuedByUserId, $issuedByName, $remarks, $remarks, $appId);
  $stmtU->execute();
  $stmtU->close();
 
  $db->commit();
  tmm_audit_event($db, 'FRANCHISE_ISSUED', 'Franchise', (string)$appId, ['certificate_no' => $certificateNo, 'issue_date' => $issueDate, 'expiry_date' => $expiry, 'approved_units' => $approvedUnits, 'approved_service_area_id' => $areaId]);
  echo json_encode(['ok' => true, 'status' => 'Active', 'certificate_no' => $certificateNo, 'issue_date' => $issueDate, 'expiry_date' => $expiry, 'approved_units' => $approvedUnits, 'approved_service_area_id' => $areaId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  $msg = $e->getMessage();
  if (!is_string($msg) || $msg === '') $msg = 'db_error';
  echo json_encode(['ok' => false, 'error' => 'db_error:' . $msg]);
}
