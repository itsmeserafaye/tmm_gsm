<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/_auth.php';

$db = db();
header('Content-Type: application/json');
tmm_treasury_integration_authorize();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_json']);
  exit;
}

$kind = strtolower(trim((string)($payload['kind'] ?? 'ticket')));
if ($kind !== 'ticket') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'unsupported_kind']);
  exit;
}

$transactionId = trim((string)($payload['transaction_id'] ?? ''));
$paymentStatus = strtoupper(trim((string)($payload['payment_status'] ?? 'PAID')));
$receipt = trim((string)($payload['official_receipt_no'] ?? ($payload['receipt_ref'] ?? '')));
$amountPaid = (float)($payload['amount_paid'] ?? 0);
$datePaid = trim((string)($payload['date_paid'] ?? ''));
$channel = trim((string)($payload['payment_channel'] ?? ''));
$externalPaymentId = trim((string)($payload['external_payment_id'] ?? ''));

if ($transactionId === '' || $paymentStatus === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

if ($paymentStatus !== 'PAID') {
  echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'payment_status_not_paid']);
  exit;
}

$stmtT = $db->prepare("SELECT ticket_id, ticket_number, fine_amount, status FROM tickets WHERE ticket_number=? LIMIT 1");
if (!$stmtT) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtT->bind_param('s', $transactionId);
$stmtT->execute();
$t = $stmtT->get_result()->fetch_assoc();
$stmtT->close();

if (!$t) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'ticket_not_found']);
  exit;
}

$ticketId = (int)($t['ticket_id'] ?? 0);
$fine = (float)($t['fine_amount'] ?? 0);
if ($amountPaid <= 0) $amountPaid = $fine;

$paidAt = $datePaid !== '' ? $datePaid : date('Y-m-d H:i:s');

$stmtIns = $db->prepare("INSERT INTO payment_records(ticket_id, amount_paid, date_paid, receipt_ref, verified_by_treasury, payment_channel, external_payment_id) VALUES(?,?,?,?,1,?,?)");
if (!$stmtIns) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtIns->bind_param('idssss', $ticketId, $amountPaid, $paidAt, $receipt, $channel, $externalPaymentId);
$ok = $stmtIns->execute();
$stmtIns->close();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'insert_failed']);
  exit;
}

$stmtUp = $db->prepare("UPDATE tickets SET status='Settled', payment_ref=? WHERE ticket_id=?");
if ($stmtUp) {
  $stmtUp->bind_param('si', $receipt, $ticketId);
  $stmtUp->execute();
  $stmtUp->close();
}

echo json_encode(['ok' => true, 'kind' => 'ticket', 'transaction_id' => $transactionId, 'ticket_id' => $ticketId]);

