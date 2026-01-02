<?php
header('Content-Type: application/json');
require_once 'db.php';

$conn = db();

$plate = $_GET['plate'] ?? '';

if (empty($plate)) {
    echo json_encode(['success' => false, 'message' => 'Plate number is required']);
    exit;
}

// Clean input
$plate = $conn->real_escape_string($plate);

// Query vehicle
$sql = "SELECT v.plate_number, v.status, v.coop_name, r.route_name 
        FROM vehicles v 
        LEFT JOIN routes r ON v.route_id = r.route_id 
        WHERE v.plate_number = '$plate'";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $vehicle = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'data' => [
            'plate_number' => $vehicle['plate_number'],
            'status' => $vehicle['status'], // Active / Suspended
            'coop_name' => $vehicle['coop_name'],
            'route' => $vehicle['route_name'] ?? 'Unassigned'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
}
?>