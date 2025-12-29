<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalId = $_POST['terminal_id'] ?? 0;
$amount = $_POST['amount'] ?? 0;
$type = $_POST['type'] ?? '';
$due = $_POST['due_date'] ?? '';

if (!$terminalId || !$amount || !$type) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

$receipt = 'REC-' . date('Y') . '-' . mt_rand(1000, 9999);
$stmt = $db->prepare("INSERT INTO terminal_charges (terminal_id, amount, charge_type, due_date, receipt_no, status) VALUES (?, ?, ?, ?, ?, 'Paid')");
$stmt->bind_param('idsss', $terminalId, $amount, $type, $due, $receipt);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'receipt' => $receipt]);
} else {
    echo json_encode(['success' => false, 'message' => $db->error]);
}
?>