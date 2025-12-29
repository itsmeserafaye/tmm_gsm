<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = $_POST['terminal_id'] ?? 0;
$operator = $_POST['operator'] ?? '';
$plate = $_POST['plate'] ?? '';

if (!$terminalId || !$operator || !$plate) {
    echo json_encode(['success' => false, 'message' => 'All fields required']);
    exit;
}

// Check if vehicle already enrolled
$check = $db->query("SELECT id FROM terminal_operators WHERE vehicle_plate = '$plate' AND status='Active'");
if ($check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Vehicle already enrolled']);
    exit;
}

$stmt = $db->prepare("INSERT INTO terminal_operators (terminal_id, operator_name, vehicle_plate) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $terminalId, $operator, $plate);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>