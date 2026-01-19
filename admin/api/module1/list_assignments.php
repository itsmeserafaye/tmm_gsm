<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.view','module1.routes.write','parking.manage','module5.view']);
$route = trim($_GET['route_id'] ?? '');
$sql = "SELECT ta.plate_number, ta.route_id, ta.terminal_name, ta.status, v.operator_name FROM terminal_assignments ta LEFT JOIN vehicles v ON v.plate_number=ta.plate_number";
if ($route !== '') { $sql .= " WHERE ta.route_id=?"; $stmt = $db->prepare($sql); $stmt->bind_param('s', $route); $stmt->execute(); $res = $stmt->get_result(); } else { $res = $db->query($sql); }
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
