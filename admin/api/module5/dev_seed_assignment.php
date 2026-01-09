<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$route = trim($_POST['route_id'] ?? '');
$terminal = trim($_POST['terminal_name'] ?? '');
if ($plate === '' || $route === '' || $terminal === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$stmtT = $db->prepare("SELECT id FROM terminals WHERE name=?");
$stmtT->bind_param('s', $terminal);
$stmtT->execute();
$trow = $stmtT->get_result()->fetch_assoc();
if (!$trow) { echo json_encode(['ok'=>false,'error'=>'terminal_not_found']); exit; }
$stmtV = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$stmtV->bind_param('s', $plate);
$stmtV->execute();
$vrow = $stmtV->get_result()->fetch_assoc();
if (!$vrow) { echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$upd = $db->prepare("UPDATE vehicles SET route_id=? WHERE plate_number=?");
$upd->bind_param('ss', $route, $plate);
$upd->execute();
$ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, route_id, terminal_name, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
$st = 'Authorized';
$ins->bind_param('ssss', $plate, $route, $terminal, $st);
$ok = $ins->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'route_id'=>$route, 'terminal_name'=>$terminal, 'status'=>$st]);
