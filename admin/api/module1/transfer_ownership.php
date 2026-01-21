<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.vehicles.write');
$plate = trim($_POST['plate_number'] ?? '');
$newop = trim((string)($_POST['new_operator_name'] ?? ''));
$newOpId = isset($_POST['new_operator_id']) ? (int)$_POST['new_operator_id'] : 0;
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
$resolvedName = $newop;
if ($newOpId > 0) {
  $stmtO = $db->prepare("SELECT name, full_name FROM operators WHERE id=? LIMIT 1");
  if ($stmtO) {
    $stmtO->bind_param('i', $newOpId);
    $stmtO->execute();
    $rowO = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if (!$rowO) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'operator_not_found']); exit; }
    $resolvedName = trim((string)($rowO['name'] ?? ''));
    if ($resolvedName === '') $resolvedName = trim((string)($rowO['full_name'] ?? ''));
  }
}

$newOpIdBind = $newOpId > 0 ? $newOpId : null;
$stmt1 = $db->prepare("UPDATE vehicles SET operator_id=?, operator_name=?, record_status='Linked', status='Linked' WHERE plate_number=?");
$stmt1->bind_param('iss', $newOpIdBind, $resolvedName, $plate);
$stmt1->execute();
$stmt2 = $db->prepare("INSERT INTO ownership_transfers(plate_number, new_operator_name, deed_ref) VALUES(?,?,?)");
$stmt2->bind_param('sss', $plate, $resolvedName, $deed);
$stmt2->execute();
echo json_encode(['ok'=>true, 'plate_number'=>$plate, 'new_operator_name'=>$resolvedName, 'new_operator_id'=>$newOpId]);
?>
