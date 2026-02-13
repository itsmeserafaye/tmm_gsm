<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vehicle_types.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
$route = trim($_GET['route_id'] ?? '');
$vehicleType = trim($_GET['vehicle_type'] ?? '');
$requested = (int)($_GET['requested'] ?? 0);
if ($route === '' || $vehicleType === '' || $requested <= 0) { echo json_encode(['error'=>'missing_params']); exit; }

function tmm_normalize_vehicle_category($v) {
  $s = trim((string)$v);
  if ($s === '') return '';
  if (in_array($s, ['Tricycle','Jeepney','UV','Bus'], true)) return $s;
  $l = strtolower($s);
  if (str_contains($l, 'tricycle') || str_contains($l, 'e-trike') || str_contains($l, 'pedicab')) return 'Tricycle';
  if (str_contains($l, 'jeepney')) return 'Jeepney';
  if (str_contains($l, 'bus') || str_contains($l, 'mini-bus')) return 'Bus';
  if (str_contains($l, 'uv') || str_contains($l, 'van') || str_contains($l, 'shuttle')) return 'UV';
  return '';
}

$allowedVehicleTypes = vehicle_types();
if (!in_array('UV', $allowedVehicleTypes, true)) $allowedVehicleTypes[] = 'UV';
if (!in_array($vehicleType, $allowedVehicleTypes, true) && tmm_normalize_vehicle_category($vehicleType) === '') {
  echo json_encode(['error'=>'invalid_vehicle_type']);
  exit;
}
$stmt = $db->prepare("SELECT id, route_id FROM routes WHERE route_id=? OR route_code=? LIMIT 1");
$stmt->bind_param('ss', $route, $route);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { echo json_encode(['error'=>'route_not_found']); exit; }
$routeDbId = (int)($r['id'] ?? 0);
if ($routeDbId <= 0) { echo json_encode(['error'=>'route_not_found']); exit; }

$allocType = $vehicleType;
$limit = null;

$stmtA = $db->prepare("SELECT vehicle_type, COALESCE(authorized_units,0) AS authorized_units FROM route_vehicle_types WHERE route_id=? AND vehicle_type=? AND status='Active' LIMIT 1");
$stmtA->bind_param('is', $routeDbId, $allocType);
$stmtA->execute();
$a = $stmtA->get_result()->fetch_assoc();
$stmtA->close();
if ($a) {
  $allocType = (string)($a['vehicle_type'] ?? $allocType);
  $limit = (int)($a['authorized_units'] ?? 0);
}
if ($limit === null) {
  $cat = tmm_normalize_vehicle_category($vehicleType);
  if ($cat === '') { echo json_encode(['error'=>'allocation_not_found']); exit; }
  $stmtAll = $db->prepare("SELECT vehicle_type, COALESCE(authorized_units,0) AS authorized_units FROM route_vehicle_types WHERE route_id=? AND status='Active' AND vehicle_type<>'Tricycle' ORDER BY id ASC");
  $stmtAll->bind_param('i', $routeDbId);
  $stmtAll->execute();
  $resAll = $stmtAll->get_result();
  $candidates = [];
  while ($rr = $resAll->fetch_assoc()) {
    $vt = (string)($rr['vehicle_type'] ?? '');
    if ($vt === '') continue;
    if (tmm_normalize_vehicle_category($vt) !== $cat) continue;
    $candidates[] = $rr;
  }
  $stmtAll->close();
  if (!$candidates) { echo json_encode(['error'=>'allocation_not_found']); exit; }
  $picked = $candidates[0];
  foreach ($candidates as $cand) {
    if ((string)($cand['vehicle_type'] ?? '') === $cat) { $picked = $cand; break; }
  }
  $allocType = (string)($picked['vehicle_type'] ?? $allocType);
  $limit = (int)($picked['authorized_units'] ?? 0);
}

$stmtU = $db->prepare("SELECT COALESCE(SUM(vehicle_count),0) AS used_units FROM franchise_applications WHERE route_id=? AND vehicle_type=? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')");
$stmtU->bind_param('is', $routeDbId, $allocType);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
$stmtU->close();
$used = (int)($u['used_units'] ?? 0);
$remaining = max(0, $limit - $used);
$allowed = $requested <= $remaining;

echo json_encode(['ok'=>true, 'route_id'=>$route, 'route_db_id'=>$routeDbId, 'vehicle_type'=>$allocType, 'requested'=>$requested, 'limit'=>$limit, 'used'=>$used, 'remaining'=>$remaining, 'allowed'=>$allowed]);
?> 
