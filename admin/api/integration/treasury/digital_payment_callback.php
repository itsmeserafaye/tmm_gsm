<?php
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/util.php';

$db = db();
header('Content-Type: application/json');

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST','GET'], true)) {
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

if (!$okAuth && $expectedKey === '' && $callbackToken === '') {
  $okAuth = true;
}

if (!$okAuth) {
  tmm_audit_event($db, 'treasury.callback.unauthorized', 'treasury', '-', [
    'has_integration_key' => ($expectedKey !== ''),
    'has_callback_token' => ($callbackToken !== ''),
    'provided_header_key' => ($headerKey !== ''),
    'provided_query_key' => ($queryKey !== ''),
    'provided_token' => ($token !== ''),
    'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
  ]);
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
  if (!empty($_GET)) $payload = $_GET;
}
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
  exit;
}

$transactionId = trim((string)($payload['transaction_id'] ?? ($payload['reference_id'] ?? ($payload['ref'] ?? ($payload['reference'] ?? '')))));
$kind = strtolower(trim((string)($payload['kind'] ?? '')));
if ($kind === '' && $transactionId !== '') {
  $stmtK = $db->prepare("SELECT kind FROM treasury_payment_requests WHERE ref=? LIMIT 1");
  if ($stmtK) {
    $stmtK->bind_param('s', $transactionId);
    $stmtK->execute();
    $rowK = $stmtK->get_result()->fetch_assoc();
    $stmtK->close();
    if ($rowK && isset($rowK['kind'])) $kind = strtolower(trim((string)$rowK['kind']));
  }
  if ($kind === '') {
    if (stripos($transactionId, 'park-') === 0) $kind = 'parking';
    else $kind = 'ticket';
  }
}
$statusRaw = strtoupper(trim((string)($payload['payment_status'] ?? ($payload['status'] ?? ($payload['result'] ?? 'PAID')))));
$receipt = trim((string)($payload['official_receipt_no'] ?? ($payload['receipt_number'] ?? ($payload['receipt_ref'] ?? ($payload['or_no'] ?? ($payload['receipt'] ?? ''))))));
$amountPaid = (float)($payload['amount_paid'] ?? ($payload['amount'] ?? 0));
$datePaid = trim((string)($payload['date_paid'] ?? ($payload['paid_at'] ?? ($payload['payment_date'] ?? ''))));
$channel = trim((string)($payload['payment_channel'] ?? ($payload['payment_method'] ?? ($payload['channel'] ?? ''))));
$externalPaymentId = trim((string)($payload['external_payment_id'] ?? ($payload['external_id'] ?? ($payload['payment_id'] ?? ''))));
$purpose = trim((string)($payload['purpose'] ?? ($payload['description'] ?? '')));

