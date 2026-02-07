<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module3.issue');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$plateRaw = (string)($_POST['plate_number'] ?? '');
$plate = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
$plate = preg_replace('/[^A-Z0-9\-]/', '', $plate);
if ($plate !== '' && strpos($plate, '-') === false) {
  $letters = substr(preg_replace('/[^A-Z]/', '', $plate), 0, 3);
  $digits = substr(preg_replace('/[^0-9]/', '', $plate), 0, 4);
  if ($letters !== '' && $digits !== '') $plate = $letters . '-' . $digits;
}
if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) error_response(400, 'invalid_plate');

$violationType = trim((string)($_POST['violation_type'] ?? ''));
if ($violationType === '') error_response(400, 'missing_violation_type');
$location = substr(trim((string)($_POST['location'] ?? '')), 0, 255);
$observedAt = trim((string)($_POST['violation_date'] ?? ''));
$remarks = trim((string)($_POST['remarks'] ?? ''));
$workflow = trim((string)($_POST['workflow_status'] ?? 'Pending'));
$allowedWorkflow = ['Pending','Verified','Closed'];
if (!in_array($workflow, $allowedWorkflow, true)) $workflow = 'Pending';

$observedAtSql = null;
if ($observedAt !== '') {
  $ts = strtotime($observedAt);
  if ($ts === false) error_response(400, 'invalid_datetime');
  $observedAtSql = date('Y-m-d H:i:s', $ts);
}

$operatorId = null;
$vehicleId = null;
$stmtVeh = $db->prepare("SELECT id, COALESCE(NULLIF(current_operator_id,0), NULLIF(operator_id,0), 0) AS op_id
                         FROM vehicles WHERE plate_number=? LIMIT 1");
if ($stmtVeh) {
  $stmtVeh->bind_param('s', $plate);
  $stmtVeh->execute();
  $row = $stmtVeh->get_result()->fetch_assoc();
  $stmtVeh->close();
  if ($row) {
    $vehicleId = (int)($row['id'] ?? 0);
    $op = (int)($row['op_id'] ?? 0);
    if ($op > 0) $operatorId = $op;
  }
}

$amount = 0.0;
$stmtVt = $db->prepare("SELECT fine_amount FROM violation_types WHERE violation_code=? LIMIT 1");
if ($stmtVt) {
  $stmtVt->bind_param('s', $violationType);
  $stmtVt->execute();
  $r = $stmtVt->get_result()->fetch_assoc();
  $stmtVt->close();
  if ($r) $amount = (float)($r['fine_amount'] ?? 0);
}

$evidencePath = null;
if (isset($_FILES['evidence']) && is_array($_FILES['evidence']) && (int)($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  $f = $_FILES['evidence'];
  $orig = (string)($f['name'] ?? '');
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) error_response(400, 'invalid_evidence_type');
  $dir = __DIR__ . '/../../uploads/violations';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  $filename = $plate . '_' . $violationType . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file((string)$f['tmp_name'], $dest)) error_response(400, 'evidence_upload_failed');
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) { if (is_file($dest)) @unlink($dest); error_response(400, 'file_failed_security_scan'); }
  $evidencePath = 'violations/' . $filename;
}

$adminUserId = (int)($_SESSION['user_id'] ?? 0);
$adminName = trim((string)($_SESSION['name'] ?? 'Admin'));
$now = date('Y-m-d H:i:s');

$stmt = $db->prepare("INSERT INTO violations
  (plate_number, violation_type, amount, status, violation_date, created_at, vehicle_id, operator_id, location, evidence_path, workflow_status, remarks, recorded_by_user_id, recorded_by_name, recorded_at)
  VALUES
  (?, ?, ?, 'Unpaid', COALESCE(?, NOW()), NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) error_response(500, 'db_prepare_failed');
$vehBind = $vehicleId && $vehicleId > 0 ? $vehicleId : 0;
$opBind = $operatorId && $operatorId > 0 ? $operatorId : 0;
$stmt->bind_param('ssdsiissssiss', $plate, $violationType, $amount, $observedAtSql, $vehBind, $opBind, $location, $evidencePath, $workflow, $remarks, $adminUserId, $adminName, $now);
$ok = $stmt->execute();
$id = (int)$db->insert_id;
$stmt->close();

if (!$ok) error_response(500, 'db_error');
tmm_audit_event($db, 'VIOLATION_RECORDED', 'Violation', (string)$id, ['plate_number' => $plate, 'violation_type' => $violationType, 'workflow_status' => $workflow]);
echo json_encode(['ok' => true, 'violation_id' => $id]);
