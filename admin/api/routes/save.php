<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$route_id = trim($_POST['route_id'] ?? '');
$route_name = trim($_POST['route_name'] ?? '');
$limit = (int)($_POST['max_vehicle_limit'] ?? 0);
$status = trim($_POST['status'] ?? 'Active');
if ($route_id === '' || $route_name === '' || $limit <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$stmt = $db->prepare("INSERT INTO routes(route_id, route_name, max_vehicle_limit, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_name=VALUES(route_name), max_vehicle_limit=VALUES(max_vehicle_limit), status=VALUES(status)");
$stmt->bind_param('ssis', $route_id, $route_name, $limit, $status);
$ok = $stmt->execute();
header('Content-Type: application/json');
if (!$ok) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'save_failed']);
  exit;
}
echo json_encode(['ok'=>true, 'route_id'=>$route_id]);
?> 
