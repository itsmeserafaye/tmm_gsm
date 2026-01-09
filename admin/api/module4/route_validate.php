<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$route_id = trim($_GET['route_id'] ?? $_POST['route_id'] ?? '');
if ($route_id === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'route_required']); exit; }
$rs = $db->prepare("SELECT route_id, route_name, max_vehicle_limit, status FROM routes WHERE route_id=?");
$rs->bind_param('s', $route_id);
$rs->execute();
$route = $rs->get_result()->fetch_assoc();
if (!$route) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'route_not_found']); exit; }
$qs = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status='Authorized'");
$qs->bind_param('s', $route_id);
$qs->execute();
$count = ($qs->get_result()->fetch_assoc()['c'] ?? 0);
$limit = (int)($route['max_vehicle_limit'] ?? 0);
$within = $count <= $limit;
echo json_encode(['ok'=>true,'route_id'=>$route_id,'route_name'=>$route['route_name'],'assigned'=>$count,'max_limit'=>$limit,'within_limit'=>$within]);
?> 
