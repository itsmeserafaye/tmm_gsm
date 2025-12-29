<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = $_POST['terminal_id'] ?? 0;
$plate = $_POST['plate'] ?? '';
$type = $_POST['type'] ?? '';
$desc = $_POST['description'] ?? '';

if (!$terminalId || !$plate || !$type) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $db->prepare("INSERT INTO terminal_incidents (terminal_id, vehicle_plate, incident_type, description) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $terminalId, $plate, $type, $desc);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>