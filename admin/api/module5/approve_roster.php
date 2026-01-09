<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');

$platesCsv = trim($_POST['plates'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$terminalName = trim($_POST['terminal_name'] ?? '');
$status = trim($_POST['status'] ?? 'Authorized');

if ($platesCsv === '' || $route === '' || $terminalName === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}
$plates = array_values(array_filter(array_map(function($p){ return strtoupper(trim($p)); }, explode(',', $platesCsv)), function($p){ return $p !== ''; }));
if (empty($plates)) { echo json_encode(['ok'=>false,'error'=>'no_valid_plates']); exit; }

$stmtT = $db->prepare("SELECT id FROM terminals WHERE name=?");
$stmtT->bind_param('s', $terminalName);
$stmtT->execute();
$t = $stmtT->get_result()->fetch_assoc();
if (!$t) { echo json_encode(['ok'=>false,'error'=>'terminal_not_found']); exit; }
$terminalId = (int)$t['id'];

$stmtP = $db->prepare("SELECT id FROM terminal_permits WHERE terminal_id=? AND status='Active' AND payment_verified=1 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY created_at DESC LIMIT 1");
$stmtP->bind_param('i', $terminalId);
$stmtP->execute();
$permit = $stmtP->get_result()->fetch_assoc();
if (!$permit) { echo json_encode(['ok'=>false,'error'=>'terminal_permit_inactive']); exit; }

$rstmt = $db->prepare("SELECT max_vehicle_limit FROM routes WHERE route_id=?");
$rstmt->bind_param('s', $route);
$rstmt->execute();
$r = $rstmt->get_result()->fetch_assoc();
if (!$r) { echo json_encode(['ok'=>false,'error'=>'route_not_found']); exit; }
$staticCap = (int)($r['max_vehicle_limit'] ?? 0);
$limit = $staticCap > 0 ? $staticCap : -1;

$dstmt = $db->prepare("SELECT cap FROM route_cap_schedule WHERE route_id=? AND ts<=NOW() ORDER BY ts DESC LIMIT 1");
$dstmt->bind_param('s', $route);
$dstmt->execute();
$drow = $dstmt->get_result()->fetch_assoc();

if ($drow) {
  $dcap = isset($drow['cap']) ? (int)$drow['cap'] : -1;
  if ($dcap >= 0) {
    if ($limit === -1 || $dcap < $limit) { $limit = $dcap; }
  }
}

$results = [];
$cstmt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
$cstmt->bind_param('s', $route);
$cstmt->execute();
$cnt = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
foreach ($plates as $plate) {
  $errs = [];
  $vstmt = $db->prepare("SELECT plate_number, status, franchise_id, inspection_status FROM vehicles WHERE plate_number=?");
  $vstmt->bind_param('s', $plate);
  $vstmt->execute();
  $veh = $vstmt->get_result()->fetch_assoc();
  if (!$veh) { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'vehicle_not_found']; continue; }
  if (($veh['status'] ?? '') === 'Suspended' || ($veh['status'] ?? '') === 'Deactivated') { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'vehicle_inactive']; continue; }
  if (strtoupper($veh['inspection_status'] ?? '') !== 'PASSED') { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'inspection_required']; continue; }
  $fr = trim($veh['franchise_id'] ?? '');
  if ($fr === '') { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'franchise_missing']; continue; }
  $fstmt = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
  $fstmt->bind_param('s', $fr);
  $fstmt->execute();
  $frow = $fstmt->get_result()->fetch_assoc();
  if (!$frow || ($frow['status'] ?? '') !== 'Endorsed') { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'franchise_not_endorsed']; continue; }

  if ($limit !== -1 && $cnt >= $limit) { $results[] = ['plate_number'=>$plate,'ok'=>false,'error'=>'route_capacity_reached','capacity'=>$limit,'assigned'=>$cnt]; continue; }

  $upd = $db->prepare("UPDATE vehicles SET route_id=? WHERE plate_number=?");
  $upd->bind_param('ss', $route, $plate);
  $upd->execute();
  $ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, route_id, terminal_name, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
  $ins->bind_param('ssss', $plate, $route, $terminalName, $status);
  $ok = $ins->execute();
  if ($ok) { $cnt++; }
  $results[] = ['plate_number'=>$plate,'ok'=>$ok];
}

echo json_encode(['ok'=>true, 'results'=>$results, 'route_id'=>$route, 'terminal_name'=>$terminalName]);
?> 
