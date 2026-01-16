<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = $_POST['terminal_id'] ?? 0;
$inspector = $_POST['inspector'] ?? '';
$date = $_POST['date'] ?? '';
$location = $_POST['location'] ?? '';

if (!$terminalId) {
    echo json_encode(['success' => false, 'message' => 'Terminal required']);
    exit;
}

$stmt = $db->prepare("INSERT INTO terminal_inspections (terminal_id, inspector_name, inspection_date, location, status) VALUES (?, ?, ?, ?, 'Passed')");
$stmt->bind_param('isss', $terminalId, $inspector, $date, $location);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>
