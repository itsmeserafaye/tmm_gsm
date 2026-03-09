<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method !== 'POST' && $method !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$terminalId = (int)(($method === 'POST') ? ($_POST['terminal_id'] ?? 0) : ($_GET['terminal_id'] ?? 0));
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
if ($capacity <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'capacity_required']);
  exit;
}
if ($capacity > 2000) $capacity = 2000;

$existing = [];
$stmtSlots = $db->prepare("SELECT slot_no FROM parking_slots WHERE terminal_id=?");
if ($stmtSlots) {
  $stmtSlots->bind_param('i', $terminalId);
  $stmtSlots->execute();
  $resSlots = $stmtSlots->get_result();
  while ($resSlots && ($r = $resSlots->fetch_assoc())) {
    $slotNo = trim((string)($r['slot_no'] ?? ''));
    if ($slotNo === '') continue;
    $existing[$slotNo] = true;
  }
  $stmtSlots->close();
}

$added = 0;
$stmtIns = $db->prepare("INSERT IGNORE INTO parking_slots (terminal_id, slot_no, status) VALUES (?, ?, 'Free')");
if (!$stmtIns) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
for ($i = 1; $i <= $capacity; $i++) {
  $slotNo = (string)$i;
  if (isset($existing[$slotNo])) continue;
  $stmtIns->bind_param('is', $terminalId, $slotNo);
  if ($stmtIns->execute()) $added++;
}
$stmtIns->close();

$count = 0;
$stmtCount = $db->prepare("SELECT COUNT(*) AS c FROM parking_slots WHERE terminal_id=?");
if ($stmtCount) {
  $stmtCount->bind_param('i', $terminalId);
  $stmtCount->execute();
  $rowC = $stmtCount->get_result()->fetch_assoc();
  $stmtCount->close();
  $count = (int)($rowC['c'] ?? 0);
}

echo json_encode(['ok' => true, 'terminal_id' => $terminalId, 'capacity' => $capacity, 'added' => $added, 'slot_count' => $count]);
