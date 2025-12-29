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
$activity = $_POST['activity'] ?? '';
$remarks = $_POST['remarks'] ?? '';

if (!$terminalId || !$plate || !$activity) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$stmt = $db->prepare("INSERT INTO terminal_logs (terminal_id, vehicle_plate, activity_type, remarks) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $terminalId, $plate, $activity, $remarks);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>