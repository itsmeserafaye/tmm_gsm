<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.endorse','module2.approve']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$franchiseId = (int)($_POST['franchise_id'] ?? 0);
$vehicleId = (int)($_POST['vehicle_id'] ?? 0);
if ($franchiseId <= 0 || $vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmt = $db->prepare("SELECT fa.franchise_ref_number, fa.route_id
                      FROM franchises f
                      JOIN franchise_applications fa ON fa.application_id=f.application_id
                      WHERE f.franchise_id=? LIMIT 1");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('i', $franchiseId);
$stmt->execute();
$fa = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$fa) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'franchise_not_found']); exit; }
$frRef = trim((string)($fa['franchise_ref_number'] ?? ''));
$routeId = (int)($fa['route_id'] ?? 0);

$stmtV = $db->prepare("SELECT id FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }

$db->begin_transaction();
try {
  $stmtOff = $db->prepare("UPDATE franchise_vehicles SET status='Inactive' WHERE vehicle_id=? AND status='Active'");
  if ($stmtOff) {
    $stmtOff->bind_param('i', $vehicleId);
    $stmtOff->execute();
    $stmtOff->close();
  }

  $stmtIns = $db->prepare("INSERT INTO franchise_vehicles (franchise_id, franchise_ref_number, route_id, vehicle_id, status) VALUES (?, ?, ?, ?, 'Active')");
  if (!$stmtIns) throw new Exception('db_prepare_failed');
  $stmtIns->bind_param('isii', $franchiseId, $frRef, $routeId, $vehicleId);
  if (!$stmtIns->execute()) throw new Exception('insert_failed');
  $fvId = (int)$stmtIns->insert_id;
  $stmtIns->close();

  $db->commit();
  echo json_encode(['ok' => true, 'fv_id' => $fvId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}

