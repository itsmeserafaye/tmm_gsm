<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$ticket_id = $input['ticket_id'] ?? null;
$status = $input['status'] ?? null;
$payment_ref = $input['payment_ref'] ?? null;
$notes = $input['notes'] ?? ''; // Could be logged in a history table

if (!$ticket_id || !$status) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate Payment Ref for Settlement
if ($status === 'Settled' && empty($payment_ref)) {
    echo json_encode(['ok' => false, 'error' => 'Payment Reference is required for settlement.']);
    exit;
}

$valid_statuses = ['Pending', 'Validated', 'Settled', 'Escalated'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid status']);
    exit;
}

// Update Status
$sql = "UPDATE tickets SET status = ?";
$types = "s";
$params = [$status];

if ($payment_ref) {
    $sql .= ", payment_ref = ?";
    $types .= "s";
    $params[] = $payment_ref;
}

$sql .= " WHERE ticket_number = ?"; // Using ticket_number as ID
$types .= "s";
$params[] = $ticket_id;

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => $db->error]);
}
?>