<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$terminalId = (int)($_POST['terminal_id'] ?? 0);
$slotNo = strtoupper(trim((string)($_POST['slot_no'] ?? '')));
$slotNo = preg_replace('/\s+/', ' ', $slotNo);
$slotNo = $slotNo !== null ? (string)$slotNo : '';
if (strlen($slotNo) > 64) $slotNo = substr($slotNo, 0, 64);
if ($terminalId <= 0 || $slotNo === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fields']);
  exit;
}

$stmtT = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
if ($stmtT) {
  $stmtT->bind_param('i', $terminalId);
  $stmtT->execute();
  $t = $stmtT->get_result()->fetch_assoc();
  $stmtT->close();
  if (!$t) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'terminal_not_found']);
    exit;
  }
}

$stmt = $db->prepare("INSERT INTO parking_slots (terminal_id, slot_no, status) VALUES (?, ?, 'Free')");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('is', $terminalId, $slotNo);
$ok = $stmt->execute();
if (!$ok) {
  $errno = (int)($stmt->errno ?? 0);
  if ($errno === 1062) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'duplicate_slot']);
    exit;
  }
  if ($errno === 1452) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'terminal_not_found']);
    exit;
  }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_error']);
  exit;
}
echo json_encode(['ok' => true, 'slot_id' => (int)$stmt->insert_id]);
