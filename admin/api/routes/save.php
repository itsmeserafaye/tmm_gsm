<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.routes.write');
$route_id = trim($_POST['route_id'] ?? '');
$route_name = trim($_POST['route_name'] ?? '');
$limit = (int)($_POST['max_vehicle_limit'] ?? 0);
$status = trim($_POST['status'] ?? 'Active');
$route_id = strtoupper(trim($route_id));
if ($route_id === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }

$lptrp = tmm_get_lptrp_route($db, $route_id);
if (!$lptrp || !tmm_lptrp_is_approved($lptrp)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'route_not_lptrp_approved']);
  exit;
}

if (!tmm_sync_routes_from_lptrp($db, $route_id)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'sync_failed']);
  exit;
}

echo json_encode(['ok'=>true, 'route_id'=>$route_id, 'synced'=>true]);
?> 
