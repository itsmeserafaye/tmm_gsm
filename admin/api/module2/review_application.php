<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
require_once __DIR__ . '/../../includes/util.php';
 
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}
 
$appId = (int)($_POST['application_id'] ?? 0);
$decisionRaw = trim((string)($_POST['decision'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$notes = substr($notes, 0, 1000);
 
if ($appId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}
 
$allowed = ['Approved', 'Rejected', 'Returned for Correction'];
$decision = 'Returned for Correction';
foreach ($allowed as $opt) {
  if (strcasecmp($decisionRaw, $opt) === 0) { $decision = $opt; break; }
}
 
if ($decision === 'Returned for Correction' && $notes === '') {
  echo json_encode(['ok' => false, 'error' => 'notes_required']);
  exit;
}
 
$db->begin_transaction();
try {
  $stmtA = $db->prepare("SELECT application_id, operator_id, service_area_id, vehicle_type, vehicle_count, status
                         FROM franchise_applications
                         WHERE application_id=? FOR UPDATE");
  if (!$stmtA) throw new Exception('db_prepare_failed');
  $stmtA->bind_param('i', $appId);
  $stmtA->execute();
  $app = $stmtA->get_result()->fetch_assoc();
  $stmtA->close();
 
  if (!$app) { $db->rollback(); echo json_encode(['ok' => false, 'error' => 'application_not_found']); exit; }
 
  $curStatus = (string)($app['status'] ?? '');
  if (!in_array($curStatus, ['Pending Review', 'Returned for Correction'], true)) {
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
 
  $opId = (int)($app['operator_id'] ?? 0);
  $areaId = (int)($app['service_area_id'] ?? 0);
  $want = (int)($app['vehicle_count'] ?? 0);
  if ($want <= 0) $want = 1;
 
  if ($decision === 'Approved') {
    $gate = tmm_can_review_tricycle_application($db, $opId, $areaId, $want, $appId);
    if (!($gate['ok'] ?? false)) {
      $db->rollback();
      echo json_encode($gate);
      exit;
    }
  }
 
  $reviewedByUserId = (int)($_SESSION['user_id'] ?? 0);
  $reviewedByName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($reviewedByName === '') $reviewedByName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($reviewedByName === '') $reviewedByName = 'Staff';
 
  $approvedCount = $decision === 'Approved' ? $want : null;
  $stmtU = $db->prepare("UPDATE franchise_applications
                         SET status=?,
                             approved_vehicle_count=COALESCE(approved_vehicle_count, ?),
                             reviewed_at=NOW(),
                             reviewed_by_user_id=?,
                             reviewed_by_name=?,
                             review_decision=?,
                             review_notes=?,
                             remarks=CASE WHEN ?<>'' THEN ? ELSE remarks END
                         WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('siissssssi', $decision, $approvedCount, $reviewedByUserId, $reviewedByName, $decision, $notes, $notes, $notes, $appId);
  $stmtU->execute();
  $stmtU->close();
 
  $db->commit();
  tmm_audit_event($db, 'FRANCHISE_APPLICATION_REVIEWED', 'FranchiseApplication', (string)$appId, ['decision' => $decision]);
  echo json_encode(['ok' => true, 'status' => $decision]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
