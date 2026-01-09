<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$terminalId = isset($payload['terminal_id']) ? (int)$payload['terminal_id'] : 0;
$routeId = isset($payload['route_id']) ? trim((string)$payload['route_id']) : '';
$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
if ($terminalId <= 0 || empty($items)) {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

$stmt = $db->prepare("INSERT INTO traffic_data(terminal_id, route_id, ts, avg_speed_kph, congestion_index, travel_time_min, source) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE avg_speed_kph=VALUES(avg_speed_kph), congestion_index=VALUES(congestion_index), travel_time_min=VALUES(travel_time_min), source=VALUES(source)");
$inserted = 0;
foreach ($items as $it) {
  $ts = isset($it['ts']) ? trim($it['ts']) : null;
  if ($ts === null || $ts === '') { continue; }
  $speed = isset($it['avg_speed_kph']) ? (double)$it['avg_speed_kph'] : null;
  $cong = isset($it['congestion_index']) ? (double)$it['congestion_index'] : null;
  $tt = isset($it['travel_time_min']) ? (double)$it['travel_time_min'] : null;
  $src = isset($it['source']) ? trim((string)$it['source']) : null;
  $rid = isset($it['route_id']) ? trim((string)$it['route_id']) : $routeId;
  $stmt->bind_param('issddds', $terminalId, $rid, $ts, $speed, $cong, $tt, $src);
  if ($stmt->execute()) { $inserted++; }
}
echo json_encode(['ok'=>true,'inserted'=>$inserted]);
?> 
