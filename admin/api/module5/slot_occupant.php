<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal', 'module5.parking_fees']);

$slotId = (int)($_GET['slot_id'] ?? 0);
if ($slotId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_slot_id']);
  exit;
}

$stmtS = $db->prepare("SELECT slot_id, terminal_id, slot_no, status FROM parking_slots WHERE slot_id=? LIMIT 1");
if (!$stmtS) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtS->bind_param('i', $slotId);
$stmtS->execute();
$slot = $stmtS->get_result()->fetch_assoc();
$stmtS->close();

if (!$slot) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'slot_not_found']);
  exit;
}

$occupant = null;
$stmtP = $db->prepare("SELECT p.payment_id, p.amount, p.or_no, p.paid_at,
                              v.plate_number,
                              COALESCE(v.vehicle_type, '') AS vehicle_type,
                              COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), '') AS operator_name
                       FROM parking_payments p
                       JOIN vehicles v ON v.id=p.vehicle_id
                       LEFT JOIN operators o ON o.id=v.operator_id
                       WHERE p.slot_id=?
                       ORDER BY p.paid_at DESC, p.payment_id DESC
                       LIMIT 1");
if ($stmtP) {
  $stmtP->bind_param('i', $slotId);
  $stmtP->execute();
  $row = $stmtP->get_result()->fetch_assoc();
  $stmtP->close();
  if ($row) $occupant = $row;
}

echo json_encode(['ok' => true, 'slot' => $slot, 'occupant' => $occupant]);
