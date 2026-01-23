<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $db = db();
    require_permission('module1.routes.write');

    $routeCode = strtoupper(trim((string)($_POST['route_code'] ?? ($_POST['route_id'] ?? ''))));
    $routeName = trim((string)($_POST['route_name'] ?? ''));
    $origin = trim((string)($_POST['origin'] ?? ''));
    $destination = trim((string)($_POST['destination'] ?? ''));
    $via = trim((string)($_POST['via'] ?? ''));
    $structure = trim((string)($_POST['structure'] ?? ''));
    $vehicleType = trim((string)($_POST['vehicle_type'] ?? ''));
    $distanceKm = isset($_POST['distance_km']) ? (float)$_POST['distance_km'] : null;
    $authorizedUnits = isset($_POST['authorized_units']) ? (int)$_POST['authorized_units'] : null;
    $status = trim((string)($_POST['status'] ?? 'Active'));
    $approvedBy = trim((string)($_POST['approved_by'] ?? ''));
    $approvedDate = trim((string)($_POST['approved_date'] ?? ''));

    if ($routeCode === '' || strlen($routeCode) < 2) {
        throw new Exception('invalid_route_code');
    }
    if ($routeName === '') $routeName = $routeCode;

    $allowedStruct = ['Loop','Point-to-Point'];
    $structOk = false;
    foreach ($allowedStruct as $s) {
        if (strcasecmp($structure, $s) === 0) { $structure = $s; $structOk = true; break; }
    }
    if (!$structOk) $structure = null;

    $allowedVehicleTypes = ['Tricycle','Jeepney','UV','Bus'];
    $vehOk = false;
    foreach ($allowedVehicleTypes as $t) {
        if (strcasecmp($vehicleType, $t) === 0) { $vehicleType = $t; $vehOk = true; break; }
    }
    if (!$vehOk) $vehicleType = null;

    if ($approvedDate !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $approvedDate)) {
        $approvedDate = '';
    }

    $statusAllowed = ['Active','Inactive'];
    $stOk = false;
    foreach ($statusAllowed as $s) {
        if (strcasecmp($status, $s) === 0) { $status = $s; $stOk = true; break; }
    }
    if (!$stOk) $status = 'Active';

    $maxLimit = $authorizedUnits !== null && $authorizedUnits > 0 ? $authorizedUnits : null;
    if ($maxLimit === null) $maxLimit = 50;

    $stmt = $db->prepare("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, authorized_units, max_vehicle_limit, status, approved_by, approved_date)
                          VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE
                            route_code=VALUES(route_code),
                            route_name=VALUES(route_name),
                            vehicle_type=VALUES(vehicle_type),
                            origin=VALUES(origin),
                            destination=VALUES(destination),
                            via=VALUES(via),
                            structure=VALUES(structure),
                            distance_km=VALUES(distance_km),
                            authorized_units=VALUES(authorized_units),
                            max_vehicle_limit=VALUES(max_vehicle_limit),
                            status=VALUES(status),
                            approved_by=VALUES(approved_by),
                            approved_date=VALUES(approved_date)");
    if (!$stmt) {
        throw new Exception('db_prepare_failed');
    }
    $distanceBind = $distanceKm;
    $authorizedBind = $authorizedUnits;
    $viaBind = $via !== '' ? $via : null;
    $approvedByBind = $approvedBy !== '' ? $approvedBy : null;
    $approvedDateBind = $approvedDate !== '' ? $approvedDate : null;
    $stmt->bind_param('ssssssssdiiiss', $routeCode, $routeCode, $routeName, $vehicleType, $origin, $destination, $viaBind, $structure, $distanceBind, $authorizedBind, $maxLimit, $status, $approvedByBind, $approvedDateBind);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => $ok, 'route_code' => $routeCode, 'route_id' => $routeCode]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
