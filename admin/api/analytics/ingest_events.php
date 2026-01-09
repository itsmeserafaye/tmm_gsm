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
$items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
if ($terminalId <= 0 || empty($items)) {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

$stmt = $db->prepare("INSERT INTO event_data(terminal_id, title, ts_start, ts_end, expected_attendance, priority, location, source) VALUES (?,?,?,?,?,?,?,?)");
$inserted = 0;
foreach ($items as $it) {
  $title = isset($it['title']) ? trim((string)$it['title']) : '';
  $tsStart = isset($it['ts_start']) ? trim($it['ts_start']) : null;
  if ($title === '' || $tsStart === null || $tsStart === '') { continue; }
  $tsEnd = isset($it['ts_end']) ? trim($it['ts_end']) : null;
  $att = isset($it['expected_attendance']) ? (int)$it['expected_attendance'] : null;
  $prio = isset($it['priority']) ? (int)$it['priority'] : null;
  $loc = isset($it['location']) ? trim((string)$it['location']) : null;
  $src = isset($it['source']) ? trim((string)$it['source']) : null;
  $stmt->bind_param('isssiiss', $terminalId, $title, $tsStart, $tsEnd, $att, $prio, $loc, $src);
  if ($stmt->execute()) { $inserted++; }
}
echo json_encode(['ok'=>true,'inserted'=>$inserted]);
?> 
