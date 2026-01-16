<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$res = $db->query("SELECT route_id, route_name, max_vehicle_limit, status FROM routes ORDER BY route_id");
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
