<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$operator = trim($_POST['operator_name'] ?? '');
$coop = trim($_POST['coop_name'] ?? '');
if ($plate === '' || $operator === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
if (!preg_match('/^[A-Z]{3}-?[0-9]{3,4}$/', $plate)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_plate_format']); exit; }
if (strlen($operator) < 3 || !preg_match("/^[A-Za-z\s'.-]+$/", $operator)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_operator_name']); exit; }

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

// If coop provided, enforce LGU approval rule
$coopId = null;
if ($coop !== '') {
    $stmtC = $db->prepare("SELECT id, lgu_approval_number FROM coops WHERE coop_name=?");
    $stmtC->bind_param('s', $coop);
    $stmtC->execute();
    $rowC = $stmtC->get_result()->fetch_assoc();
    if (!$rowC) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'coop_not_found']);
        exit;
    }
    if (trim((string)$rowC['lgu_approval_number']) === '') {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'coop_without_lgu_approval']);
        exit;
    }
    $coopId = (int)$rowC['id'];
}

// If operator already exists, check coop consistency when both provided
if ($coopId !== null) {
    $stmtO = $db->prepare("SELECT coop_id FROM operators WHERE full_name=? LIMIT 1");
    $stmtO->bind_param('s', $operator);
    $stmtO->execute();
    $rowO = $stmtO->get_result()->fetch_assoc();
    if ($rowO && (int)$rowO['coop_id'] !== 0 && (int)$rowO['coop_id'] !== $coopId) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'operator_not_member_of_coop']);
        exit;
    }
}

$stmt = $db->prepare("UPDATE vehicles SET operator_name=?, coop_name=? WHERE plate_number=?");
$stmt->bind_param('sss', $operator, $coop, $plate);
$ok = $stmt->execute();
header('Content-Type: application/json');
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate]);
?> 
