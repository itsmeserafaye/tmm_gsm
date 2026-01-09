<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$entity = trim($_POST['entity_name'] ?? '');
$ref_num = trim($_POST['franchise_ref'] ?? '');
$violation = trim($_POST['violation_type'] ?? '');
$penalty = (float)($_POST['penalty_amount'] ?? 0);
$details = trim($_POST['details'] ?? '');

if (empty($entity) || empty($violation)) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$stmt = $db->prepare("INSERT INTO compliance_cases (franchise_ref_number, entity_name, violation_type, penalty_amount, violation_details, status, reported_at) VALUES (?, ?, ?, ?, ?, 'Open', NOW())");
$stmt->bind_param('sssds', $ref_num, $entity, $violation, $penalty, $details);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'case_id' => $db->insert_id]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>
