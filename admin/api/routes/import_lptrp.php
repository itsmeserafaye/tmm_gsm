<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');
$csv = '';
if (!empty($_POST['csv'])) { $csv = $_POST['csv']; }
elseif (!empty($_FILES['file']['tmp_name'])) { $csv = file_get_contents($_FILES['file']['tmp_name']); }
elseif (php_sapi_name() === 'cli') {
  global $argv;
  $path = '';
  foreach ($argv as $arg) {
    if (strpos($arg, 'csv=') === 0) { $csv = substr($arg, 4); }
    if (strpos($arg, 'path=') === 0) { $path = substr($arg, 5); }
  }
  if ($csv === '' && $path !== '' && file_exists($path)) { $csv = file_get_contents($path); }
}
if ($csv === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_csv']); exit; }
$lines = preg_split("/\\r?\\n/", trim($csv));
if (!$lines || count($lines) < 2) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'empty_csv']); exit; }
$header = str_getcsv(array_shift($lines));
$map = array_flip(array_map('strtolower', $header));
$required = ['route_id','route_name','origin','destination','distance_km','fare','max_vehicle_limit','status'];
foreach ($required as $r) { if (!isset($map[$r])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_col_'.$r]); exit; } }
$ins = $db->prepare("INSERT INTO routes(route_id, route_name, origin, destination, distance_km, fare, max_vehicle_limit, status) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE route_name=VALUES(route_name), origin=VALUES(origin), destination=VALUES(destination), distance_km=VALUES(distance_km), fare=VALUES(fare), max_vehicle_limit=VALUES(max_vehicle_limit), status=VALUES(status)");
foreach ($lines as $line) {
  if (trim($line) === '') continue;
  $row = str_getcsv($line);
  $route_id = $row[$map['route_id']] ?? '';
  $route_name = $row[$map['route_name']] ?? '';
  $origin = $row[$map['origin']] ?? '';
  $destination = $row[$map['destination']] ?? '';
  $distance_km = floatval($row[$map['distance_km']] ?? 0);
  $fare = floatval($row[$map['fare']] ?? 0);
  $max_limit = intval($row[$map['max_vehicle_limit']] ?? 0);
  $status = $row[$map['status']] ?? 'Active';
  if ($route_id === '' || $route_name === '') continue;
  $ins->bind_param('ssssddis', $route_id, $route_name, $origin, $destination, $distance_km, $fare, $max_limit, $status);
  $ins->execute();
}
echo json_encode(['ok'=>true]);
