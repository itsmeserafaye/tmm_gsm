<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module3.settle');

$ticket = trim((string)($_GET['ticket_number'] ?? ($_GET['q'] ?? '')));
if ($ticket === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ticket_number_required']);
  exit;
}

$ticket2 = $ticket;
$stmt = $db->prepare("SELECT ticket_id, ticket_number, external_ticket_number, vehicle_plate, fine_amount, status, payment_ref
                      FROM tickets
                      WHERE ticket_number=? OR external_ticket_number=?
                      LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('ss', $ticket, $ticket2);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'ticket_not_found']);
  exit;
}

$ticketId = (int)($row['ticket_id'] ?? 0);
$receiptRef = (string)($row['payment_ref'] ?? '');
$paidAt = '';
$channel = '';
$amountPaid = 0.0;
$externalPaymentId = '';

$hasChannel = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'payment_channel'")->num_rows ?? 0) > 0;
$hasExt = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'external_payment_id'")->num_rows ?? 0) > 0;
$hasDatePaid = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'date_paid'")->num_rows ?? 0) > 0;

$cols = "payment_id, amount_paid, receipt_ref";
if ($hasDatePaid) $cols .= ", date_paid";
if ($hasChannel) $cols .= ", payment_channel";
if ($hasExt) $cols .= ", external_payment_id";

$stmtP = $db->prepare("SELECT $cols FROM payment_records WHERE ticket_id=? ORDER BY payment_id DESC LIMIT 1");
if ($stmtP) {
  $stmtP->bind_param('i', $ticketId);
  $stmtP->execute();
  $p = $stmtP->get_result()->fetch_assoc();
  $stmtP->close();
  if ($p) {
    if ($receiptRef === '' && isset($p['receipt_ref'])) $receiptRef = (string)$p['receipt_ref'];
    if (isset($p['amount_paid'])) $amountPaid = (float)$p['amount_paid'];
    if ($hasDatePaid && isset($p['date_paid'])) $paidAt = (string)$p['date_paid'];
    if ($hasChannel && isset($p['payment_channel'])) $channel = (string)$p['payment_channel'];
    if ($hasExt && isset($p['external_payment_id'])) $externalPaymentId = (string)$p['external_payment_id'];
  }
}

$status = (string)($row['status'] ?? '');
$isPaid = strtolower($status) === 'settled' || $receiptRef !== '';

echo json_encode([
  'ok' => true,
  'ticket' => [
    'ticket_id' => $ticketId,
    'ticket_number' => (string)($row['ticket_number'] ?? ''),
    'external_ticket_number' => (string)($row['external_ticket_number'] ?? ''),
    'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
    'status' => $status,
    'fine_amount' => (float)($row['fine_amount'] ?? 0),
    'is_paid' => $isPaid,
    'receipt_ref' => $receiptRef,
    'amount_paid' => $amountPaid,
    'date_paid' => $paidAt,
    'payment_channel' => $channel,
    'external_payment_id' => $externalPaymentId,
  ],
]);
