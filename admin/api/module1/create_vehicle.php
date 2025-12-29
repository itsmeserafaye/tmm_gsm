<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
if ($plate === '' || !preg_match('/^[A-Z0-9-]{6,10}$/', $plate)) {
    echo json_encode(['error' => 'Invalid or missing plate number']);
    exit;
}
$type = trim($_POST['vehicle_type'] ?? '');
$operator = trim($_POST['operator_name'] ?? '');
$franchise = trim($_POST['franchise_id'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$status = trim($_POST['status'] ?? 'Active');
if ($plate === '' || $type === '' || $operator === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
$stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_name, franchise_id, route_id, status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), operator_name=VALUES(operator_name), franchise_id=VALUES(franchise_id), route_id=VALUES(route_id), status=VALUES(status)");
$stmt->bind_param('ssssss', $plate, $type, $operator, $franchise, $route, $status);
$ok = $stmt->execute();
header('Content-Type: application/json');
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate]);
?> 
