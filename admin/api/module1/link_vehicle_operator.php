<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module1.vehicles.write');
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$operatorId = isset($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;
$operatorName = trim((string)($_POST['operator_name'] ?? ($_POST['full_name'] ?? '')));
if ($plate === '' || ($operatorId <= 0 && $operatorName === '')) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
if (!preg_match('/^[A-Z0-9-]{4,16}$/', $plate)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_plate']); exit; }

// Ensure vehicle exists
$stmtV = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$stmtV->bind_param('s', $plate);
$stmtV->execute();
$exists = $stmtV->get_result()->fetch_assoc();
if (!$exists) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']);
    exit;
}

$resolvedName = $operatorName;
$resolvedId = $operatorId;
if ($resolvedId > 0) {
    $stmtO = $db->prepare("SELECT id, name, full_name FROM operators WHERE id=? LIMIT 1");
    if ($stmtO) {
        $stmtO->bind_param('i', $resolvedId);
        $stmtO->execute();
        $rowO = $stmtO->get_result()->fetch_assoc();
        $stmtO->close();
        if (!$rowO) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'operator_not_found']); exit; }
        $resolvedName = trim((string)($rowO['name'] ?? ''));
        if ($resolvedName === '') $resolvedName = trim((string)($rowO['full_name'] ?? ''));
    }
} else {
    $stmtO = $db->prepare("SELECT id, name, full_name FROM operators WHERE full_name=? LIMIT 1");
    if ($stmtO) {
        $stmtO->bind_param('s', $resolvedName);
        $stmtO->execute();
        $rowO = $stmtO->get_result()->fetch_assoc();
        $stmtO->close();
        if ($rowO && isset($rowO['id'])) $resolvedId = (int)$rowO['id'];
        $nm = trim((string)($rowO['name'] ?? ''));
        if ($nm !== '') $resolvedName = $nm;
    }
}

$resolvedIdBind = $resolvedId > 0 ? $resolvedId : null;
$stmt = $db->prepare("UPDATE vehicles SET operator_id=?, operator_name=?, status='Linked' WHERE plate_number=?");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('iss', $resolvedIdBind, $resolvedName, $plate);
$ok = $stmt->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'operator_id'=>$resolvedId, 'operator_name'=>$resolvedName, 'vehicle_status'=>'Linked']);
?> 
