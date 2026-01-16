<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
$status = trim($_POST['status'] ?? '');
$type = trim($_POST['vehicle_type'] ?? '');
if ($plate === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_plate']); exit; }
$sets = [];
$params = [];
$types = '';
if ($status !== '' && $status !== 'Status') { $sets[] = "status=?"; $params[] = $status; $types .= 's'; }
if ($type !== '' && $type !== 'Select vehicle type') { $sets[] = "vehicle_type=?"; $params[] = $type; $types .= 's'; }
if (!$sets) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'nothing_to_update']); exit; }
$sql = "UPDATE vehicles SET ".implode(",", $sets)." WHERE plate_number=?";
$params[] = $plate;
$types .= 's';
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
header('Content-Type: application/json');
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate]);
?> 
