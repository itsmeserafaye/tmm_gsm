<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/util.php';
require_once __DIR__ . '/../../includes/violation_escalation.php';
require_permission('module3.issue');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$ticketNo = trim((string)($_POST['sts_ticket_no'] ?? ''));
$ticketNo = preg_replace('/\s+/', '', $ticketNo);
$ticketNo = substr($ticketNo, 0, 64);
if ($ticketNo === '' || !preg_match('/^(?:[0-9A-Za-z\/]|-){3,64}$/', $ticketNo)) error_response(400, 'invalid_ticket_no');

$issuedBy = substr(trim((string)($_POST['issued_by'] ?? '')), 0, 128);
$dateIssued = trim((string)($_POST['date_issued'] ?? ''));
$fineAmount = (float)($_POST['fine_amount'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'Pending Payment'));
$notes = trim((string)($_POST['verification_notes'] ?? ''));
$linkedViolationId = (int)($_POST['linked_violation_id'] ?? 0);
if ($fineAmount < 0) $fineAmount = 0;
if ($fineAmount > 999999) $fineAmount = 999999;
$allowed = ['Pending Payment','Paid','Closed'];
if (!in_array($status, $allowed, true)) $status = 'Pending Payment';

$dateSql = null;
if ($dateIssued !== '') {
  $ts = strtotime($dateIssued);
  if ($ts === false) error_response(400, 'invalid_date_issued');
  $dateSql = date('Y-m-d', $ts);
}

$scanPath = null;
if (isset($_FILES['ticket_scan']) && is_array($_FILES['ticket_scan']) && (int)($_FILES['ticket_scan']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  $f = $_FILES['ticket_scan'];
  $orig = (string)($f['name'] ?? '');
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) error_response(400, 'invalid_scan_type');
  $dir = __DIR__ . '/../../uploads/sts_tickets';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  $filename = 'STS_' . $ticketNo . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file((string)$f['tmp_name'], $dest)) error_response(400, 'scan_upload_failed');
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) { if (is_file($dest)) @unlink($dest); error_response(400, 'file_failed_security_scan'); }
  $scanPath = 'sts_tickets/' . $filename;
}

if ($linkedViolationId > 0) {
  $stmtV = $db->prepare("SELECT id FROM violations WHERE id=? LIMIT 1");
  if (!$stmtV) error_response(500, 'db_prepare_failed');
  $stmtV->bind_param('i', $linkedViolationId);
  $stmtV->execute();
  $v = $stmtV->get_result()->fetch_assoc();
  $stmtV->close();
  if (!$v) error_response(400, 'invalid_linked_violation');
}

$stmt = $db->prepare("INSERT INTO sts_tickets (sts_ticket_no, issued_by, date_issued, fine_amount, status, verification_notes, linked_violation_id, ticket_scan_path)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) error_response(500, 'db_prepare_failed');
$linkBind = $linkedViolationId > 0 ? $linkedViolationId : 0;
$stmt->bind_param('sssdssis', $ticketNo, $issuedBy, $dateSql, $fineAmount, $status, $notes, $linkBind, $scanPath);
$ok = $stmt->execute();
$id = (int)$db->insert_id;
$stmt->close();

if (!$ok) error_response(500, 'db_error');
if ($linkedViolationId > 0) {
  $stmtCtx = $db->prepare("SELECT plate_number, violation_type, operator_id, vehicle_id, COALESCE(NULLIF(violation_date,''), created_at) AS observed_at
                           FROM violations WHERE id=? LIMIT 1");
  if ($stmtCtx) {
    $stmtCtx->bind_param('i', $linkedViolationId);
    $stmtCtx->execute();
    $vctx = $stmtCtx->get_result()->fetch_assoc();
    $stmtCtx->close();
    if ($vctx) {
      $obs = (string)($vctx['observed_at'] ?? '');
      if ($dateSql !== null && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $dateSql)) $obs = $dateSql . ' 00:00:00';
      tmm_apply_progressive_violation_policy($db, [
        'plate_number' => (string)($vctx['plate_number'] ?? ''),
        'violation_code' => (string)($vctx['violation_type'] ?? ''),
        'operator_id' => (int)($vctx['operator_id'] ?? 0),
        'vehicle_id' => (int)($vctx['vehicle_id'] ?? 0),
        'observed_at' => $obs !== '' ? $obs : date('Y-m-d H:i:s'),
      ]);
    }
  }
}
tmm_audit_event($db, 'STS_TICKET_RECORDED', 'STSTicket', (string)$id, ['sts_ticket_no' => $ticketNo, 'status' => $status]);
echo json_encode(['ok' => true, 'sts_ticket_id' => $id]);
