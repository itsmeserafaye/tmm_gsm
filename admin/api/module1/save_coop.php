<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');
$name = trim($_POST['coop_name'] ?? '');
$addr = trim($_POST['address'] ?? '');
$chair = trim($_POST['chairperson_name'] ?? '');
$approval = trim($_POST['lgu_approval_number'] ?? '');
if ($name === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_coop_name']); exit; }
$stmt = $db->prepare("INSERT INTO coops(coop_name, address, chairperson_name, lgu_approval_number) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE address=VALUES(address), chairperson_name=VALUES(chairperson_name), lgu_approval_number=VALUES(lgu_approval_number)");
$stmt->bind_param('ssss', $name, $addr, $chair, $approval);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'coop_name'=>$name]);
?> 
