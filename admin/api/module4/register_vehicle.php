<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
$orcrNo = trim((string)($_POST['orcr_no'] ?? ''));
$orcrDate = trim((string)($_POST['orcr_date'] ?? ''));

if ($vehicleId <= 0 || $orcrNo === '' || $orcrDate === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_required_fields']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $orcrDate)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_orcr_date']);
  exit;
}

$stmtV = $db->prepare("SELECT id, plate_number, operator_id FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) {
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
  exit;
}
if ((int)($veh['operator_id'] ?? 0) <= 0) {
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
  exit;
}

$db->begin_transaction();
try {
  $stmtUp = $db->prepare("INSERT INTO vehicle_registrations (vehicle_id, orcr_no, orcr_date, registration_status, created_at)
                          VALUES (?, ?, ?, 'Registered', NOW())
                          ON DUPLICATE KEY UPDATE orcr_no=VALUES(orcr_no), orcr_date=VALUES(orcr_date), registration_status='Registered'");
  if (!$stmtUp) throw new Exception('db_prepare_failed');
  $stmtUp->bind_param('iss', $vehicleId, $orcrNo, $orcrDate);
  if (!$stmtUp->execute()) throw new Exception('insert_failed');
  $stmtUp->close();

  $db->commit();
  echo json_encode(['ok' => true, 'message' => 'Vehicle registered', 'vehicle_id' => $vehicleId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
