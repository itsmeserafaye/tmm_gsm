<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/util.php';

$db = db();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$expectedKey = (string)getenv('TMM_TREASURY_INTEGRATION_KEY');
$headerKey = (string)($_SERVER['HTTP_X_INTEGRATION_KEY'] ?? '');
$queryKey = (string)($_GET['integration_key'] ?? '');
$okAuth = false;
if ($expectedKey !== '' && ($headerKey !== '' || $queryKey !== '')) {
  $provided = $headerKey !== '' ? $headerKey : $queryKey;
  if (hash_equals($expectedKey, $provided)) $okAuth = true;
}

$callbackToken = (string)getenv('TMM_TREASURY_CALLBACK_TOKEN');
$token = (string)($_GET['token'] ?? '');
if (!$okAuth && $callbackToken !== '' && $token !== '' && hash_equals($callbackToken, $token)) {
  $okAuth = true;
}

if (!$okAuth) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
  if (!empty($_POST)) $payload = $_POST;
}
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
  exit;
}

$kind = strtolower(trim((string)($payload['kind'] ?? 'ticket')));

$transactionId = trim((string)($payload['transaction_id'] ?? ($payload['ref'] ?? '')));
$statusRaw = strtoupper(trim((string)($payload['payment_status'] ?? ($payload['status'] ?? ($payload['result'] ?? 'PAID')))));
$receipt = trim((string)($payload['official_receipt_no'] ?? ($payload['receipt_ref'] ?? ($payload['or_no'] ?? ($payload['receipt'] ?? '')))));
$amountPaid = (float)($payload['amount_paid'] ?? ($payload['amount'] ?? 0));
$datePaid = trim((string)($payload['date_paid'] ?? ($payload['paid_at'] ?? ($payload['payment_date'] ?? ''))));
$channel = trim((string)($payload['payment_channel'] ?? ($payload['channel'] ?? '')));
$externalPaymentId = trim((string)($payload['external_payment_id'] ?? ($payload['external_id'] ?? ($payload['payment_id'] ?? ''))));
$purpose = trim((string)($payload['purpose'] ?? ($payload['description'] ?? '')));

if ($transactionId === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_transaction_id']);
  exit;
}

$paidStatuses = ['PAID', 'SUCCESS', 'COMPLETED', 'OK', 'APPROVED'];
if (!in_array($statusRaw, $paidStatuses, true)) {
  echo json_encode(['ok' => true, 'ignored' => true, 'reason' => 'status_not_paid', 'status' => $statusRaw]);
  exit;
}

$paidAt = $datePaid !== '' ? $datePaid : date('Y-m-d H:i:s');

$stmtReq = $db->prepare("SELECT id, status FROM treasury_payment_requests WHERE ref=? LIMIT 1");
if ($stmtReq) {
  $stmtReq->bind_param('s', $transactionId);
  $stmtReq->execute();
  $existing = $stmtReq->get_result()->fetch_assoc();
  $stmtReq->close();

  $cbJson = json_encode($payload);
  if ($existing) {
    $stmtUp = $db->prepare("UPDATE treasury_payment_requests SET status='paid', amount=?, purpose=?, receipt_ref=?, payment_channel=?, external_payment_id=?, callback_payload=? WHERE id=?");
    if ($stmtUp) {
      $id = (int)$existing['id'];
      $stmtUp->bind_param('dsssssi', $amountPaid, $purpose, $receipt, $channel, $externalPaymentId, $cbJson, $id);
      $stmtUp->execute();
      $stmtUp->close();
    }
  } else {
    $stmtIns = $db->prepare("INSERT INTO treasury_payment_requests(ref, kind, transaction_id, amount, purpose, status, receipt_ref, payment_channel, external_payment_id, callback_payload) VALUES(?,?,?,?,?,'paid',?,?,?,?)");
    if ($stmtIns) {
      $k = $kind !== '' ? $kind : 'ticket';
      $stmtIns->bind_param('sssdsssss', $transactionId, $k, $transactionId, $amountPaid, $purpose, $receipt, $channel, $externalPaymentId, $cbJson);
      $stmtIns->execute();
      $stmtIns->close();
    }
  }
}

if ($kind === 'ticket') {
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

  $already = false;
  if ($receipt !== '') {
    $stmtDup = $db->prepare("SELECT payment_id FROM payment_records WHERE ticket_id=? AND receipt_ref=? LIMIT 1");
    if ($stmtDup) {
      $stmtDup->bind_param('is', $ticketId, $receipt);
      $stmtDup->execute();
      $dup = $stmtDup->get_result()->fetch_assoc();
      $stmtDup->close();
      if ($dup) $already = true;
    }
  }

  if (!$already) {
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
  }

  $stmtUp = $db->prepare("UPDATE tickets SET status='Settled', payment_ref=? WHERE ticket_id=?");
  if ($stmtUp) {
    $stmtUp->bind_param('si', $receipt, $ticketId);
    $stmtUp->execute();
    $stmtUp->close();
  }

  echo json_encode(['ok' => true, 'kind' => 'ticket', 'transaction_id' => $transactionId, 'ticket_id' => $ticketId, 'already_recorded' => $already]);
  exit;
}

if ($kind === 'parking') {
  $id = (int)$transactionId;
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_parking_transaction_id']);
    exit;
  }
  $stmt = $db->prepare("UPDATE parking_transactions SET status='Paid', receipt_ref=?, payment_channel=?, external_payment_id=?, paid_at=? WHERE id=?");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmt->bind_param('ssssi', $receipt, $channel, $externalPaymentId, $paidAt, $id);
  $stmt->execute();
  $stmt->close();
  echo json_encode(['ok' => true, 'kind' => 'parking', 'transaction_id' => $transactionId]);
  exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unsupported_kind']);
