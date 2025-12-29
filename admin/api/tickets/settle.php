<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$ticket = trim($_POST['ticket_number'] ?? '');
$amount = (float)($_POST['amount_paid'] ?? 0);
$receipt = trim($_POST['receipt_ref'] ?? '');
$verified = isset($_POST['verified_by_treasury']) ? (int)$_POST['verified_by_treasury'] : 1;

if ($ticket === '' || $amount <= 0) {
  echo json_encode(['error' => 'Ticket number and amount are required']);
  exit;
}

$stmt = $db->prepare("SELECT ticket_id FROM tickets WHERE ticket_number = ?");
$stmt->bind_param('s', $ticket);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $tid = (int)$row['ticket_id'];
  $stmtP = $db->prepare("INSERT INTO payment_records (ticket_id, amount_paid, receipt_ref, verified_by_treasury) VALUES (?, ?, ?, ?)");
  $stmtP->bind_param('idsi', $tid, $amount, $receipt, $verified);
  if ($stmtP->execute()) {
    $db->query("UPDATE tickets SET status='Settled', payment_ref='" . $db->real_escape_string($receipt) . "' WHERE ticket_id = $tid");
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Settled']);
  } else {
    echo json_encode(['error' => 'Failed to record payment']);
  }
} else {
  echo json_encode(['error' => 'Ticket not found']);
}
?> 
