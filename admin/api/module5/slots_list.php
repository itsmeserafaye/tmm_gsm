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

// Status reconciliation: reflect open occupancy events and free released ones
try {
  // 1) Close any lingering open events from previous days
  $stmtCloseEvents = $db->prepare("UPDATE parking_slot_events
                                   SET time_out=IFNULL(time_out, CONCAT(CURDATE(),' 00:00:00'))
                                   WHERE terminal_id=?
                                     AND time_out IS NULL
                                     AND DATE(time_in) < CURDATE()");
  if ($stmtCloseEvents) {
    $stmtCloseEvents->bind_param('i', $terminalId);
    $stmtCloseEvents->execute();
    $stmtCloseEvents->close();
  }
  // 1b) Normalize blank/legacy statuses to Free
  $stmtNorm = $db->prepare("UPDATE parking_slots
                            SET status='Free'
                            WHERE terminal_id=?
                              AND (status IS NULL OR TRIM(COALESCE(status,''))='' OR TRIM(COALESCE(status,''))='0')");
  if ($stmtNorm) {
    $stmtNorm->bind_param('i', $terminalId);
    $stmtNorm->execute();
    $stmtNorm->close();
  }
  // 2) Ensure any slot with an open occupancy event is marked Occupied
  $stmtOcc = $db->prepare("UPDATE parking_slots ps
                           SET ps.status='Occupied'
                           WHERE ps.terminal_id=?
                             AND EXISTS (
                               SELECT 1
                               FROM parking_slot_events e
                               WHERE e.slot_id=ps.slot_id
                                 AND e.time_out IS NULL
                             )");
  if ($stmtOcc) {
    $stmtOcc->bind_param('i', $terminalId);
    $stmtOcc->execute();
    $stmtOcc->close();
  }
  // 3) Free any slot marked Occupied that no longer has an open event
  $stmtFree = $db->prepare("UPDATE parking_slots ps
                            SET ps.status='Free'
                            WHERE ps.terminal_id=?
                              AND COALESCE(ps.status,'Free') <> 'Free'
                              AND NOT EXISTS (
                                SELECT 1
                                FROM parking_slot_events e
                                WHERE e.slot_id=ps.slot_id
                                  AND e.time_out IS NULL
                              )");
  if ($stmtFree) {
    $stmtFree->bind_param('i', $terminalId);
    $stmtFree->execute();
    $stmtFree->close();
  }
} catch (Throwable $e) {
  // ignore reconciliation errors to avoid breaking listing
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
} else {
  try {
    $stmtCount = $db->prepare("SELECT COUNT(*) AS c FROM parking_slots WHERE terminal_id=?");
    if ($stmtCount) {
      $stmtCount->bind_param('i', $terminalId);
      $stmtCount->execute();
      $rowCount = $stmtCount->get_result()->fetch_assoc();
      $stmtCount->close();
      $slotCount = (int)($rowCount['c'] ?? 0);
      if ($slotCount === 0) {
        $stmtIns2 = $db->prepare("INSERT IGNORE INTO parking_slots (terminal_id, slot_no, status) VALUES (?, ?, 'Free')");
        if ($stmtIns2) {
          $defaultCount = 20;
          for ($j = 1; $j <= $defaultCount; $j++) {
            $slotNo2 = (string)$j;
            $stmtIns2->bind_param('is', $terminalId, $slotNo2);
            $stmtIns2->execute();
          }
          $stmtIns2->close();
        }
      }
    }
  } catch (Throwable $e) { }
}

$res = $db->query("SELECT slot_id, slot_id AS id, terminal_id, slot_no, status
                  FROM parking_slots
                  WHERE terminal_id=" . (int)$terminalId . "
                  ORDER BY (slot_no REGEXP '^[0-9]+$') DESC, CAST(slot_no AS UNSIGNED) ASC, slot_no ASC");
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);
