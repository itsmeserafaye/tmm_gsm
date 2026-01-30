<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.parking_fees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$plateRaw = strtoupper(trim((string)($_POST['plate_no'] ?? ($_POST['plate_number'] ?? ''))));
$plateRaw = preg_replace('/\s+/', '', $plateRaw);
$plateNoDash = preg_replace('/[^A-Z0-9]/', '', $plateRaw);
$plate = $plateRaw !== null ? (string)$plateRaw : '';
$plateNoDash = $plateNoDash !== null ? (string)$plateNoDash : '';
if ($plate !== '' && strpos($plate, '-') === false) {
  if (preg_match('/^([A-Z0-9]+)(\d{3,4})$/', $plateNoDash, $m)) {
    $plate = $m[1] . '-' . $m[2];
  }
}
$terminalId = (int)($_POST['terminal_id'] ?? 0);
$slotId = (int)($_POST['slot_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$orNo = trim((string)($_POST['or_no'] ?? ''));
$paidAtRaw = trim((string)($_POST['paid_at'] ?? ''));
$exportedToTreasury = isset($_POST['exported_to_treasury']) ? (int)($_POST['exported_to_treasury'] ?? 0) : 0;
$exportedToTreasury = $exportedToTreasury === 1 ? 1 : 0;
$exportedAtRaw = trim((string)($_POST['exported_at'] ?? ''));

$paidAt = null;
if ($paidAtRaw !== '') {
  $ts = strtotime($paidAtRaw);
  if ($ts !== false) $paidAt = date('Y-m-d H:i:s', $ts);
}

$exportedAt = null;
if ($exportedToTreasury === 1) {
  if ($exportedAtRaw !== '') {
    $ts2 = strtotime($exportedAtRaw);
    if ($ts2 !== false) $exportedAt = date('Y-m-d H:i:s', $ts2);
  }
  if ($exportedAt === null) $exportedAt = $paidAt !== null ? $paidAt : date('Y-m-d H:i:s');
}

if ($plate === '' || ($slotId <= 0 && $terminalId <= 0) || $amount <= 0 || $orNo === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmtV = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
if (!$stmtV) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtV->bind_param('ss', $plate, $plateNoDash);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$vehicleId = (int)($veh['id'] ?? 0);

$db->begin_transaction();
try {
  $slotTerminalId = 0;
  if ($slotId > 0) {
    $stmtS = $db->prepare("SELECT slot_id, status, terminal_id FROM parking_slots WHERE slot_id=? LIMIT 1 FOR UPDATE");
    if (!$stmtS) throw new Exception('db_prepare_failed');
    $stmtS->bind_param('i', $slotId);
    $stmtS->execute();
    $slot = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();
    if (!$slot) throw new Exception('slot_not_found');
    if (($slot['status'] ?? '') !== 'Free') throw new Exception('slot_not_free');
    $slotTerminalId = (int)($slot['terminal_id'] ?? 0);
  } else {
    $stmtS = $db->prepare("SELECT slot_id, status, terminal_id
                           FROM parking_slots
                           WHERE terminal_id=? AND status='Free'
                           ORDER BY (slot_no REGEXP '^[0-9]+$') DESC, CAST(slot_no AS UNSIGNED) ASC, slot_no ASC
                           LIMIT 1 FOR UPDATE");
    if (!$stmtS) throw new Exception('db_prepare_failed');
    $stmtS->bind_param('i', $terminalId);
    $stmtS->execute();
    $slot = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();
    if (!$slot) throw new Exception('no_free_slots');
    $slotId = (int)($slot['slot_id'] ?? 0);
    $slotTerminalId = (int)($slot['terminal_id'] ?? 0);
    if ($slotId <= 0) throw new Exception('no_free_slots');
  }

  if ($slotTerminalId > 0) {
    $stmtAssign = $db->prepare("SELECT terminal_id FROM terminal_assignments WHERE vehicle_id=?");
    if ($stmtAssign) {
      $stmtAssign->bind_param('i', $vehicleId);
      $stmtAssign->execute();
      $resAssign = $stmtAssign->get_result();
      $assignedTerminals = [];
      while ($rowA = $resAssign->fetch_assoc()) {
        $assignedTerminals[] = (int)$rowA['terminal_id'];
      }
      $stmtAssign->close();
      if (!empty($assignedTerminals)) {
        if (!in_array($slotTerminalId, $assignedTerminals, true)) {
          throw new Exception('vehicle_restricted_to_assigned_terminals');
        }
      }
    }
  }

  if ($paidAt !== null) {
    $stmtP = $db->prepare("INSERT INTO parking_payments (vehicle_id, slot_id, amount, or_no, paid_at, exported_to_treasury, exported_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmtP) throw new Exception('db_prepare_failed');
    $stmtP->bind_param('iidssis', $vehicleId, $slotId, $amount, $orNo, $paidAt, $exportedToTreasury, $exportedAt);
  } else {
    $stmtP = $db->prepare("INSERT INTO parking_payments (vehicle_id, slot_id, amount, or_no, paid_at, exported_to_treasury, exported_at) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
    if (!$stmtP) throw new Exception('db_prepare_failed');
    $stmtP->bind_param('iidsis', $vehicleId, $slotId, $amount, $orNo, $exportedToTreasury, $exportedAt);
  }
  if (!$stmtP->execute()) throw new Exception('insert_failed');
  $paymentId = (int)$stmtP->insert_id;
  $stmtP->close();

  $stmtU = $db->prepare("UPDATE parking_slots SET status='Occupied' WHERE slot_id=? AND status='Free'");
  if (!$stmtU) throw new Exception('db_prepare_failed');
  $stmtU->bind_param('i', $slotId);
  $stmtU->execute();
  $affected = (int)$stmtU->affected_rows;
  $stmtU->close();
  if ($affected !== 1) throw new Exception('slot_not_free');

  $db->commit();
  echo json_encode(['ok' => true, 'payment_id' => $paymentId, 'slot_id' => $slotId]);
} catch (Throwable $e) {
  $db->rollback();
  $err = (string)$e->getMessage();
  $clientErrors = ['slot_not_found', 'slot_not_free', 'no_free_slots', 'vehicle_restricted_to_assigned_terminals'];
  if (in_array($err, $clientErrors, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $err]);
  } else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
  }
}
