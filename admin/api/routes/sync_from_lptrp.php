<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';

$db = db();
header('Content-Type: application/json');
require_permission('module1.routes.write');

$routeId = trim((string)($_POST['route_id'] ?? ($_GET['route_id'] ?? '')));
$routeId = $routeId !== '' ? strtoupper($routeId) : '';

if ($routeId !== '') {
  $lptrp = tmm_get_lptrp_route($db, $routeId);
  if (!$lptrp) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'lptrp_route_not_found']); exit; }
  if (!tmm_lptrp_is_approved($lptrp)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'route_not_lptrp_approved']); exit; }
}

$ok = tmm_sync_routes_from_lptrp($db, $routeId !== '' ? $routeId : null);
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'sync_failed']);
  exit;
}
echo json_encode(['ok'=>true,'route_id'=>$routeId !== '' ? $routeId : null]);

