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

    $shouldAutogen = empty($existing) || (!$hasNonNumeric && count($existing) < $capacity);
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
