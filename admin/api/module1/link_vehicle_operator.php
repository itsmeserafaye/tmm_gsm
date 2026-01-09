<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');

$plate = trim($_POST['plate_number'] ?? '');
$operator = trim($_POST['operator_name'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');
if ($plate === '' || $operator === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$stmt = $db->prepare("UPDATE vehicles SET operator_name=?, coop_name=? WHERE plate_number=?");
$stmt->bind_param('sss', $operator, $coop, $plate);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate]);
?> 