tmm_audit_event($db, 'treasury.callback.received', 'treasury', $transactionId !== '' ? $transactionId : '-', ['keys' => array_keys($payload)]);

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
  if (!function_exists('tmm_table_exists') || !tmm_table_exists($db, 'tickets') || !tmm_table_exists($db, 'payment_records') || !tmm_table_exists($db, 'ticket_payments')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'missing_ticketing_tables']);
    exit;
  }

  $stmtT = $db->prepare("SELECT ticket_id, ticket_number, fine_amount, status FROM tickets WHERE ticket_number=? OR external_ticket_number=? LIMIT 1");
  if (!$stmtT) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $t2 = $transactionId;
  $stmtT->bind_param('ss', $transactionId, $t2);
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
    $q1 = $db->query("SHOW COLUMNS FROM payment_records LIKE 'payment_channel'");
    $q2 = $db->query("SHOW COLUMNS FROM payment_records LIKE 'external_payment_id'");
    $q3 = $db->query("SHOW COLUMNS FROM payment_records LIKE 'date_paid'");
    $q4 = $db->query("SHOW COLUMNS FROM payment_records LIKE 'paid_at'");
    $hasChannel = ($q1 && ($q1->num_rows ?? 0) > 0);
    $hasExt = ($q2 && ($q2->num_rows ?? 0) > 0);
    $hasDatePaid = ($q3 && ($q3->num_rows ?? 0) > 0);
    $hasPaidAt = ($q4 && ($q4->num_rows ?? 0) > 0);
    $dateCol = $hasDatePaid ? 'date_paid' : ($hasPaidAt ? 'paid_at' : '');

    $cols = "ticket_id, amount_paid, receipt_ref, verified_by_treasury";
    $place = "?, ?, ?, 1";
    $types = "ids";
    $params = [$ticketId, $amountPaid, $receipt];
    if ($dateCol !== '') {
      $cols .= ", {$dateCol}";
      $place .= ", ?";
      $types .= "s";
      $params[] = $paidAt;
    }
    if ($hasChannel) {
      $cols .= ", payment_channel";
      $place .= ", ?";
      $types .= "s";
      $params[] = $channel;
    }
    if ($hasExt) {
      $cols .= ", external_payment_id";
      $place .= ", ?";
      $types .= "s";
      $params[] = $externalPaymentId;
    }
    $sqlIns = "INSERT INTO payment_records({$cols}) VALUES({$place})";
    $stmtIns = $db->prepare($sqlIns);
    if (!$stmtIns) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
      exit;
    }
    $bindArgs = [];
    $bindArgs[] = &$types;
    for ($i = 0; $i < count($params); $i++) $bindArgs[] = &$params[$i];
    call_user_func_array([$stmtIns, 'bind_param'], $bindArgs);
    $ok = $stmtIns->execute();
    $stmtIns->close();
    if (!$ok) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'insert_failed']);
      exit;
    }
  }

  if ($receipt !== '') {
    $stmtTP = $db->prepare("INSERT INTO ticket_payments(ticket_id, or_no, amount_paid, paid_at) VALUES(?,?,?,?)");
    if ($stmtTP) {
      $stmtTP->bind_param('isds', $ticketId, $receipt, $amountPaid, $paidAt);
      $stmtTP->execute();
      $stmtTP->close();
    }
  }

  $stmtUp = $db->prepare("UPDATE tickets SET status='Settled', payment_ref=? WHERE ticket_id=?");
  if ($stmtUp) {
    $stmtUp->bind_param('si', $receipt, $ticketId);
    $stmtUp->execute();
    $stmtUp->close();
  }

  tmm_audit_event($db, 'treasury.callback.ticket', 'ticket', (string)$ticketId, ['receipt_ref' => $receipt, 'amount_paid' => $amountPaid, 'external_payment_id' => $externalPaymentId, 'transaction_id' => $transactionId]);
  echo json_encode(['ok' => true, 'kind' => 'ticket', 'transaction_id' => $transactionId, 'ticket_id' => $ticketId, 'already_recorded' => $already]);
  exit;
}

if ($kind === 'parking') {
  $id = (int)$transactionId;
  if ($id <= 0) {
    $stmtFind = $db->prepare("SELECT transaction_id FROM treasury_payment_requests WHERE ref=? AND kind='parking' LIMIT 1");
    if ($stmtFind) {
      $stmtFind->bind_param('s', $transactionId);
      $stmtFind->execute();
      $rowFind = $stmtFind->get_result()->fetch_assoc();
      $stmtFind->close();
      if ($rowFind && isset($rowFind['transaction_id'])) $id = (int)$rowFind['transaction_id'];
    }
  }
  if ($id <= 0 && preg_match('/([0-9]{1,10})$/', $transactionId, $m)) {
    $id = (int)$m[1];
  }
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_parking_transaction_id']);
    exit;
  }
  $stmt = $db->prepare("UPDATE parking_transactions
                        SET status='Paid',
                            receipt_ref=?,
                            reference_no=IF(reference_no IS NULL OR reference_no='', ?, reference_no),
                            payment_channel=?,
                            external_payment_id=?,
                            paid_at=?
                        WHERE id=?");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
    exit;
  }
  $stmt->bind_param('sssssi', $receipt, $receipt, $channel, $externalPaymentId, $paidAt, $id);
  $stmt->execute();
  $stmt->close();
  tmm_audit_event($db, 'treasury.callback.parking', 'parking_transaction', (string)$id, ['receipt_ref' => $receipt, 'external_payment_id' => $externalPaymentId, 'transaction_id' => $transactionId]);
  echo json_encode(['ok' => true, 'kind' => 'parking', 'transaction_id' => $transactionId]);
  exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unsupported_kind']);
