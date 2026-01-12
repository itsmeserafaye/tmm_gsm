<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_role(['Admin', 'Encoder']);

    $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
    if ($plate === '' || !preg_match('/^[A-Z0-9-]{6,12}$/', $plate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_plate']);
        exit;
    }

    $type = trim($_POST['vehicle_type'] ?? '');
    $operator = trim($_POST['operator_name'] ?? '');
    $franchise = trim($_POST['franchise_id'] ?? '');
    $route = trim($_POST['route_id'] ?? '');
    $status = 'Active';

    if ($route !== '') {
        $stmtR = $db->prepare("SELECT status FROM routes WHERE route_id=? LIMIT 1");
        if ($stmtR) {
            $stmtR->bind_param('s', $route);
            $stmtR->execute();
            $routeRow = $stmtR->get_result()->fetch_assoc();
            $stmtR->close();

            if (!$routeRow) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'route_not_found']);
                exit;
            }

            $routeStatus = strtoupper(trim((string)($routeRow['status'] ?? '')));
            if ($routeStatus !== 'ACTIVE') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'route_inactive']);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'route_validation_failed']);
            exit;
        }
    }

    if ($plate === '' || $type === '' || $operator === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_fields']);
        exit;
    }

    if ($franchise === '') {
        $status = 'Suspended';
    } elseif ($franchise !== '') {
        $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
        if ($stmtF) {
            $stmtF->bind_param('s', $franchise);
            $stmtF->execute();
            $fr = $stmtF->get_result()->fetch_assoc();
            $stmtF->close();
            $status = (!$fr || ($fr['status'] ?? '') !== 'Endorsed') ? 'Suspended' : 'Active';
        }
    }

    $stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_name, franchise_id, route_id, status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), operator_name=VALUES(operator_name), franchise_id=VALUES(franchise_id), route_id=VALUES(route_id), status=VALUES(status)");
    $stmt->bind_param('ssssss', $plate, $type, $operator, $franchise, $route, $status);
    $ok = $stmt->execute();

    echo json_encode(['ok' => $ok, 'plate_number' => $plate, 'status' => $status]);
} catch (Exception $e) {
    if (defined('TMM_TEST')) {
        throw $e;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?> 
