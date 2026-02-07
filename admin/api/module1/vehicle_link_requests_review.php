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

$requestId = (int)($_POST['request_id'] ?? 0);
$decision = strtolower(trim((string)($_POST['decision'] ?? '')));
$remarks = trim((string)($_POST['remarks'] ?? ''));

if ($requestId <= 0) error_response(400, 'invalid_request_id');
if (!in_array($decision, ['approve','reject'], true)) error_response(400, 'invalid_decision');

$stmt = $db->prepare("SELECT * FROM vehicle_link_requests WHERE request_id=? LIMIT 1");
if (!$stmt) error_response(500, 'db_prepare_failed');
$stmt->bind_param('i', $requestId);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$req) error_response(404, 'request_not_found');
if ((string)($req['status'] ?? '') !== 'Pending') error_response(400, 'request_not_pending');

$adminUserId = (int)($_SESSION['user_id'] ?? 0);
$adminName = trim((string)($_SESSION['name'] ?? 'Admin'));
if ($adminUserId <= 0) error_response(401, 'not_authenticated');

$plate = trim((string)($req['plate_number'] ?? ''));
$operatorId = (int)($req['requested_operator_id'] ?? 0);
$now = date('Y-m-d H:i:s');

$db->begin_transaction();
try {
  if ($decision === 'reject') {
    $stmtUp = $db->prepare("UPDATE vehicle_link_requests
                            SET status='Rejected', reviewed_by_user_id=?, reviewed_by_name=?, reviewed_at=?, remarks=?
                            WHERE request_id=?");
    if (!$stmtUp) throw new Exception('db_prepare_failed');
    $stmtUp->bind_param('isssi', $adminUserId, $adminName, $now, $remarks, $requestId);
    $stmtUp->execute();
    $stmtUp->close();
    tmm_audit_event($db, 'PUV_LINK_REQUEST_REJECTED', 'VehicleLinkRequest', (string)$requestId, ['plate_number' => $plate]);
    $db->commit();
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($operatorId <= 0) throw new Exception('missing_operator_id');
  $stmtVeh = $db->prepare("UPDATE vehicles
                           SET operator_id=?, current_operator_id=?, record_status='Linked'
                           WHERE plate_number=?");
  if (!$stmtVeh) throw new Exception('db_prepare_failed');
  $stmtVeh->bind_param('iis', $operatorId, $operatorId, $plate);
  $stmtVeh->execute();
  $stmtVeh->close();

  $stmtUp = $db->prepare("UPDATE vehicle_link_requests
                          SET status='Approved', reviewed_by_user_id=?, reviewed_by_name=?, reviewed_at=?, remarks=?
                          WHERE request_id=?");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('isssi', $adminUserId, $adminName, $now, $remarks, $requestId);
  $stmtUp->execute();
  $stmtUp->close();

  tmm_audit_event($db, 'PUV_LINK_REQUEST_APPROVED', 'Vehicle', $plate, ['request_id' => $requestId, 'operator_id' => $operatorId]);
  $db->commit();
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  $db->rollback();
  error_response(500, 'db_error', ['message' => $e->getMessage()]);
}
