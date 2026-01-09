<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$type = $_POST['type'] ?? 'Terminal';
$capacity = $_POST['capacity'] ?? 0;
$applicant = $_POST['applicant'] ?? '';

if (!$name) {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

// 1. Create Terminal Record
$stmt = $db->prepare("INSERT INTO terminals (name, address, type, capacity) VALUES (?, ?, ?, ?)");
$stmt->bind_param('sssi', $name, $address, $type, $capacity);

if ($stmt->execute()) {
    $terminalId = $stmt->insert_id;
    
    // 2. Create Permit Application Record automatically
    $appNo = 'PERM-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $stmt2 = $db->prepare("INSERT INTO terminal_permits (terminal_id, application_no, applicant_name, status) VALUES (?, ?, ?, 'Pending')");
    $stmt2->bind_param('iss', $terminalId, $appNo, $applicant);
    $stmt2->execute();

    echo json_encode(['success' => true, 'message' => 'Application submitted', 'id' => $terminalId, 'app_no' => $appNo]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $db->error]);
}
?>