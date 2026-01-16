<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
$fr = trim($_POST['franchise_ref_number'] ?? '');
$type = trim($_POST['violation_type'] ?? '');
if ($fr === '' || $type === '') { echo json_encode(['error'=>'missing_fields']); exit; }
$stmt = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, violation_type) VALUES (?, ?)");
$stmt->bind_param('ss', $fr, $type);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'case_id'=>$db->insert_id]);
?> 
