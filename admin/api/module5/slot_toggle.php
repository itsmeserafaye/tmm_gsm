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

$slotId = (int)($_POST['slot_id'] ?? 0);
if ($slotId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_slot_id']);
  exit;
}

$stmt = $db->prepare("SELECT status FROM parking_slots WHERE slot_id=? LIMIT 1");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('i', $slotId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'slot_not_found']); exit; }

$cur = (string)($row['status'] ?? 'Free');
$next = $cur === 'Occupied' ? 'Free' : 'Occupied';
$stmt2 = $db->prepare("UPDATE parking_slots SET status=? WHERE slot_id=?");
if (!$stmt2) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt2->bind_param('si', $next, $slotId);
$ok = $stmt2->execute();
$stmt2->close();
echo json_encode(['ok' => (bool)$ok, 'status' => $next]);
