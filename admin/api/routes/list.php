<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/lptrp.php';
$db = db();
$hasLptrp = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lptrp_routes' LIMIT 1");
if ($hasLptrp && $hasLptrp->fetch_row()) {
  $res = $db->query("SELECT r.route_id, r.route_name, r.max_vehicle_limit, r.status FROM routes r JOIN lptrp_routes lr ON lr.route_code = r.route_id ORDER BY r.route_id");
} else {
  $res = $db->query("SELECT route_id, route_name, max_vehicle_limit, status FROM routes ORDER BY route_id");
}
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
