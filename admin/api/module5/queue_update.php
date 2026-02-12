<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
 
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees']);
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
  exit;
}
 
$queueId = (int)($_POST['queue_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
if ($queueId <= 0 || !in_array($action, ['serve','cancel','set_priority'], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
 
if ($action === 'set_priority') {
  $priority = trim((string)($_POST['priority'] ?? 'Normal'));
  if (!in_array($priority, ['Normal','Priority'], true)) $priority = 'Normal';
  $stmt = $db->prepare("UPDATE terminal_queue SET priority=? WHERE queue_id=?");
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('si', $priority, $queueId);
  $ok = $stmt->execute();
  $stmt->close();
  echo json_encode(['ok'=>(bool)$ok,'queue_id'=>$queueId,'priority'=>$priority]);
  exit;
}
 
$status = $action === 'serve' ? 'Served' : 'Cancelled';
$stmt = $db->prepare("UPDATE terminal_queue SET status=?, served_at=NOW() WHERE queue_id=? AND status='Queued'");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmt->bind_param('si', $status, $queueId);
$ok = $stmt->execute();
$affected = (int)$stmt->affected_rows;
$stmt->close();
 
if (!$ok || $affected <= 0) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'not_queued']);
  exit;
}
echo json_encode(['ok'=>true,'queue_id'=>$queueId,'status'=>$status]);
?>
