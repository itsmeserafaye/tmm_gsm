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
$slotId = (int)($_POST['slot_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$orNo = trim((string)($_POST['or_no'] ?? ''));

if ($plate === '' || $slotId <= 0 || $amount <= 0 || $orNo === '') {
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

$stmtS = $db->prepare("SELECT slot_id, status, terminal_id FROM parking_slots WHERE slot_id=? LIMIT 1");
if (!$stmtS) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtS->bind_param('i', $slotId);
$stmtS->execute();
$slot = $stmtS->get_result()->fetch_assoc();
$stmtS->close();
if (!$slot) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'slot_not_found']); exit; }
if (($slot['status'] ?? '') !== 'Free') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'slot_not_free']); exit; }

$slotTerminalId = (int)($slot['terminal_id'] ?? 0);
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
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'vehicle_restricted_to_assigned_terminals']);
        exit;
      }
    }
  }
}

$db->begin_transaction();
try {
  $stmtP = $db->prepare("INSERT INTO parking_payments (vehicle_id, slot_id, amount, or_no, paid_at) VALUES (?, ?, ?, ?, NOW())");
  if (!$stmtP) throw new Exception('db_prepare_failed');
  $stmtP->bind_param('iids', $vehicleId, $slotId, $amount, $orNo);
  if (!$stmtP->execute()) throw new Exception('insert_failed');
  $paymentId = (int)$stmtP->insert_id;
  $stmtP->close();

  $stmtU = $db->prepare("UPDATE parking_slots SET status='Occupied' WHERE slot_id=?");
  if ($stmtU) {
    $stmtU->bind_param('i', $slotId);
    $stmtU->execute();
    $stmtU->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'payment_id' => $paymentId]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
