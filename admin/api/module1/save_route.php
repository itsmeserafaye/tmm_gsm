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

    $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes'");
    $routeCols = [];
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $routeCols[(string)($c['COLUMN_NAME'] ?? '')] = true;
        }
    }
    $hasFareMin = isset($routeCols['fare_min']);
    $hasFareMax = isset($routeCols['fare_max']);

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
    $fareMinRaw = trim((string)($_POST['fare_min'] ?? ''));
    $fareMaxRaw = trim((string)($_POST['fare_max'] ?? ''));
    $fareMin = $fareMinRaw === '' ? null : (float)$fareMinRaw;
    $fareMax = $fareMaxRaw === '' ? null : (float)$fareMaxRaw;
    $authorizedUnits = isset($_POST['authorized_units']) ? (int)$_POST['authorized_units'] : null;
    $status = trim((string)($_POST['status'] ?? 'Active'));

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

    $statusAllowed = ['Active','Inactive'];
    $stOk = false;
    foreach ($statusAllowed as $s) {
        if (strcasecmp($status, $s) === 0) { $status = $s; $stOk = true; break; }
    }
    if (!$stOk) $status = 'Active';

    if ($fare !== null && $fare < 0) {
        throw new Exception('invalid_fare');
    }
    if ($fareMin !== null && $fareMin < 0) {
        throw new Exception('invalid_fare');
    }
    if ($fareMax !== null && $fareMax < 0) {
        throw new Exception('invalid_fare');
    }
    if ($fareMin !== null && $fareMax !== null && $fareMax < $fareMin) {
        throw new Exception('invalid_fare_range');
    }

    if ($fareMin === null && $fareMax !== null) $fareMin = $fareMax;
    if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
    if ($fare === null && $fareMin !== null) $fare = $fareMin;
    if ($fareMin === null && $fare !== null) $fareMin = $fare;
    if ($fareMax === null && $fare !== null) $fareMax = $fare;

    $suggestAuthorized = function ($vehicleType, $distanceKm, $fare, $fareMin, $fareMax, $routeCode): int {
        $vt = is_string($vehicleType) ? trim($vehicleType) : '';
        $km = is_numeric($distanceKm) ? (float)$distanceKm : 0.0;
        $fareRef = 0.0;
        if (is_numeric($fare)) $fareRef = (float)$fare;
        else if (is_numeric($fareMin)) $fareRef = (float)$fareMin;
        else if (is_numeric($fareMax)) $fareRef = (float)$fareMax;
        $code = is_string($routeCode) ? strtoupper($routeCode) : '';

        $n = 50;
        if ($vt === 'Bus') {
            if (strpos($code, 'CAROUSEL') !== false) $n = 200;
            else if ($km >= 150) $n = 25;
            else if ($km >= 80) $n = 35;
            else if ($km > 0) $n = 55;
            else if ($fareRef >= 700) $n = 25;
            else if ($fareRef >= 300) $n = 35;
            else $n = 45;
        } else if ($vt === 'UV') {
            if ($km >= 25) $n = 80;
            else if ($km > 0) $n = 110;
            else if ($fareRef >= 50) $n = 90;
            else $n = 120;
        } else if ($vt === 'Jeepney') {
            if ($km >= 18) $n = 90;
            else if ($km > 0) $n = 120;
            else if ($fareRef >= 25) $n = 100;
            else $n = 140;
        } else if ($vt === 'Tricycle') {
            if ($km >= 6) $n = 180;
            else if ($km > 0) $n = 220;
            else if ($fareRef >= 30) $n = 220;
            else $n = 260;
        }
        if ($n < 5) $n = 5;
        if ($n > 500) $n = 500;
        return (int)$n;
    };

    if ($authorizedUnits === null || $authorizedUnits <= 0) {
        $authorizedUnits = $suggestAuthorized($vehicleType, $distanceKm, $fare, $fareMin, $fareMax, $routeCode);
    }

    $maxLimit = $authorizedUnits;
    if ($maxLimit <= 0) $maxLimit = 50;

    $distanceBind = $distanceKm;
    $authorizedBind = (int)$authorizedUnits;
    $viaBind = $via !== '' ? $via : null;
    $fareBind = $fare;
    $fareMinBind = $fareMin;
    $fareMaxBind = $fareMax;

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

        $set = "route_id=?, route_code=?, route_name=?, vehicle_type=?, origin=?, destination=?, via=?, structure=?, distance_km=?, fare=?, authorized_units=?, max_vehicle_limit=?, status=?";
        $types = 'ssssssssddiis';
        $params = [$routeCode, $routeCode, $routeName, $vehicleType, $origin, $destination, $viaBind, $structure, $distanceBind, $fareBind, $authorizedBind, $maxLimit, $status];
        if ($hasFareMin) { $set .= ", fare_min=?"; $types .= 'd'; $params[] = $fareMinBind; }
        if ($hasFareMax) { $set .= ", fare_max=?"; $types .= 'd'; $params[] = $fareMaxBind; }

        $types .= 'i';
        $params[] = $routePk;

        $stmt = $db->prepare("UPDATE routes SET $set WHERE id=?");
        if (!$stmt) throw new Exception('db_prepare_failed');
        $stmt->bind_param($types, ...$params);
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

        $cols = ['route_id', 'route_code', 'route_name', 'vehicle_type', 'origin', 'destination', 'via', 'structure', 'distance_km', 'fare', 'authorized_units', 'max_vehicle_limit', 'status'];
        $vals = array_fill(0, count($cols), '?');
        $types = 'ssssssssddiis';
        $params = [$routeCode, $routeCode, $routeName, $vehicleType, $origin, $destination, $viaBind, $structure, $distanceBind, $fareBind, $authorizedBind, $maxLimit, $status];

        if ($hasFareMin) { $cols[] = 'fare_min'; $vals[] = '?'; $types .= 'd'; $params[] = $fareMinBind; }
        if ($hasFareMax) { $cols[] = 'fare_max'; $vals[] = '?'; $types .= 'd'; $params[] = $fareMaxBind; }

        $stmt = $db->prepare("INSERT INTO routes(" . implode(',', $cols) . ") VALUES(" . implode(',', $vals) . ")");
        if (!$stmt) throw new Exception('db_prepare_failed');
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $routePk = (int)$db->insert_id;
        $stmt->close();
    }

    echo json_encode(['ok' => $ok, 'route_code' => $routeCode, 'route_id' => $routeCode, 'id' => $routePk]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
