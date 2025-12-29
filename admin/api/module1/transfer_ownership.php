<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
$newop = trim($_POST['new_operator_name'] ?? '');
$deed = trim($_POST['deed_ref'] ?? '');
if ($plate === '' || $newop === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$stmt1 = $db->prepare("UPDATE vehicles SET operator_name=? WHERE plate_number=?");
$stmt1->bind_param('ss', $newop, $plate);
$stmt1->execute();
$stmt2 = $db->prepare("INSERT INTO ownership_transfers(plate_number, new_operator_name, deed_ref) VALUES(?,?,?)");
$stmt2->bind_param('sss', $plate, $newop, $deed);
$stmt2->execute();
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'plate_number'=>$plate, 'new_operator_name'=>$newop]);
?> 
