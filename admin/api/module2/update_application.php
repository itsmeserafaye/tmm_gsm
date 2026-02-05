<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/franchise_gate.php';
header('Content-Type: application/json');

$db = db();
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$appId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$routeId = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
$vehicleCount = isset($_POST['vehicle_count']) ? (int)$_POST['vehicle_count'] : 0;
$rep = trim((string)($_POST['representative_name'] ?? ''));
$rep = substr($rep, 0, 120);

if ($appId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}
if ($routeId <= 0 || $vehicleCount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
  exit;
}

$db->begin_transaction();
try {
  $stmt = $db->prepare("SELECT application_id, status FROM franchise_applications WHERE application_id=? FOR UPDATE");
  if (!$stmt) throw new Exception('db_prepare_failed');
  $stmt->bind_param('i', $appId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) {
    $db->rollback();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'application_not_found']);
    exit;
  }
  $st = (string)($row['status'] ?? '');
  if (in_array($st, ['LTFRB-Approved','Approved'], true)) {
    $db->rollback();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'locked_status']);
    exit;
  }

  $cap = tmm_route_capacity_check($db, $routeId, $vehicleCount, $appId);
  if (!$cap['ok']) {
    $db->rollback();
    echo json_encode($cap);
    exit;
  }

  $stmtU = $db->prepare("UPDATE franchise_applications
                         SET route_id=?, route_ids=?, vehicle_count=?, representative_name=?
                         WHERE application_id=?");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $routeIds = (string)$routeId;
  $stmtU->bind_param('isisi', $routeId, $routeIds, $vehicleCount, $rep, $appId);
  if (!$stmtU->execute()) throw new Exception('update_failed');
  $stmtU->close();

  $db->commit();
  echo json_encode(['ok' => true, 'application_id' => $appId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}

