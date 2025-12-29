<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$terminal = trim($_POST['terminal_name'] ?? '');
$status = trim($_POST['status'] ?? 'Authorized');
if ($plate === '' || $route === '' || $terminal === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$chk = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
if (!$exists) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$upd = $db->prepare("UPDATE vehicles SET route_id=? WHERE plate_number=?");
$upd->bind_param('ss', $route, $plate);
$upd->execute();
$ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, route_id, terminal_name, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
$ins->bind_param('ssss', $plate, $route, $terminal, $status);
$ok = $ins->execute();
header('Content-Type: application/json');
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'route_id'=>$route, 'terminal_name'=>$terminal, 'status'=>$status]);
?> 
