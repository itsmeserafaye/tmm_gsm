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

$stmt = $db->prepare("INSERT INTO weather_data(terminal_id, ts, temp_c, humidity, rainfall_mm, wind_kph, weather_code, source) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE temp_c=VALUES(temp_c), humidity=VALUES(humidity), rainfall_mm=VALUES(rainfall_mm), wind_kph=VALUES(wind_kph), weather_code=VALUES(weather_code), source=VALUES(source)");
$inserted = 0;
foreach ($items as $it) {
  $ts = isset($it['ts']) ? trim($it['ts']) : null;
  if ($ts === null || $ts === '') { continue; }
  $temp = isset($it['temp_c']) ? (double)$it['temp_c'] : null;
  $hum = isset($it['humidity']) ? (double)$it['humidity'] : null;
  $rain = isset($it['rainfall_mm']) ? (double)$it['rainfall_mm'] : null;
  $wind = isset($it['wind_kph']) ? (double)$it['wind_kph'] : null;
  $code = isset($it['weather_code']) ? trim((string)$it['weather_code']) : null;
  $src = isset($it['source']) ? trim((string)$it['source']) : null;
  $stmt->bind_param('isddddss', $terminalId, $ts, $temp, $hum, $rain, $wind, $code, $src);
  if ($stmt->execute()) { $inserted++; }
}
echo json_encode(['ok'=>true,'inserted'=>$inserted]);
?> 
