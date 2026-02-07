<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_response(405, 'method_not_allowed');
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($submissionId <= 0) error_response(400, 'invalid_submission_id');
if (!in_array($decision, ['approve','reject'], true)) error_response(400, 'invalid_decision');

$stmt = $db->prepare("SELECT * FROM operator_record_submissions WHERE submission_id=? LIMIT 1");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) error_response(404, 'submission_not_found');
if ((string)($sub['status'] ?? '') !== 'Submitted') error_response(400, 'submission_not_pending');

$adminUserId = (int)($_SESSION['user_id'] ?? 0);
$adminName = trim((string)($_SESSION['name'] ?? 'Admin'));
if ($adminUserId <= 0) error_response(401, 'not_authenticated');

$portalUserId = (int)($sub['portal_user_id'] ?? 0);
$operatorType = trim((string)($sub['operator_type'] ?? 'Individual'));
$registeredName = trim((string)($sub['registered_name'] ?? ''));
$name = trim((string)($sub['name'] ?? ''));
$address = trim((string)($sub['address'] ?? ''));
$contactNo = trim((string)($sub['contact_no'] ?? ''));
$email = strtolower(trim((string)($sub['email'] ?? '')));
$coopName = trim((string)($sub['coop_name'] ?? ''));
$submittedAt = trim((string)($sub['submitted_at'] ?? ''));
$submittedByName = trim((string)($sub['submitted_by_name'] ?? ''));

$now = date('Y-m-d H:i:s');

$db->begin_transaction();
try {
  if ($decision === 'reject') {
    $stmtUp = $db->prepare("UPDATE operator_record_submissions
                            SET status='Rejected', approved_by_user_id=?, approved_by_name=?, approved_at=?, approval_remarks=?
                            WHERE submission_id=?");
    if (!$stmtUp) throw new Exception('db_prepare_failed');
    $stmtUp->bind_param('isssi', $adminUserId, $adminName, $now, $remarks, $submissionId);
    $stmtUp->execute();
    $stmtUp->close();

    tmm_audit_event($db, 'PUV_OPERATOR_REJECTED', 'OperatorSubmission', (string)$submissionId, ['remarks' => $remarks]);
    $db->commit();
    echo json_encode(['ok' => true]);
    exit;
  }

  $operatorId = 0;
  $stmtFind = $db->prepare("SELECT id FROM operators WHERE portal_user_id=? LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('i', $portalUserId);
    $stmtFind->execute();
    $row = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    if ($row) $operatorId = (int)($row['id'] ?? 0);
  }
  if ($operatorId <= 0 && $email !== '') {
    $stmtFind2 = $db->prepare("SELECT id FROM operators WHERE email=? ORDER BY id DESC LIMIT 1");
    if ($stmtFind2) {
      $stmtFind2->bind_param('s', $email);
      $stmtFind2->execute();
      $row2 = $stmtFind2->get_result()->fetch_assoc();
      $stmtFind2->close();
      if ($row2) $operatorId = (int)($row2['id'] ?? 0);
    }
  }

  $displayName = $registeredName !== '' ? $registeredName : ($name !== '' ? $name : $submittedByName);
  if ($displayName === '') $displayName = 'Operator';

  if ($operatorId > 0) {
    $stmtOp = $db->prepare("UPDATE operators
                            SET operator_type=?, registered_name=?, name=?, full_name=?, address=?, contact_no=?, email=?, coop_name=?,
                                portal_user_id=?, submitted_by_name=?, submitted_at=?,
                                approved_by_user_id=?, approved_by_name=?, approved_at=?,
                                verification_status='Verified', workflow_status='Active'
                            WHERE id=?");
    if (!$stmtOp) throw new Exception('db_prepare_failed');
    $stmtOp->bind_param(
      'ssssssssississi',
      $operatorType, $registeredName, $name, $displayName, $address, $contactNo, $email, $coopName,
      $portalUserId, $submittedByName, $submittedAt,
      $adminUserId, $adminName, $now,
      $operatorId
    );
    $stmtOp->execute();
    $stmtOp->close();
  } else {
    $stmtIns = $db->prepare("INSERT INTO operators (operator_type, registered_name, name, full_name, address, contact_no, email, coop_name, status, verification_status, workflow_status,
                                                   portal_user_id, submitted_by_name, submitted_at, approved_by_user_id, approved_by_name, approved_at, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', 'Verified', 'Active', ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmtIns) throw new Exception('db_prepare_failed');
    $stmtIns->bind_param(
      'ssssssssississ',
      $operatorType, $registeredName, $name, $displayName, $address, $contactNo, $email, $coopName,
      $portalUserId, $submittedByName, $submittedAt,
      $adminUserId, $adminName, $now
    );
    $stmtIns->execute();
    $operatorId = (int)$db->insert_id;
    $stmtIns->close();
  }

  $stmtLink = $db->prepare("UPDATE operator_portal_users SET puv_operator_id=? WHERE id=?");
  if ($stmtLink) {
    $stmtLink->bind_param('ii', $operatorId, $portalUserId);
    $stmtLink->execute();
    $stmtLink->close();
  }

  $stmtUp = $db->prepare("UPDATE operator_record_submissions
                          SET status='Approved', approved_by_user_id=?, approved_by_name=?, approved_at=?, approval_remarks=?, operator_id=?
                          WHERE submission_id=?");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('isssii', $adminUserId, $adminName, $now, $remarks, $operatorId, $submissionId);
  $stmtUp->execute();
  $stmtUp->close();

  tmm_audit_event($db, 'PUV_OPERATOR_APPROVED', 'Operator', (string)$operatorId, ['submission_id' => $submissionId]);

  $db->commit();
  echo json_encode(['ok' => true, 'operator_id' => $operatorId]);
} catch (Throwable $e) {
  $db->rollback();
  error_response(500, 'db_error', ['message' => $e->getMessage()]);
}
