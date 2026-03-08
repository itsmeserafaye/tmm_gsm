<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees']);

$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}

// Daily reset: ensure slots without a payment today are set to Free
try {
  $stmtReset = $db->prepare("UPDATE parking_slots ps
                             SET ps.status='Free'
                             WHERE ps.terminal_id=?
                               AND COALESCE(ps.status,'Free') <> 'Free'
                               AND NOT EXISTS (
                                 SELECT 1
                                 FROM parking_payments pp
                                 WHERE pp.slot_id=ps.slot_id
                                   AND DATE(pp.paid_at)=CURDATE()
                               )");
  if ($stmtReset) {
    $stmtReset->bind_param('i', $terminalId);
    $stmtReset->execute();
    $stmtReset->close();
  }
  // Close any open events from previous days
  $stmtCloseEvents = $db->prepare("UPDATE parking_slot_events e
                                   SET e.time_out=IFNULL(e.time_out, CONCAT(CURDATE(),' 00:00:00'))
                                   WHERE e.terminal_id=?
                                     AND e.time_out IS NULL
                                     AND DATE(e.time_in) < CURDATE()");
  if ($stmtCloseEvents) {
    $stmtCloseEvents->bind_param('i', $terminalId);
    $stmtCloseEvents->execute();
    $stmtCloseEvents->close();
  }
} catch (Throwable $e) {
  // ignore reset errors to avoid breaking listing
}

$capacity = 0;
$stmtCap = $db->prepare("SELECT COALESCE(capacity, 0) AS capacity FROM terminals WHERE id=? LIMIT 1");
if ($stmtCap) {
  $stmtCap->bind_param('i', $terminalId);
  $stmtCap->execute();
  $rowCap = $stmtCap->get_result()->fetch_assoc();
  $stmtCap->close();
  $capacity = (int)($rowCap['capacity'] ?? 0);
}

if ($capacity > 0) {
  if ($capacity > 2000) $capacity = 2000;
  $stmtSlots = $db->prepare("SELECT slot_no FROM parking_slots WHERE terminal_id=?");
  if ($stmtSlots) {
    $stmtSlots->bind_param('i', $terminalId);
    $stmtSlots->execute();
    $resSlots = $stmtSlots->get_result();
    $existing = [];
    $hasNonNumeric = false;
    while ($resSlots && ($r = $resSlots->fetch_assoc())) {
      $slotNo = trim((string)($r['slot_no'] ?? ''));
      if ($slotNo === '') continue;
      $existing[$slotNo] = true;
      if (!ctype_digit($slotNo)) $hasNonNumeric = true;
    }
    $stmtSlots->close();

    $shouldAutogen = empty($existing) || count($existing) < $capacity;
    if ($shouldAutogen) {
      $stmtIns = $db->prepare("INSERT IGNORE INTO parking_slots (terminal_id, slot_no, status) VALUES (?, ?, 'Free')");
      if ($stmtIns) {
        for ($i = 1; $i <= $capacity; $i++) {
          $slotNo = (string)$i;
          if (isset($existing[$slotNo])) continue;
          $stmtIns->bind_param('is', $terminalId, $slotNo);
          $stmtIns->execute();
        }
        $stmtIns->close();
      }
    }
  }
}

$res = $db->query("SELECT slot_id, terminal_id, slot_no, status
                  FROM parking_slots
                  WHERE terminal_id=" . (int)$terminalId . "
                  ORDER BY (slot_no REGEXP '^[0-9]+$') DESC, CAST(slot_no AS UNSIGNED) ASC, slot_no ASC");
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);
