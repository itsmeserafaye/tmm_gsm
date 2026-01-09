<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
$route = trim($_GET['route_id'] ?? '');
$sql = "SELECT ta.plate_number, ta.route_id, ta.terminal_name, ta.status, v.operator_name FROM terminal_assignments ta LEFT JOIN vehicles v ON v.plate_number=ta.plate_number";
if ($route !== '') { $sql .= " WHERE ta.route_id=?"; $stmt = $db->prepare($sql); $stmt->bind_param('s', $route); $stmt->execute(); $res = $stmt->get_result(); } else { $res = $db->query($sql); }
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
