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
if (!is_array($payload)) { $payload = []; }
$terminalId = isset($payload['terminal_id']) ? (int)$payload['terminal_id'] : 0;
$routeId = isset($payload['route_id']) ? trim((string)$payload['route_id']) : '';
$start = isset($payload['start']) ? trim((string)$payload['start']) : '';
$end = isset($payload['end']) ? trim((string)$payload['end']) : '';
$weatherUrl = isset($payload['weather_url']) ? trim((string)$payload['weather_url']) : '';
$trafficUrl = isset($payload['traffic_url']) ? trim((string)$payload['traffic_url']) : '';
$eventUrl = isset($payload['event_url']) ? trim((string)$payload['event_url']) : '';
if ($terminalId <= 0) {
  echo json_encode(['ok'=>false,'error'=>'missing_terminal_id']);
  exit;
}

function parse_items($json) {
  if (!is_array($json)) return [];
  if (isset($json['items']) && is_array($json['items'])) return $json['items'];
  if (isset($json['data']) && is_array($json['data'])) return $json['data'];
  return is_array($json) ? $json : [];
}

$insert_weather = 0;
$insert_traffic = 0;
$insert_events = 0;

if ($weatherUrl !== '') {
  $params = [];
  if ($start !== '') $params['start'] = $start;
  if ($end !== '') $params['end'] = $end;
  $params['terminal_id'] = $terminalId;
  $q = http_build_query($params);
  $res = @file_get_contents($weatherUrl . (strpos($weatherUrl, '?') === false ? '?' : '&') . $q);
  if ($res !== false) {
    $data = json_decode($res, true);
    $items = parse_items($data);
    $stmtW = $db->prepare("INSERT INTO weather_data(terminal_id, ts, temp_c, humidity, rainfall_mm, wind_kph, weather_code, source) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE temp_c=VALUES(temp_c), humidity=VALUES(humidity), rainfall_mm=VALUES(rainfall_mm), wind_kph=VALUES(wind_kph), weather_code=VALUES(weather_code), source=VALUES(source)");
    foreach ($items as $it) {
      $ts = isset($it['ts']) ? trim((string)$it['ts']) : '';
      if ($ts === '') continue;
      $temp = isset($it['temp_c']) ? (double)$it['temp_c'] : null;
      $hum = isset($it['humidity']) ? (double)$it['humidity'] : null;
      $rain = isset($it['rainfall_mm']) ? (double)$it['rainfall_mm'] : null;
      $wind = isset($it['wind_kph']) ? (double)$it['wind_kph'] : null;
      $code = isset($it['weather_code']) ? trim((string)$it['weather_code']) : null;
      $src = isset($it['source']) ? trim((string)$it['source']) : 'ai';
      $stmtW->bind_param('isddddss', $terminalId, $ts, $temp, $hum, $rain, $wind, $code, $src);
      if ($stmtW->execute()) { $insert_weather++; }
    }
  }
}

if ($trafficUrl !== '') {
  $params = [];
  if ($start !== '') $params['start'] = $start;
  if ($end !== '') $params['end'] = $end;
  $params['terminal_id'] = $terminalId;
  if ($routeId !== '') $params['route_id'] = $routeId;
  $q = http_build_query($params);
  $res = @file_get_contents($trafficUrl . (strpos($trafficUrl, '?') === false ? '?' : '&') . $q);
  if ($res !== false) {
    $data = json_decode($res, true);
    $items = parse_items($data);
    $stmtT = $db->prepare("INSERT INTO traffic_data(terminal_id, route_id, ts, avg_speed_kph, congestion_index, travel_time_min, source) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE avg_speed_kph=VALUES(avg_speed_kph), congestion_index=VALUES(congestion_index), travel_time_min=VALUES(travel_time_min), source=VALUES(source)");
    foreach ($items as $it) {
      $ts = isset($it['ts']) ? trim((string)$it['ts']) : '';
      if ($ts === '') continue;
      $speed = isset($it['avg_speed_kph']) ? (double)$it['avg_speed_kph'] : null;
      $cong = isset($it['congestion_index']) ? (double)$it['congestion_index'] : null;
      $tt = isset($it['travel_time_min']) ? (double)$it['travel_time_min'] : null;
      $rid = isset($it['route_id']) ? trim((string)$it['route_id']) : $routeId;
      $src = isset($it['source']) ? trim((string)$it['source']) : 'ai';
      $stmtT->bind_param('issddds', $terminalId, $rid, $ts, $speed, $cong, $tt, $src);
      if ($stmtT->execute()) { $insert_traffic++; }
    }
  }
}

if ($eventUrl !== '') {
  $params = [];
  $params['terminal_id'] = $terminalId;
  $q = http_build_query($params);
  $res = @file_get_contents($eventUrl . (strpos($eventUrl, '?') === false ? '?' : '&') . $q);
  if ($res !== false) {
    $data = json_decode($res, true);
    $items = parse_items($data);
    $stmtE = $db->prepare("INSERT INTO event_data(terminal_id, title, ts_start, ts_end, expected_attendance, priority, location, source) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($items as $it) {
      $title = isset($it['title']) ? trim((string)$it['title']) : '';
      $tsStart = isset($it['ts_start']) ? trim((string)$it['ts_start']) : '';
      if ($title === '' || $tsStart === '') continue;
      $tsEnd = isset($it['ts_end']) ? trim((string)$it['ts_end']) : null;
      $att = isset($it['expected_attendance']) ? (int)$it['expected_attendance'] : null;
      $prio = isset($it['priority']) ? (int)$it['priority'] : null;
      $loc = isset($it['location']) ? trim((string)$it['location']) : null;
      $src = isset($it['source']) ? trim((string)$it['source']) : 'ai';
      $stmtE->bind_param('isssiiss', $terminalId, $title, $tsStart, $tsEnd, $att, $prio, $loc, $src);
      if ($stmtE->execute()) { $insert_events++; }
    }
  }
}

echo json_encode([
  'ok' => true,
  'terminal_id' => $terminalId,
  'route_id' => $routeId,
  'inserted_weather' => $insert_weather,
  'inserted_traffic' => $insert_traffic,
  'inserted_events' => $insert_events
]);
?> 
