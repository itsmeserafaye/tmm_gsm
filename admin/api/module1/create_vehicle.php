<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');

$plate = trim($_POST['plate_number'] ?? '');
if ($plate === '' || !preg_match('/^[A-Z0-9-]{6,10}$/', $plate)) {
    echo json_encode(['ok'=>false, 'error' => 'invalid_plate']);
    exit;
}
$type = trim($_POST['vehicle_type'] ?? '');
$operator = trim($_POST['operator_name'] ?? '');
$franchise = trim($_POST['franchise_id'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$status = trim($_POST['status'] ?? 'Pending Validation');

if ($plate === '' || $type === '' || $operator === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

$chk = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
if ($exists) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'duplicate_plate','plate_number'=>$plate]);
  exit;
}

$stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_name, franchise_id, route_id, status) VALUES(?,?,?,?,?,?)");
$stmt->bind_param('ssssss', $plate, $type, $operator, $franchise, $route, $status);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate]);
?> 
