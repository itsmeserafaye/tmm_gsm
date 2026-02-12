<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

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

$db->begin_transaction();
try {
  $stmt = $db->prepare("SELECT slot_id, status FROM parking_slots WHERE slot_id=? LIMIT 1 FOR UPDATE");
  if (!$stmt) throw new Exception('db_prepare_failed');
  $stmt->bind_param('i', $slotId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'slot_not_found']); exit; }

  $cur = (string)($row['status'] ?? 'Free');
  if ($cur !== 'Occupied') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'manual_occupy_not_allowed']);
    exit;
  }

  $next = 'Free';
  $stmt2 = $db->prepare("UPDATE parking_slots SET status=? WHERE slot_id=?");
  if (!$stmt2) throw new Exception('db_prepare_failed');
  $stmt2->bind_param('si', $next, $slotId);
  $ok = $stmt2->execute();
  $stmt2->close();
  if (!$ok) throw new Exception('db_error');

  $actorUserId = (int)($_SESSION['user_id'] ?? 0);
  $actorName = trim((string)($_SESSION['name'] ?? ($_SESSION['full_name'] ?? '')));
  if ($actorName === '') $actorName = trim((string)($_SESSION['email'] ?? ($_SESSION['user_email'] ?? '')));
  if ($actorName === '') $actorName = 'Admin';

  $stmtE = $db->prepare("UPDATE parking_slot_events
                         SET time_out=NOW(), released_by_user_id=?, released_by_name=?
                         WHERE slot_id=? AND time_out IS NULL
                         ORDER BY time_in DESC, event_id DESC
                         LIMIT 1");
  if ($stmtE) {
    $stmtE->bind_param('isi', $actorUserId, $actorName, $slotId);
    $stmtE->execute();
    $stmtE->close();
  }

  $db->commit();
  echo json_encode(['ok' => true, 'status' => $next]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db_error']);
}
