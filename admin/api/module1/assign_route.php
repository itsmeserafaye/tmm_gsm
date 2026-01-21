<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

$hasOperatorIdCol = false;
$resCol = $db->query("SHOW COLUMNS FROM terminal_assignments LIKE 'operator_id'");
if ($resCol && $resCol->num_rows > 0) {
    $hasOperatorIdCol = true;
}

$plate = trim($_POST['plate_number'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$terminal = trim($_POST['terminal_name'] ?? '');
$status = trim($_POST['status'] ?? 'Authorized');

if ($plate === '' || $route === '' || $terminal === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

$chk = $db->prepare("SELECT id, plate_number, inspection_status, status, operator_id, operator_name, vehicle_type FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$veh = $chk->get_result()->fetch_assoc();

if (!$veh) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
    exit;
}

$inspection = strtolower(trim((string)($veh['inspection_status'] ?? '')));
if ($inspection !== 'passed') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'inspection_not_passed']);
    exit;
}

$vehicleId = (int)($veh['id'] ?? 0);
$hasOrcr = false;
if ($vehicleId > 0) {
    $stmtDoc = $db->prepare("SELECT doc_id FROM vehicle_documents WHERE vehicle_id=? AND doc_type='ORCR' AND COALESCE(is_verified,0)=1 LIMIT 1");
    if ($stmtDoc) {
        $stmtDoc->bind_param('i', $vehicleId);
        $stmtDoc->execute();
        $d = $stmtDoc->get_result()->fetch_assoc();
        $stmtDoc->close();
        $hasOrcr = $d ? true : false;
    }
}
if (!$hasOrcr) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'orcr_required']);
    exit;
}

$operatorId = (int)($veh['operator_id'] ?? 0);
if ($operatorId <= 0) {
    $opName = trim((string)($veh['operator_name'] ?? ''));
    if ($opName !== '') {
        $stmtOp = $db->prepare("SELECT id FROM operators WHERE full_name=? OR name=? LIMIT 1");
        if ($stmtOp) {
            $stmtOp->bind_param('ss', $opName, $opName);
            $stmtOp->execute();
            $opRow = $stmtOp->get_result()->fetch_assoc();
            $stmtOp->close();
            if ($opRow && isset($opRow['id'])) $operatorId = (int)$opRow['id'];
        }
    }
}
if ($operatorId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'vehicle_not_linked_to_operator']);
    exit;
}

$stmtF = $db->prepare("SELECT application_id FROM franchise_applications WHERE operator_id=? AND status IN ('Approved','LTFRB-Approved') ORDER BY application_id DESC LIMIT 1");
if ($stmtF) {
    $stmtF->bind_param('i', $operatorId);
    $stmtF->execute();
    $fr = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();
    if (!$fr) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'franchise_not_approved']);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
}

$stmtRoute = $db->prepare("SELECT route_id, COALESCE(NULLIF(authorized_units,0), NULLIF(max_vehicle_limit,0), 0) AS capacity FROM routes WHERE route_id=? AND status='Active' LIMIT 1");
$stmtRoute->bind_param('s', $route);
$stmtRoute->execute();
$routeRow = $stmtRoute->get_result()->fetch_assoc();
$stmtRoute->close();

if (!$routeRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'route_not_found']);
    exit;
}
$limit = (int)($routeRow['capacity'] ?? 0);
if ($limit > 0) {
    $stmtCount = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=? AND status='Authorized'");
    $stmtCount->bind_param('s', $route);
    $stmtCount->execute();
    $countRow = $stmtCount->get_result()->fetch_assoc();
    $stmtCount->close();

    $assigned = (int)($countRow['c'] ?? 0);
    if ($assigned >= $limit) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'route_over_capacity']);
        exit;
    }
}

$upd = $db->prepare("UPDATE vehicles SET route_id=? WHERE plate_number=?");
$upd->bind_param('ss', $route, $plate);
$upd->execute();

if ($hasOperatorIdCol) {
    $operatorIdBind = $operatorId > 0 ? $operatorId : null;
    $ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, operator_id, route_id, terminal_name, status) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE operator_id=VALUES(operator_id), route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
    $ins->bind_param('sisss', $plate, $operatorIdBind, $route, $terminal, $status);
} else {
    $ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, route_id, terminal_name, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
    $ins->bind_param('ssss', $plate, $route, $terminal, $status);
}
$ok = $ins->execute();

echo json_encode([
    'ok' => $ok,
    'plate_number' => $plate,
    'operator_id' => $operatorId,
    'route_id' => $route,
    'terminal_name' => $terminal,
    'status' => $status
]);
?> 
