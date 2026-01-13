<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_permission('module1.vehicles.write');

    $plate = strtoupper(trim($_POST['plate_number'] ?? ''));
    if ($plate === '' || !preg_match('/^[A-Z0-9-]{6,12}$/', $plate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_plate']);
        exit;
    }

    $type = trim($_POST['vehicle_type'] ?? '');
    $operator = trim($_POST['operator_name'] ?? '');
    $franchise = trim($_POST['franchise_id'] ?? '');
    $route = '';
    $status = 'Suspended';
    $inspectionStatus = 'Pending';

    if ($plate === '' || $type === '' || $operator === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_fields']);
        exit;
    }

    if ($franchise !== '') {
        $franchise = strtoupper($franchise);
        if (!preg_match('/^[0-9]{4}-[0-9]{3,6}$/', $franchise)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_franchise_format']);
            exit;
        }
        $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=? LIMIT 1");
        if ($stmtF) {
            $stmtF->bind_param('s', $franchise);
            $stmtF->execute();
            $fr = $stmtF->get_result()->fetch_assoc();
            $stmtF->close();
            if (!$fr) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'franchise_not_found']);
                exit;
            }
        }
    }

    $stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_name, franchise_id, route_id, status, inspection_status) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), operator_name=VALUES(operator_name), franchise_id=VALUES(franchise_id), route_id=VALUES(route_id), status=VALUES(status), inspection_status=COALESCE(NULLIF(inspection_status,''), VALUES(inspection_status))");
    $stmt->bind_param('sssssss', $plate, $type, $operator, $franchise, $route, $status, $inspectionStatus);
    $ok = $stmt->execute();

    echo json_encode(['ok' => $ok, 'plate_number' => $plate, 'status' => $status, 'inspection_status' => $inspectionStatus]);
} catch (Exception $e) {
    if (defined('TMM_TEST')) {
        throw $e;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?> 
