<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_permission('module1.vehicles.write');

    $plateRaw = (string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? ''));
    $plateNorm = strtoupper(preg_replace('/\s+/', '', trim($plateRaw)));
    $plateNorm = preg_replace('/[^A-Z0-9-]/', '', $plateNorm);
    $letters = substr(preg_replace('/[^A-Z]/', '', $plateNorm), 0, 3);
    $digits = substr(preg_replace('/[^0-9]/', '', $plateNorm), 0, 4);
    $plate = ($letters !== '' && $digits !== '') ? ($letters . '-' . $digits) : $plateNorm;
    if ($plate === '' || !preg_match('/^[A-Z]{3}\-[0-9]{3,4}$/', $plate)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_plate']);
        exit;
    }

    $type = trim((string)($_POST['vehicle_type'] ?? ''));
    $engineNo = strtoupper(trim((string)($_POST['engine_no'] ?? ($_POST['engine_number'] ?? ''))));
    $chassisNo = strtoupper(trim((string)($_POST['chassis_no'] ?? ($_POST['chassis_number'] ?? ''))));
    $make = trim((string)($_POST['make'] ?? ''));
    $model = trim((string)($_POST['model'] ?? ''));
    $yearModel = trim((string)($_POST['year_model'] ?? ''));
    $fuelType = trim((string)($_POST['fuel_type'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $operatorId = isset($_POST['operator_id']) && $_POST['operator_id'] !== '' ? (int)$_POST['operator_id'] : 0;
    $operatorName = trim((string)($_POST['operator_name'] ?? ''));

    if ($type === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'missing_vehicle_type']);
        exit;
    }

    if ($engineNo !== '' && !preg_match('/^[A-Z0-9\-]{5,20}$/', $engineNo)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_engine_no']);
        exit;
    }
    if ($chassisNo !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $chassisNo)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_chassis_no']);
        exit;
    }

    $vehicleStatus = 'Active';
    $recordStatus = ($operatorId > 0 || $operatorName !== '') ? 'Linked' : 'Encoded';

    $opNameResolved = '';
    if ($operatorId > 0) {
        $stmtOp = $db->prepare("SELECT name, full_name FROM operators WHERE id=? LIMIT 1");
        if ($stmtOp) {
            $stmtOp->bind_param('i', $operatorId);
            $stmtOp->execute();
            $rowOp = $stmtOp->get_result()->fetch_assoc();
            $stmtOp->close();
            if ($rowOp) {
                $opNameResolved = trim((string)($rowOp['name'] ?? ''));
                if ($opNameResolved === '') $opNameResolved = trim((string)($rowOp['full_name'] ?? ''));
            }
        }
    }
    if ($opNameResolved === '' && $operatorName !== '') $opNameResolved = $operatorName;

    $route = '';
    $franchise = '';
    $inspectionStatus = 'Pending';

    $stmt = $db->prepare("INSERT INTO vehicles(plate_number, vehicle_type, operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, color, record_status, status, inspection_status)
                          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            vehicle_type=VALUES(vehicle_type),
                            operator_id=VALUES(operator_id),
                            operator_name=VALUES(operator_name),
                            engine_no=VALUES(engine_no),
                            chassis_no=VALUES(chassis_no),
                            make=VALUES(make),
                            model=VALUES(model),
                            year_model=VALUES(year_model),
                            fuel_type=VALUES(fuel_type),
                            color=VALUES(color),
                            record_status=VALUES(record_status),
                            status=CASE WHEN status IS NULL OR status='' OR status IN ('Linked','Unlinked') THEN VALUES(status) ELSE status END,
                            inspection_status=COALESCE(NULLIF(inspection_status,''), VALUES(inspection_status))");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
        exit;
    }
    $operatorIdBind = $operatorId > 0 ? $operatorId : null;
    $stmt->bind_param('ssisssssssssss', $plate, $type, $operatorIdBind, $opNameResolved, $engineNo, $chassisNo, $make, $model, $yearModel, $fuelType, $color, $recordStatus, $vehicleStatus, $inspectionStatus);
    $ok = $stmt->execute();

    echo json_encode(['ok' => $ok, 'vehicle_id' => (int)$db->insert_id, 'plate_number' => $plate, 'status' => $vehicleStatus, 'inspection_status' => $inspectionStatus]);
} catch (Exception $e) {
    if (defined('TMM_TEST')) {
        throw $e;
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?> 
