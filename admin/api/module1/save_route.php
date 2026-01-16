<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $db = db();
    require_permission('module1.routes.write');

    $route_id = strtoupper(trim($_POST['route_id'] ?? ''));
    $distanceProvided = array_key_exists('distance_km', $_POST);
    $fareProvided = array_key_exists('fare', $_POST);
    $distance = $distanceProvided ? floatval($_POST['distance_km'] ?? 0) : null;
    $fare = $fareProvided ? floatval($_POST['fare'] ?? 0) : null;

    if (empty($route_id)) {
        throw new Exception('Route ID is required');
    }

    $lptrp = tmm_get_lptrp_route($db, $route_id);
    if (!$lptrp || !tmm_lptrp_is_approved($lptrp)) {
        throw new Exception('Route is not LPTRP-approved');
    }

    if (!tmm_sync_routes_from_lptrp($db, $route_id)) {
        throw new Exception('Failed to sync route from LPTRP masterlist');
    }

    $sets = [];
    $types = '';
    $params = [];
    if ($distanceProvided && tmm_table_has_column($db, 'routes', 'distance_km')) {
        $sets[] = 'distance_km=?';
        $types .= 'd';
        $params[] = $distance;
    }
    if ($fareProvided && tmm_table_has_column($db, 'routes', 'fare')) {
        $sets[] = 'fare=?';
        $types .= 'd';
        $params[] = $fare;
    }
    if ($sets) {
        $types .= 's';
        $params[] = $route_id;
        $stmt = $db->prepare("UPDATE routes SET " . implode(',', $sets) . " WHERE route_id=?");
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode(['ok' => true, 'message' => 'Route synced successfully', 'route_id' => $route_id]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
