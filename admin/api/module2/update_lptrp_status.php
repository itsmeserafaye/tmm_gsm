<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

$id = (int)($_POST['application_id'] ?? 0);
$status = trim((string)($_POST['lptrp_status'] ?? ''));
if ($id <= 0 || $status === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }

$allowed = ['Draft','Submitted','Under Evaluation','For Correction','Approved','Active','Rejected','Suspended','Expired'];
if (!in_array($status, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'invalid_status']); exit; }

$stmt = $db->prepare("SELECT application_id, operator_id, route_id, vehicle_count, lptrp_status, status FROM franchise_applications WHERE application_id=? LIMIT 1");
if (!$stmt) { echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$app) { echo json_encode(['ok'=>false,'error'=>'application_not_found']); exit; }

$prev = (string)($app['lptrp_status'] ?? '');
$routeId = (int)($app['route_id'] ?? 0);
$vehCount = (int)($app['vehicle_count'] ?? 0);
$approvedUnits = isset($_POST['approved_units']) ? (int)$_POST['approved_units'] : 0;
if ($approvedUnits <= 0) $approvedUnits = $vehCount;
$approvedRouteDbId = isset($_POST['approved_route_id']) ? (int)$_POST['approved_route_id'] : 0;

$stmtU = $db->prepare("UPDATE franchise_applications SET lptrp_status=? WHERE application_id=?");
if (!$stmtU) { echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtU->bind_param('si', $status, $id);
$ok = $stmtU->execute();
$stmtU->close();
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'update_failed']); exit; }

$payload = ['updated'=>true];

if ($status === 'Approved') {
  $permNo = 'PUV-END-' . date('Ymd') . '-' . $id;
  $stmtE = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number) VALUES (?, CURDATE(), ?) ON DUPLICATE KEY UPDATE permit_number=VALUES(permit_number), issued_date=VALUES(issued_date)");
  if ($stmtE) {
    $stmtE->bind_param('is', $id, $permNo);
    $stmtE->execute();
    $stmtE->close();
  }
  $stmtA = $db->prepare("UPDATE franchise_applications SET approved_route_ids=?, approved_vehicle_count=? WHERE application_id=?");
  if ($stmtA) {
    $routeDbId = $approvedRouteDbId > 0 ? $approvedRouteDbId : $routeId;
    $rid = $routeDbId > 0 ? (string)$routeDbId : null;
    $stmtA->bind_param('sii', $rid, $approvedUnits, $id);
    $stmtA->execute();
    $stmtA->close();
  }
  $payload['approved'] = true;
}

echo json_encode(['ok'=>true,'data'=>$payload]);
