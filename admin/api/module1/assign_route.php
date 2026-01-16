<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';

$db = db();
header('Content-Type: application/json');
require_permission('module1.routes.write');

$hasOperatorIdCol = false;
$resCol = $db->query("SHOW COLUMNS FROM terminal_assignments LIKE 'operator_id'");
if ($resCol && $resCol->num_rows > 0) {
    $hasOperatorIdCol = true;
} else {
    $db->query("ALTER TABLE terminal_assignments ADD COLUMN operator_id INT NULL AFTER plate_number");
    $db->query("ALTER TABLE terminal_assignments ADD INDEX idx_operator_id (operator_id)");
    $resCol2 = $db->query("SHOW COLUMNS FROM terminal_assignments LIKE 'operator_id'");
    $hasOperatorIdCol = ($resCol2 && $resCol2->num_rows > 0);
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

$chk = $db->prepare("SELECT plate_number, inspection_status, status, franchise_id, operator_name, coop_name, vehicle_type FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$veh = $chk->get_result()->fetch_assoc();

if (!$veh) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
    exit;
}

$inspection = strtoupper(trim((string)($veh['inspection_status'] ?? '')));
if ($inspection !== 'PASSED') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'inspection_not_passed']);
    exit;
}

$franchiseId = trim((string)($veh['franchise_id'] ?? ''));
if ($franchiseId !== '') {
    $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
    $stmtF->bind_param('s', $franchiseId);
    $stmtF->execute();
    $fr = $stmtF->get_result()->fetch_assoc();
    $stmtF->close();

    if (!$fr || ($fr['status'] ?? '') !== 'Endorsed') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'franchise_invalid']);
        exit;
    }
}

$stmtRoute = $db->prepare("SELECT route_id, max_vehicle_limit FROM routes WHERE route_id=?");
$stmtRoute->bind_param('s', $route);
$stmtRoute->execute();
$routeRow = $stmtRoute->get_result()->fetch_assoc();
$stmtRoute->close();

if (!$routeRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'route_not_found']);
    exit;
}

$lptrp = tmm_get_lptrp_route($db, $route);
if (!$lptrp || !tmm_lptrp_is_approved($lptrp)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'route_not_lptrp_approved']);
    exit;
}
if (!tmm_sync_routes_from_lptrp($db, $route)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'route_sync_failed']);
    exit;
}

$stmtRoute = $db->prepare("SELECT route_id, max_vehicle_limit FROM routes WHERE route_id=?");
$stmtRoute->bind_param('s', $route);
$stmtRoute->execute();
$routeRow = $stmtRoute->get_result()->fetch_assoc();
$stmtRoute->close();

$limit = (int)($routeRow['max_vehicle_limit'] ?? 0);
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

$operatorId = null;
if ($hasOperatorIdCol) {
    $opName = trim((string)($veh['operator_name'] ?? ''));
    $coopName = trim((string)($veh['coop_name'] ?? ''));
    if ($opName !== '') {
        if ($coopName !== '') {
            $stmtOp = $db->prepare("SELECT id FROM operators WHERE full_name=? AND coop_name=? LIMIT 1");
            $stmtOp->bind_param('ss', $opName, $coopName);
        } else {
            $stmtOp = $db->prepare("SELECT id FROM operators WHERE full_name=? LIMIT 1");
            $stmtOp->bind_param('s', $opName);
        }
        $stmtOp->execute();
        $opRow = $stmtOp->get_result()->fetch_assoc();
        $stmtOp->close();
        if ($opRow && isset($opRow['id'])) $operatorId = (int)$opRow['id'];
    }
}

if ($hasOperatorIdCol) {
    $operatorIdBind = $operatorId === null ? null : (int)$operatorId;
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
