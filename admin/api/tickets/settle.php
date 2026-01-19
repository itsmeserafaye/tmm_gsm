<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module3.settle');

$ticket = trim((string)($_POST['ticket_number'] ?? ''));
$amount = (float)($_POST['amount_paid'] ?? 0);
$receipt = trim((string)($_POST['receipt_ref'] ?? ($_POST['or_no'] ?? '')));
$verified = 1;
$channel = trim((string)($_POST['payment_channel'] ?? ''));
$externalPaymentId = trim((string)($_POST['external_payment_id'] ?? ''));
$datePaid = trim((string)($_POST['date_paid'] ?? ''));

if ($ticket === '' || $amount <= 0) {
  echo json_encode(['error' => 'Ticket number and amount are required']);
  exit;
}

$receipt = trim($receipt);
if ($receipt === '') {
  http_response_code(400);
  echo json_encode(['error' => 'Receipt Ref (OR number) is required']);
  exit;
}

$ticket2 = $ticket;
$stmt = $db->prepare("SELECT ticket_id FROM tickets WHERE ticket_number = ? OR external_ticket_number = ? LIMIT 1");
$stmt->bind_param('ss', $ticket, $ticket2);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $tid = (int)$row['ticket_id'];
  $hasChannel = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'payment_channel'")->num_rows ?? 0) > 0;
  $hasExt = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'external_payment_id'")->num_rows ?? 0) > 0;
  $hasDatePaid = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'date_paid'")->num_rows ?? 0) > 0;
  $paidAt = $datePaid !== '' ? $datePaid : date('Y-m-d H:i:s');

  if ($hasChannel && $hasExt && $hasDatePaid) {
    $stmtP = $db->prepare("INSERT INTO payment_records (ticket_id, amount_paid, date_paid, receipt_ref, verified_by_treasury, payment_channel, external_payment_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtP->bind_param('idssiss', $tid, $amount, $paidAt, $receipt, $verified, $channel, $externalPaymentId);
  } else {
    $stmtP = $db->prepare("INSERT INTO payment_records (ticket_id, amount_paid, receipt_ref, verified_by_treasury) VALUES (?, ?, ?, ?)");
    $stmtP->bind_param('idsi', $tid, $amount, $receipt, $verified);
  }
  if ($stmtP->execute()) {
    $stmtTP = $db->prepare("INSERT INTO ticket_payments (ticket_id, or_no, amount_paid, paid_at) VALUES (?, ?, ?, ?)");
    if ($stmtTP) {
      $stmtTP->bind_param('isds', $tid, $receipt, $amount, $paidAt);
      $stmtTP->execute();
      $stmtTP->close();
    }
    $db->query("UPDATE tickets SET status='Settled', payment_ref='" . $db->real_escape_string($receipt) . "' WHERE ticket_id = $tid");
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Settled']);
  } else {
    echo json_encode(['error' => 'Failed to record payment']);
  }
} else {
  echo json_encode(['error' => 'Ticket not found']);
}
?> 
