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

    $routePk = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $routeCode = strtoupper(trim((string)($_POST['route_code'] ?? ($_POST['route_id'] ?? ''))));
    $routeName = trim((string)($_POST['route_name'] ?? ''));
    $origin = trim((string)($_POST['origin'] ?? ''));
    $destination = trim((string)($_POST['destination'] ?? ''));
    $via = trim((string)($_POST['via'] ?? ''));
    $structure = trim((string)($_POST['structure'] ?? ''));
    $vehicleType = trim((string)($_POST['vehicle_type'] ?? ''));
    $distanceKm = isset($_POST['distance_km']) ? (float)$_POST['distance_km'] : null;
    $fareRaw = trim((string)($_POST['fare'] ?? ''));
    $fare = $fareRaw === '' ? null : (float)$fareRaw;
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

    if ($fare !== null && $fare < 0) {
        throw new Exception('invalid_fare');
    }

    $maxLimit = $authorizedUnits !== null && $authorizedUnits > 0 ? $authorizedUnits : null;
    if ($maxLimit === null) $maxLimit = 50;

    $distanceBind = $distanceKm;
    $authorizedBind = $authorizedUnits !== null ? $authorizedUnits : 0;
    $viaBind = $via !== '' ? $via : null;
    $approvedByBind = $approvedBy !== '' ? $approvedBy : null;
    $approvedDateBind = $approvedDate !== '' ? $approvedDate : null;
    $fareBind = $fare;

    if ($routePk > 0) {
        $stmtCur = $db->prepare("SELECT id FROM routes WHERE id=? LIMIT 1");
        if (!$stmtCur) throw new Exception('db_prepare_failed');
        $stmtCur->bind_param('i', $routePk);
        $stmtCur->execute();
        $exists = $stmtCur->get_result()->fetch_assoc();
        $stmtCur->close();
        if (!$exists) throw new Exception('route_not_found');

        $stmtDup = $db->prepare("SELECT id FROM routes WHERE (route_id=? OR route_code=?) AND id<>? LIMIT 1");
        if (!$stmtDup) throw new Exception('db_prepare_failed');
        $stmtDup->bind_param('ssi', $routeCode, $routeCode, $routePk);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($dup) throw new Exception('duplicate_route_code');

        $stmt = $db->prepare("UPDATE routes
                              SET route_id=?, route_code=?, route_name=?, vehicle_type=?, origin=?, destination=?, via=?, structure=?, distance_km=?, fare=?, authorized_units=?, max_vehicle_limit=?, status=?, approved_by=?, approved_date=?
                              WHERE id=?");
        if (!$stmt) throw new Exception('db_prepare_failed');
        $stmt->bind_param('ssssssssddiisssi', $routeCode, $routeCode, $routeName, $vehicleType, $origin, $destination, $viaBind, $structure, $distanceBind, $fareBind, $authorizedBind, $maxLimit, $status, $approvedByBind, $approvedDateBind, $routePk);
        $ok = $stmt->execute();
        $stmt->close();
    } else {
        $stmtDup = $db->prepare("SELECT id FROM routes WHERE route_id=? OR route_code=? LIMIT 1");
        if (!$stmtDup) throw new Exception('db_prepare_failed');
        $stmtDup->bind_param('ss', $routeCode, $routeCode);
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();
        if ($dup) throw new Exception('duplicate_route_code');

        $stmt = $db->prepare("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, fare, authorized_units, max_vehicle_limit, status, approved_by, approved_date)
                              VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        if (!$stmt) throw new Exception('db_prepare_failed');
        $stmt->bind_param('ssssssssddiisss', $routeCode, $routeCode, $routeName, $vehicleType, $origin, $destination, $viaBind, $structure, $distanceBind, $fareBind, $authorizedBind, $maxLimit, $status, $approvedByBind, $approvedDateBind);
        $ok = $stmt->execute();
        $routePk = (int)$db->insert_id;
        $stmt->close();
    }

    echo json_encode(['ok' => $ok, 'route_code' => $routeCode, 'route_id' => $routeCode, 'id' => $routePk]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
