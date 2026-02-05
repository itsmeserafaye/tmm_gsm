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
$stmtV = $db->prepare("SELECT plate_number, COALESCE(operator_id,0) AS operator_id FROM vehicles WHERE plate_number=?");
$stmtV->bind_param('s', $plate);
$stmtV->execute();
$exists = $stmtV->get_result()->fetch_assoc();
if (!$exists) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']);
    exit;
}
$currentOperatorId = (int)($exists['operator_id'] ?? 0);

$resolvedName = $operatorName;
$resolvedId = $operatorId;
$resolvedEmail = '';
if ($resolvedId > 0) {
    $stmtO = $db->prepare("SELECT id, name, full_name, email FROM operators WHERE id=? LIMIT 1");
    if ($stmtO) {
        $stmtO->bind_param('i', $resolvedId);
        $stmtO->execute();
        $rowO = $stmtO->get_result()->fetch_assoc();
        $stmtO->close();
        if (!$rowO) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'operator_not_found']); exit; }
        $resolvedName = trim((string)($rowO['name'] ?? ''));
        if ($resolvedName === '') $resolvedName = trim((string)($rowO['full_name'] ?? ''));
        $resolvedEmail = strtolower(trim((string)($rowO['email'] ?? '')));
    }
} else {
    $stmtO = $db->prepare("SELECT id, name, full_name, email FROM operators WHERE full_name=? LIMIT 1");
    if ($stmtO) {
        $stmtO->bind_param('s', $resolvedName);
        $stmtO->execute();
        $rowO = $stmtO->get_result()->fetch_assoc();
        $stmtO->close();
        if ($rowO && isset($rowO['id'])) $resolvedId = (int)$rowO['id'];
        $nm = trim((string)($rowO['name'] ?? ''));
        if ($nm !== '') $resolvedName = $nm;
        $resolvedEmail = strtolower(trim((string)($rowO['email'] ?? '')));
    }
}

$resolvedIdBind = $resolvedId > 0 ? $resolvedId : null;
$targetOperatorId = $resolvedId > 0 ? $resolvedId : 0;
if ($currentOperatorId > 0 && $targetOperatorId > 0 && $currentOperatorId !== $targetOperatorId) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'already_linked', 'current_operator_id'=>$currentOperatorId]);
    exit;
}
if ($currentOperatorId > 0 && $targetOperatorId === 0) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'already_linked', 'current_operator_id'=>$currentOperatorId]);
    exit;
}
$stmt = $db->prepare("UPDATE vehicles
                       SET operator_id=?,
                           current_operator_id=?,
                           operator_name=?,
                           record_status='Linked',
                           status=CASE
                             WHEN status IS NULL OR status='' OR status IN ('Declared/linked','Pending Inspection') THEN 'Pending Inspection'
                             ELSE status END
                       WHERE plate_number=?");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$resolvedIdBind2 = $resolvedId > 0 ? $resolvedId : null;
$stmt->bind_param('iiss', $resolvedIdBind, $resolvedIdBind2, $resolvedName, $plate);
$ok = $stmt->execute();

if ($ok) {
    $portalUserId = 0;
    if ($resolvedEmail !== '') {
        $stmtP = $db->prepare("SELECT id FROM operator_portal_users WHERE email=? LIMIT 1");
        if ($stmtP) {
            $stmtP->bind_param('s', $resolvedEmail);
            $stmtP->execute();
            $rowP = $stmtP->get_result()->fetch_assoc();
            $stmtP->close();
            $portalUserId = (int)($rowP['id'] ?? 0);
        }
    }
    if ($portalUserId <= 0 && $resolvedName !== '') {
        $stmtP = $db->prepare("SELECT id FROM operator_portal_users WHERE full_name=? OR association_name=? LIMIT 1");
        if ($stmtP) {
            $stmtP->bind_param('ss', $resolvedName, $resolvedName);
            $stmtP->execute();
            $rowP = $stmtP->get_result()->fetch_assoc();
            $stmtP->close();
            $portalUserId = (int)($rowP['id'] ?? 0);
        }
    }
    if ($portalUserId > 0) {
        $stmtIns = $db->prepare("INSERT INTO operator_portal_user_plates (user_id, plate_number) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)");
        if ($stmtIns) {
            $stmtIns->bind_param('is', $portalUserId, $plate);
            $stmtIns->execute();
            $stmtIns->close();
        }
    }
}

echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'operator_id'=>$resolvedId, 'operator_name'=>$resolvedName, 'vehicle_status'=>'Pending Inspection']);
?> 
