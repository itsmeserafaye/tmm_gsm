<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

$franchiseId = (int)($_GET['franchise_id'] ?? 0);
if ($franchiseId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_franchise_id']);
  exit;
}

$stmt = $db->prepare("SELECT fa.operator_id, fa.franchise_ref_number, fa.route_id
                      FROM franchises f
                      JOIN franchise_applications fa ON fa.application_id=f.application_id
                      WHERE f.franchise_id=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $franchiseId);
$stmt->execute();
$fa = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$fa) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'franchise_not_found']);
  exit;
}
$operatorId = (int)($fa['operator_id'] ?? 0);
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

$stmtV = $db->prepare("SELECT id, plate_number, vehicle_type
                       FROM vehicles
                       WHERE operator_id=? AND COALESCE(record_status,'') <> 'Archived'
                         AND COALESCE(plate_number,'') <> ''
                       ORDER BY plate_number ASC
                       LIMIT 2000");
if (!$stmtV) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtV->bind_param('i', $operatorId);
$stmtV->execute();
$res = $stmtV->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmtV->close();

echo json_encode(['ok' => true, 'data' => $rows]);

