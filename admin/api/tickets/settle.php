<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
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
 
$debug = (string)getenv('TMM_DEBUG_PAYMENTS');
$debugOn = $debug !== '' && strtolower($debug) !== 'off' && $debug !== '0';

if ($ticket === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Ticket number and amount are required']);
  exit;
}

$receipt = trim($receipt);
if ($receipt === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Receipt Ref (OR number) is required']);
  exit;
}

$ticket2 = $ticket;
$stmt = $db->prepare("SELECT ticket_id, status, payment_ref FROM tickets WHERE ticket_number = ? OR external_ticket_number = ? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('ss', $ticket, $ticket2);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
  $tid = (int)$row['ticket_id'];
  $status = (string)($row['status'] ?? '');
  $existingRef = (string)($row['payment_ref'] ?? '');
  $stmt->close();

  if (strtolower($status) === 'settled' && $existingRef !== '') {
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Settled', 'already_settled' => true, 'receipt_ref' => $existingRef]);
    exit;
  }

  $hasChannel = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'payment_channel'")->num_rows ?? 0) > 0;
  $hasExt = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'external_payment_id'")->num_rows ?? 0) > 0;
  $hasDatePaid = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'date_paid'")->num_rows ?? 0) > 0;
  $hasPaidAt = ($db->query("SHOW COLUMNS FROM payment_records LIKE 'paid_at'")->num_rows ?? 0) > 0;
  $dateCol = $hasDatePaid ? 'date_paid' : ($hasPaidAt ? 'paid_at' : '');
  $paidAt = $datePaid !== '' ? $datePaid : date('Y-m-d H:i:s');

  $existingPaymentId = 0;
  $stmtFind = $db->prepare("SELECT payment_id FROM payment_records WHERE ticket_id=? ORDER BY payment_id DESC LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('i', $tid);
    $stmtFind->execute();
    $p = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    if ($p && isset($p['payment_id'])) $existingPaymentId = (int)$p['payment_id'];
  }

  $db->begin_transaction();
  $okPayment = false;
  $errMsg = '';
  $errNo = 0;
  try {
    if ($existingPaymentId > 0) {
      $sets = "amount_paid=?, receipt_ref=?, verified_by_treasury=?";
      $types = "dsi";
      $params = [$amount, $receipt, $verified];
      if ($dateCol !== '') {
        $sets .= ", {$dateCol}=?";
        $types .= "s";
        $params[] = $paidAt;
      }
      if ($hasChannel) {
        $sets .= ", payment_channel=?";
        $types .= "s";
        $params[] = $channel;
      }
      if ($hasExt) {
        $sets .= ", external_payment_id=?";
        $types .= "s";
        $params[] = $externalPaymentId;
      }
      $types .= "i";
      $params[] = $existingPaymentId;
      $sqlUp = "UPDATE payment_records SET {$sets} WHERE payment_id=?";
      $stmtP = $db->prepare($sqlUp);
      if (!$stmtP) throw new Exception('db_prepare_failed');
      $bindArgs = [];
      $bindArgs[] = &$types;
      for ($i = 0; $i < count($params); $i++) {
        $bindArgs[] = &$params[$i];
      }
      call_user_func_array([$stmtP, 'bind_param'], $bindArgs);
      $okPayment = $stmtP->execute();
      $stmtP->close();
    } else {
      $cols = "ticket_id, amount_paid, receipt_ref, verified_by_treasury";
      $place = "?, ?, ?, ?";
      $types = "idsi";
      $params = [$tid, $amount, $receipt, $verified];
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
      $sqlIns = "INSERT INTO payment_records ({$cols}) VALUES ({$place})";
      $stmtP = $db->prepare($sqlIns);
      if (!$stmtP) throw new Exception('db_prepare_failed');
      $bindArgs = [];
      $bindArgs[] = &$types;
      for ($i = 0; $i < count($params); $i++) {
        $bindArgs[] = &$params[$i];
      }
      call_user_func_array([$stmtP, 'bind_param'], $bindArgs);
      $okPayment = $stmtP->execute();
      $stmtP->close();
    }

    if (!$okPayment) {
      $errNo = (int)$db->errno;
      $errMsg = (string)$db->error;
      throw new Exception('payment_write_failed');
    }

    $stmtTP = $db->prepare("INSERT INTO ticket_payments (ticket_id, or_no, amount_paid, paid_at) VALUES (?, ?, ?, ?)");
    if ($stmtTP) {
      $stmtTP->bind_param('isds', $tid, $receipt, $amount, $paidAt);
      $stmtTP->execute();
      $stmtTP->close();
    }
    $stmtT = $db->prepare("UPDATE tickets SET status='Settled', payment_ref=? WHERE ticket_id=?");
    if ($stmtT) {
      $stmtT->bind_param('si', $receipt, $tid);
      $stmtT->execute();
      $stmtT->close();
    } else {
      $db->query("UPDATE tickets SET status='Settled', payment_ref='" . $db->real_escape_string($receipt) . "' WHERE ticket_id = $tid");
    }
    tmm_audit_event($db, 'ticket.settle', 'ticket', (string)$tid, ['receipt_ref' => $receipt, 'amount_paid' => $amount]);
    $db->commit();
    echo json_encode(['ok' => true, 'ticket_id' => $tid, 'status' => 'Settled']);
  } catch (Throwable $e) {
    $db->rollback();
    $out = ['ok' => false, 'error' => 'Failed to record payment'];
    if ($debugOn) {
      $out['debug'] = [
        'errno' => $errNo ?: (int)$db->errno,
        'db_error' => $errMsg !== '' ? $errMsg : (string)$db->error,
        'date_col' => $dateCol,
        'has_channel' => $hasChannel,
        'has_ext' => $hasExt,
      ];
    }
    echo json_encode($out);
  }
} else {
  if ($stmt) $stmt->close();
  echo json_encode(['ok' => false, 'error' => 'Ticket not found']);
}
?> 
