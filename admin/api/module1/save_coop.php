<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
$db = db();
require_permission('module1.coops.write');
$name = trim($_POST['coop_name'] ?? '');
$isTestEnv = defined('TMM_TEST') && TMM_TEST;
$isTestName = preg_match('/^TEST[_-]/i', $name) === 1;
if (!$isTestEnv && $isTestName) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Cooperative name cannot start with TEST in production']); exit; }
$addr = trim($_POST['address'] ?? '');
$chair = trim($_POST['chairperson_name'] ?? '');
$approval = trim($_POST['lgu_approval_number'] ?? '');
$consolidation = trim($_POST['consolidation_status'] ?? '');
if ($consolidation === '') { $consolidation = 'Not Consolidated'; }
if (!in_array($consolidation, ['Consolidated','In Progress','Not Consolidated'], true)) { $consolidation = 'Not Consolidated'; }
if ($name === '' || strlen($name) < 3) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Cooperative name is too short']); exit; }
$isStatusOnly = ($addr === 'KEEP_EXISTING' && $chair === 'KEEP_EXISTING' && $approval === 'KEEP_EXISTING');
if ($isStatusOnly) {
    $stmtGet = $db->prepare("SELECT address, chairperson_name, lgu_approval_number FROM coops WHERE coop_name=? LIMIT 1");
    $stmtGet->bind_param('s', $name);
    $stmtGet->execute();
    $existingRow = $stmtGet->get_result()->fetch_assoc();
    if (!$existingRow) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Cooperative not found']); exit; }
    $addr = trim((string)($existingRow['address'] ?? ''));
    $chair = trim((string)($existingRow['chairperson_name'] ?? ''));
    $approval = trim((string)($existingRow['lgu_approval_number'] ?? ''));
} else {
    if ($addr === '' || strlen($addr) < 5) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Address should look like a real street address']); exit; }
    if ($chair === '' || strlen($chair) < 3 || !preg_match("/^[A-Za-z\s'.-]+$/", $chair)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Chairperson name should be a realistic human name']); exit; }
    if ($approval === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'LGU approval number is required for transport cooperatives']); exit; }
    if (!preg_match('/^[A-Za-z0-9\-\/]{4,}$/', $approval)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'LGU approval number should be alphanumeric (with - or /)']); exit; }
    $stmtCheck = $db->prepare("SELECT coop_name FROM coops WHERE lgu_approval_number = ? AND coop_name <> ? LIMIT 1");
    $stmtCheck->bind_param('ss', $approval, $name);
    $stmtCheck->execute();
    $existing = $stmtCheck->get_result()->fetch_assoc();
    if ($existing) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'LGU approval number is already used by another cooperative']); exit; }
}
$stmt = $db->prepare("INSERT INTO coops(coop_name, address, chairperson_name, lgu_approval_number, consolidation_status) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE address=VALUES(address), chairperson_name=VALUES(chairperson_name), lgu_approval_number=VALUES(lgu_approval_number), consolidation_status=VALUES(consolidation_status)");
$stmt->bind_param('sssss', $name, $addr, $chair, $approval, $consolidation);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'coop_name'=>$name]);
?> 
