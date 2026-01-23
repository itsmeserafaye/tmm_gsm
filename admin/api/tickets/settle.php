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

$requiredTables = ['tickets', 'payment_records', 'ticket_payments'];
foreach ($requiredTables as $tbl) {
  if (!function_exists('tmm_table_exists') || !tmm_table_exists($db, $tbl)) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'error' => "Database table missing: {$tbl}. Import sql/repair_missing_tables_from_tmm_tmm_3.sql or ensure DB bootstrap ran.",
    ]);
    exit;
  }
}

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

  $hasCol = function (string $table, string $col) use ($db): bool {
    $table = trim($table);
    $col = trim($col);
    if ($table === '' || $col === '') return false;
    $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
    return $res && ($res->num_rows ?? 0) > 0;
  };

  $pkCol = $hasCol('payment_records', 'payment_id') ? 'payment_id' : ($hasCol('payment_records', 'id') ? 'id' : '');
  $receiptCol = $hasCol('payment_records', 'receipt_ref') ? 'receipt_ref' : ($hasCol('payment_records', 'or_no') ? 'or_no' : '');
  $verifiedCol = $hasCol('payment_records', 'verified_by_treasury') ? 'verified_by_treasury' : '';
  $dateCol = $hasCol('payment_records', 'date_paid') ? 'date_paid' : ($hasCol('payment_records', 'paid_at') ? 'paid_at' : '');
  $hasChannel = $hasCol('payment_records', 'payment_channel');
  $hasExt = $hasCol('payment_records', 'external_payment_id');

  if ($pkCol === '' || $receiptCol === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'payment_records_schema_mismatch']);
    exit;
  }
  $paidAt = $datePaid !== '' ? $datePaid : date('Y-m-d H:i:s');

  $existingPaymentId = 0;
  $stmtFind = $db->prepare("SELECT {$pkCol} AS pid FROM payment_records WHERE ticket_id=? ORDER BY {$pkCol} DESC LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('i', $tid);
    $stmtFind->execute();
    $p = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    if ($p && isset($p['pid'])) $existingPaymentId = (int)$p['pid'];
  }

  $db->begin_transaction();
  $okPayment = false;
  $errMsg = '';
  $errNo = 0;
  try {
    if ($existingPaymentId > 0) {
      $sets = "amount_paid=?, {$receiptCol}=?";
      $types = "ds";
      $params = [$amount, $receipt];
      if ($verifiedCol !== '') {
        $sets .= ", {$verifiedCol}=?";
        $types .= "i";
        $params[] = $verified;
      }
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
      $sqlUp = "UPDATE payment_records SET {$sets} WHERE {$pkCol}=?";
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
      $cols = "ticket_id, amount_paid, {$receiptCol}";
      $place = "?, ?, ?";
      $types = "ids";
      $params = [$tid, $amount, $receipt];
      if ($verifiedCol !== '') {
        $cols .= ", {$verifiedCol}";
        $place .= ", ?";
        $types .= "i";
        $params[] = $verified;
      }
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
    tmm_audit_event($db, 'ticket.settle.failed', 'ticket', (string)$tid, ['errno' => $errNo ?: (int)$db->errno, 'db_error' => $errMsg !== '' ? $errMsg : (string)$db->error]);
    $out = ['ok' => false, 'error' => 'Failed to record payment'];
    if (($errNo ?: (int)$db->errno) > 0 && ($errMsg !== '' || (string)$db->error !== '')) {
      $out['error'] = 'Failed to record payment: DB error ' . (string)($errNo ?: (int)$db->errno);
    }
    if ($debugOn) {
      $out['debug'] = [
        'errno' => $errNo ?: (int)$db->errno,
        'db_error' => $errMsg !== '' ? $errMsg : (string)$db->error,
        'date_col' => $dateCol,
        'receipt_col' => $receiptCol,
        'verified_col' => $verifiedCol,
        'pk_col' => $pkCol,
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
