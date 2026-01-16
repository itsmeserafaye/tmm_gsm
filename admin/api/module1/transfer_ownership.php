<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.vehicles.write');
$plate = trim($_POST['plate_number'] ?? '');
$newop = trim($_POST['new_operator_name'] ?? '');
$deed = trim($_POST['deed_ref'] ?? '');
if ($plate === '' || $newop === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
if ($deed === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_deed_ref']); exit; }

// Ensure plate exists and has at least one deed document uploaded
$chk = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
if (!$exists) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }

$chkD = $db->prepare("SELECT id FROM documents WHERE plate_number=? AND type='deed' LIMIT 1");
$chkD->bind_param('s', $plate);
$chkD->execute();
$doc = $chkD->get_result()->fetch_assoc();
if (!$doc) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_deed_document']); exit; }
$stmt1 = $db->prepare("UPDATE vehicles SET operator_name=? WHERE plate_number=?");
$stmt1->bind_param('ss', $newop, $plate);
$stmt1->execute();
$stmt2 = $db->prepare("INSERT INTO ownership_transfers(plate_number, new_operator_name, deed_ref) VALUES(?,?,?)");
$stmt2->bind_param('sss', $plate, $newop, $deed);
$stmt2->execute();
echo json_encode(['ok'=>true, 'plate_number'=>$plate, 'new_operator_name'=>$newop]);
?>
