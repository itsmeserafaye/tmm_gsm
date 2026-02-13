<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
$route = trim($_GET['route_id'] ?? '');
$vehicleType = trim($_GET['vehicle_type'] ?? '');
$requested = (int)($_GET['requested'] ?? 0);
if ($route === '' || $vehicleType === '' || $requested <= 0) { echo json_encode(['error'=>'missing_params']); exit; }
$stmt = $db->prepare("SELECT id, route_id FROM routes WHERE route_id=? OR route_code=? LIMIT 1");
$stmt->bind_param('ss', $route, $route);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { echo json_encode(['error'=>'route_not_found']); exit; }
$routeDbId = (int)($r['id'] ?? 0);
if ($routeDbId <= 0) { echo json_encode(['error'=>'route_not_found']); exit; }

$stmtA = $db->prepare("SELECT COALESCE(authorized_units,0) AS authorized_units FROM route_vehicle_types WHERE route_id=? AND vehicle_type=? AND status='Active' LIMIT 1");
$stmtA->bind_param('is', $routeDbId, $vehicleType);
$stmtA->execute();
$a = $stmtA->get_result()->fetch_assoc();
$stmtA->close();
if (!$a) { echo json_encode(['error'=>'allocation_not_found']); exit; }
$limit = (int)($a['authorized_units'] ?? 0);

$stmtU = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS used_units FROM franchise_applications WHERE route_id=? AND vehicle_type=? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')");
$stmtU->bind_param('is', $routeDbId, $vehicleType);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
$stmtU->close();
$used = (int)($u['used_units'] ?? 0);
$remaining = max(0, $limit - $used);
$allowed = $requested <= $remaining;

echo json_encode(['ok'=>true, 'route_id'=>$route, 'route_db_id'=>$routeDbId, 'vehicle_type'=>$vehicleType, 'requested'=>$requested, 'limit'=>$limit, 'used'=>$used, 'remaining'=>$remaining, 'allowed'=>$allowed]);
?> 
