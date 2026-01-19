<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'Terminal'));
$capacity = (int)($_POST['capacity'] ?? 0);

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

$locationFinal = $location !== '' ? $location : ($address !== '' ? $address : null);
$addressFinal = $address !== '' ? $address : ($location !== '' ? $location : null);
$typeFinal = $type !== '' ? $type : 'Terminal';
$stmt = $db->prepare("INSERT INTO terminals (name, location, address, type, capacity) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'ok' => false, 'message' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('ssssi', $name, $locationFinal, $addressFinal, $typeFinal, $capacity);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'ok' => false, 'message' => 'db_error']);
  exit;
}
$terminalId = (int)$stmt->insert_id;
echo json_encode(['success' => true, 'ok' => true, 'message' => 'Terminal saved', 'terminal_id' => $terminalId, 'id' => $terminalId]);
?>
