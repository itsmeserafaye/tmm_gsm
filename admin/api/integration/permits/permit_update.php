<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_integration_authorize();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_json']);
  exit;
}

$referenceNo = trim((string)($payload['reference_no'] ?? ''));
$permit = $payload['permit'] ?? null;
if ($referenceNo === '' || !is_array($permit)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$status = strtoupper(trim((string)($permit['status'] ?? '')));
$permitNumber = trim((string)($permit['permit_number'] ?? ''));
$issuedDate = trim((string)($permit['issued_date'] ?? ''));
$expiryDate = trim((string)($permit['expiry_date'] ?? ''));
$remarks = trim((string)($permit['remarks'] ?? ''));
$permitsCaseId = trim((string)($permit['permits_case_id'] ?? ($payload['permits_case_id'] ?? '')));

$appId = null;
if (preg_match('/^APP-(\d+)/i', $referenceNo, $m)) $appId = (int)$m[1];
elseif (ctype_digit($referenceNo)) $appId = (int)$referenceNo;

if ($appId !== null) {
  $stmt = $db->prepare("SELECT application_id, franchise_ref_number FROM franchise_applications WHERE application_id=? LIMIT 1");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('i', $appId);
} else {
  $stmt = $db->prepare("SELECT application_id, franchise_ref_number FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('s', $referenceNo);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'application_not_found']);
  exit;
}

$applicationId = (int)($row['application_id'] ?? 0);
$frRef = (string)($row['franchise_ref_number'] ?? '');

$now = date('Y-m-d H:i:s');
$stmtU = $db->prepare("UPDATE franchise_applications SET permits_case_id=?, permit_status=?, permit_number=?, permit_issued_date=NULLIF(?,''), permit_expiry_date=NULLIF(?,''), permit_remarks=?, permit_updated_at=? WHERE application_id=?");
if (!$stmtU) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtU->bind_param('sssssssi', $permitsCaseId, $status, $permitNumber, $issuedDate, $expiryDate, $remarks, $now, $applicationId);
$ok = $stmtU->execute();
$stmtU->close();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'update_failed']);
  exit;
}

$mappedAppStatus = null;
if ($status === 'APPROVED') $mappedAppStatus = 'Endorsed';
elseif ($status === 'REJECTED' || $status === 'CANCELLED' || $status === 'REVOKED') $mappedAppStatus = 'Rejected';
elseif ($status === 'UNDER_REVIEW' || $status === 'RECEIVED' || $status === 'FOR_PAYMENT' || $status === 'ON_HOLD') $mappedAppStatus = 'Under Review';

if ($mappedAppStatus !== null) {
  $stmtS = $db->prepare("UPDATE franchise_applications SET status=? WHERE application_id=?");
  if ($stmtS) {
    $stmtS->bind_param('si', $mappedAppStatus, $applicationId);
    $stmtS->execute();
    $stmtS->close();
  }
}

$stmtE = $db->prepare("SELECT endorsement_id FROM endorsement_records WHERE application_id=? ORDER BY endorsement_id DESC LIMIT 1");
$endorsementId = 0;
if ($stmtE) {
  $stmtE->bind_param('i', $applicationId);
  $stmtE->execute();
  $e = $stmtE->get_result()->fetch_assoc();
  $stmtE->close();
  $endorsementId = (int)($e['endorsement_id'] ?? 0);
}

if ($endorsementId > 0) {
  $stmtEU = $db->prepare("UPDATE endorsement_records SET permits_case_id=?, permit_number=?, issued_date=NULLIF(?,''), expiry_date=NULLIF(?,''), status=?, remarks=? WHERE endorsement_id=?");
  if ($stmtEU) {
    $stmtEU->bind_param('ssssssi', $permitsCaseId, $permitNumber, $issuedDate, $expiryDate, $status, $remarks, $endorsementId);
    $stmtEU->execute();
    $stmtEU->close();
  }
} else {
  $stmtEI = $db->prepare("INSERT INTO endorsement_records(application_id, issued_date, permit_number, permits_case_id, expiry_date, status, remarks) VALUES(?, NULLIF(?,''), ?, ?, NULLIF(?,''), ?, ?)");
  if ($stmtEI) {
    $stmtEI->bind_param('issssss', $applicationId, $issuedDate, $permitNumber, $permitsCaseId, $expiryDate, $status, $remarks);
    $stmtEI->execute();
    $stmtEI->close();
  }
}

if ($frRef !== '') {
  if ($status === 'APPROVED') {
    $stmtV = $db->prepare("UPDATE vehicles SET status='Active' WHERE franchise_id=? AND (status IS NULL OR status='' OR status='Suspended')");
    if ($stmtV) { $stmtV->bind_param('s', $frRef); $stmtV->execute(); $stmtV->close(); }
  } elseif ($status === 'REJECTED' || $status === 'CANCELLED' || $status === 'REVOKED' || $status === 'SUSPENDED' || $status === 'ON_HOLD') {
    $stmtV = $db->prepare("UPDATE vehicles SET status='Suspended' WHERE franchise_id=? AND status='Active'");
    if ($stmtV) { $stmtV->bind_param('s', $frRef); $stmtV->execute(); $stmtV->close(); }
  }
}

echo json_encode(['ok' => true, 'reference_no' => $referenceNo, 'application_id' => $applicationId, 'permit_status' => $status]);

