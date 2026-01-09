<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

$terminalId = isset($_GET['terminal_id']) ? (int)$_GET['terminal_id'] : 0;
$routeId = trim($_GET['route_id'] ?? '');
$start = trim($_GET['start'] ?? '');
$end = trim($_GET['end'] ?? '');

if ($terminalId <= 0 || $routeId === '' || $start === '' || $end === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

$stmt = $db->prepare("
  SELECT
    DATE_FORMAT(l.time_in, '%Y-%m-%d %H:00:00') AS ts_hour,
    COUNT(*) AS trips
  FROM terminal_logs l
  JOIN vehicles v ON v.plate_number = l.vehicle_plate
  WHERE l.activity_type = 'Dispatch'
    AND l.terminal_id = ?
    AND v.route_id = ?
    AND l.time_in BETWEEN ? AND ?
  GROUP BY ts_hour
  ORDER BY ts_hour
");
$stmt->bind_param('isss', $terminalId, $routeId, $start, $end);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $rows[] = $r;
}
echo json_encode(['ok'=>true,'data'=>$rows,'terminal_id'=>$terminalId,'route_id'=>$routeId]);
?> 
