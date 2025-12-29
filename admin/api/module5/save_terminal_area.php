<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = $_POST['terminal_id'] ?? 0;
$name = $_POST['area_name'] ?? '';
$route = $_POST['route_name'] ?? '';
$fare = $_POST['fare_range'] ?? '';
$slots = $_POST['max_slots'] ?? 0;
$type = $_POST['puv_type'] ?? '';

if (!$terminalId || !$name) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $db->prepare("INSERT INTO terminal_designated_areas (terminal_id, area_name, route_name, fare_range, max_slots, puv_type) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('isssis', $terminalId, $name, $route, $fare, $slots, $type);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>