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

    $route_id = trim($_POST['route_id'] ?? '');
    $route_name = trim($_POST['route_name'] ?? '');
    $max_vehicle_limit = isset($_POST['max_vehicle_limit']) ? (int)$_POST['max_vehicle_limit'] : 50;
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $distance = floatval($_POST['distance_km'] ?? 0);
    $fare = floatval($_POST['fare'] ?? 0);
    $status = trim($_POST['status'] ?? 'Active');

    if (empty($route_id) || empty($route_name)) {
        throw new Exception('Route ID and Name are required');
    }

    $sql = "INSERT INTO routes (route_id, route_name, max_vehicle_limit, origin, destination, distance_km, fare, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            route_name=VALUES(route_name), 
            max_vehicle_limit=VALUES(max_vehicle_limit), 
            origin=VALUES(origin), 
            destination=VALUES(destination), 
            distance_km=VALUES(distance_km), 
            fare=VALUES(fare), 
            status=VALUES(status)";
            
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    $stmt->bind_param('ssissdds', $route_id, $route_name, $max_vehicle_limit, $origin, $destination, $distance, $fare, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'message' => 'Route saved successfully']);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
