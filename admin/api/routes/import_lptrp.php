<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lptrp.php';
$db = db();
require_permission('module1.routes.write');
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

$hasDesc = tmm_table_has_column($db, 'lptrp_routes', 'description');
$hasRouteName = tmm_table_has_column($db, 'lptrp_routes', 'route_name');
$hasApproval = tmm_table_has_column($db, 'lptrp_routes', 'approval_status');
$hasStatus = tmm_table_has_column($db, 'lptrp_routes', 'status');
$hasStart = tmm_table_has_column($db, 'lptrp_routes', 'start_point');
$hasEnd = tmm_table_has_column($db, 'lptrp_routes', 'end_point');

$descCol = $hasDesc ? 'description' : ($hasRouteName ? 'route_name' : null);
$statusCol = $hasApproval ? 'approval_status' : ($hasStatus ? 'status' : null);

$sel = $db->prepare("SELECT id FROM lptrp_routes WHERE route_code=? LIMIT 1");
if (!$sel) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }

$setParts = [];
$typesU = '';
if ($descCol) { $setParts[] = "$descCol=?"; $typesU .= 's'; }
if ($hasStart) { $setParts[] = "start_point=?"; $typesU .= 's'; }
if ($hasEnd) { $setParts[] = "end_point=?"; $typesU .= 's'; }
$setParts[] = "max_vehicle_capacity=?"; $typesU .= 'i';
if ($statusCol) { $setParts[] = "$statusCol=?"; $typesU .= 's'; }
$typesU .= 'i';
$upd = $db->prepare("UPDATE lptrp_routes SET " . implode(',', $setParts) . " WHERE id=?");
if (!$upd) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }

$colsI = ['route_code'];
$valsI = ['?'];
$typesI = 's';
if ($descCol) { $colsI[] = $descCol; $valsI[] = '?'; $typesI .= 's'; }
if ($hasStart) { $colsI[] = 'start_point'; $valsI[] = '?'; $typesI .= 's'; }
if ($hasEnd) { $colsI[] = 'end_point'; $valsI[] = '?'; $typesI .= 's'; }
$colsI[] = 'max_vehicle_capacity'; $valsI[] = '?'; $typesI .= 'i';
if (tmm_table_has_column($db, 'lptrp_routes', 'current_vehicle_count')) { $colsI[] = 'current_vehicle_count'; $valsI[] = '0'; }
if ($statusCol) { $colsI[] = $statusCol; $valsI[] = '?'; $typesI .= 's'; }
$ins = $db->prepare("INSERT INTO lptrp_routes(" . implode(',', $colsI) . ") VALUES(" . implode(',', $valsI) . ")");
if (!$ins) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db_prepare_failed']); exit; }

$routesHasDistance = tmm_table_has_column($db, 'routes', 'distance_km');
$routesHasFare = tmm_table_has_column($db, 'routes', 'fare');
$stmtSetFare = null;
if ($routesHasDistance && $routesHasFare) {
  $stmtSetFare = $db->prepare("UPDATE routes SET distance_km=?, fare=? WHERE route_id=?");
}

$imported = 0;
foreach ($lines as $line) {
  if (trim($line) === '') continue;
  $row = str_getcsv($line);
  $route_id = strtoupper(trim((string)($row[$map['route_id']] ?? '')));
  $route_name = trim((string)($row[$map['route_name']] ?? ''));
  $origin = trim((string)($row[$map['origin']] ?? ''));
  $destination = trim((string)($row[$map['destination']] ?? ''));
  $distance_km = floatval($row[$map['distance_km']] ?? 0);
  $fare = floatval($row[$map['fare']] ?? 0);
  $max_limit = intval($row[$map['max_vehicle_limit']] ?? 0);
  $status = trim((string)($row[$map['status']] ?? 'Approved'));
  if ($route_id === '' || $route_name === '') continue;

  $sel->bind_param('s', $route_id);
  $sel->execute();
  $existing = $sel->get_result()->fetch_assoc();
  $existingId = (int)($existing['id'] ?? 0);

  if ($existingId > 0) {
    $bindU = [];
    if ($descCol) $bindU[] = $route_name;
    if ($hasStart) $bindU[] = $origin;
    if ($hasEnd) $bindU[] = $destination;
    $bindU[] = $max_limit;
    if ($statusCol) $bindU[] = $status;
    $bindU[] = $existingId;
    $upd->bind_param($typesU, ...$bindU);
    $ok = $upd->execute();
  } else {
    $bindI = [];
    $bindI[] = $route_id;
    if ($descCol) $bindI[] = $route_name;
    if ($hasStart) $bindI[] = $origin;
    if ($hasEnd) $bindI[] = $destination;
    $bindI[] = $max_limit;
    if ($statusCol) $bindI[] = $status;
    $ins->bind_param($typesI, ...$bindI);
    $ok = $ins->execute();
  }

  if ($ok) {
      $imported++;
      tmm_sync_routes_from_lptrp($db, $route_id);
      if ($stmtSetFare) {
        $stmtSetFare->bind_param('dds', $distance_km, $fare, $route_id);
        $stmtSetFare->execute();
      }
  }
}
echo json_encode(['ok'=>true,'imported'=>$imported]);
