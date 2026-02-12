<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
 
$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.parking_fees','module5.read']);
 
$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_terminal_id']); exit; }
 
$status = trim((string)($_GET['status'] ?? 'Queued'));
if (!in_array($status, ['Queued','Served','Cancelled','All'], true)) $status = 'Queued';
 
$where = "terminal_id=?";
if ($status !== 'All') $where .= " AND status=?";
 
$sql = "SELECT queue_id, terminal_id, vehicle_id, plate_number, priority, status, created_at, served_at, notes
        FROM terminal_queue
        WHERE {$where}
        ORDER BY (priority='Priority') DESC, created_at ASC, queue_id ASC
        LIMIT 500";
 
if ($status !== 'All') {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('is', $terminalId, $status);
} else {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
  $stmt->bind_param('i', $terminalId);
}
 
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $rows[] = [
    'queue_id' => (int)($r['queue_id'] ?? 0),
    'terminal_id' => (int)($r['terminal_id'] ?? 0),
    'vehicle_id' => isset($r['vehicle_id']) ? (int)$r['vehicle_id'] : null,
    'plate_number' => (string)($r['plate_number'] ?? ''),
    'priority' => (string)($r['priority'] ?? 'Normal'),
    'status' => (string)($r['status'] ?? 'Queued'),
    'created_at' => (string)($r['created_at'] ?? ''),
    'served_at' => (string)($r['served_at'] ?? ''),
    'notes' => (string)($r['notes'] ?? ''),
  ];
}
$stmt->close();
 
echo json_encode(['ok'=>true,'data'=>$rows]);
?>
