<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$route = trim($_GET['route_id'] ?? '');
$requested = (int)($_GET['requested'] ?? 0);
if ($route === '' || $requested <= 0) { echo json_encode(['error'=>'missing_params']); exit; }
$stmt = $db->prepare("SELECT max_vehicle_limit FROM routes WHERE route_id=?");
$stmt->bind_param('s', $route);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if (!$res) { echo json_encode(['error'=>'route_not_found']); exit; }
$limit = (int)$res['max_vehicle_limit'];
$allowed = $requested <= $limit;
echo json_encode(['ok'=>true, 'route_id'=>$route, 'requested'=>$requested, 'limit'=>$limit, 'allowed'=>$allowed]);
?> 
