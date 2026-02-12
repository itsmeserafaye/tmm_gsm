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
 
$terminalId = (int)($_POST['terminal_id'] ?? 0);
$priority = trim((string)($_POST['priority'] ?? 'Normal'));
if (!in_array($priority, ['Normal','Priority'], true)) $priority = 'Normal';
 
$plateRaw = strtoupper(trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? ''))));
$plateRaw = preg_replace('/\s+/', '', $plateRaw);
$plateNoDash = preg_replace('/[^A-Z0-9]/', '', $plateRaw);
$plate = $plateRaw !== null ? (string)$plateRaw : '';
$plateNoDash = $plateNoDash !== null ? (string)$plateNoDash : '';
if ($plate !== '' && strpos($plate, '-') === false) {
  if (preg_match('/^([A-Z0-9]+)(\d{3,4})$/', $plateNoDash, $m)) {
    $plate = $m[1] . '-' . $m[2];
  }
}
 
if ($terminalId <= 0 || $plate === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
 
$stmtV = $db->prepare("SELECT id, plate_number FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
$vehicleId = null;
if ($stmtV) {
  $stmtV->bind_param('ss', $plate, $plateNoDash);
  $stmtV->execute();
  $veh = $stmtV->get_result()->fetch_assoc();
  $stmtV->close();
  if ($veh) {
    $vehicleId = (int)($veh['id'] ?? 0);
    $plate = (string)($veh['plate_number'] ?? $plate);
  }
}
 
$stmtDup = $db->prepare("SELECT queue_id FROM terminal_queue WHERE terminal_id=? AND plate_number=? AND status='Queued' LIMIT 1");
if (!$stmtDup) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$stmtDup->bind_param('is', $terminalId, $plate);
$stmtDup->execute();
$dup = $stmtDup->get_result()->fetch_assoc();
$stmtDup->close();
if ($dup) {
  echo json_encode(['ok'=>true,'queue_id'=>(int)($dup['queue_id'] ?? 0), 'dedup'=>true]);
  exit;
}
 
$stmt = $db->prepare("INSERT INTO terminal_queue (terminal_id, vehicle_id, plate_number, priority, status, created_at) VALUES (?, ?, ?, ?, 'Queued', NOW())");
if (!$stmt) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }
$vehIdBind = $vehicleId && $vehicleId > 0 ? $vehicleId : null;
$stmt->bind_param('iiss', $terminalId, $vehIdBind, $plate, $priority);
$ok = $stmt->execute();
$qid = (int)$stmt->insert_id;
$stmt->close();
 
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_error']); exit; }
echo json_encode(['ok'=>true,'queue_id'=>$qid,'plate_number'=>$plate,'priority'=>$priority]);
?>
