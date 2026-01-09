<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');
$plate = trim($_POST['plate_number'] ?? '');
$route = trim($_POST['route_id'] ?? '');
$terminal = trim($_POST['terminal_name'] ?? '');
$status = trim($_POST['status'] ?? 'Authorized');
if ($plate === '' || $route === '' || $terminal === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$vstmt = $db->prepare("SELECT plate_number, status, franchise_id, inspection_status FROM vehicles WHERE plate_number=?");
$vstmt->bind_param('s', $plate);
$vstmt->execute();
$veh = $vstmt->get_result()->fetch_assoc();
if (!$veh) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
if (($veh['status'] ?? '') === 'Suspended' || ($veh['status'] ?? '') === 'Deactivated') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'vehicle_inactive']); exit; }
if (strtoupper($veh['inspection_status'] ?? '') !== 'PASSED') { http_response_code(412); echo json_encode(['ok'=>false,'error'=>'inspection_required']); exit; }
$fr = trim($veh['franchise_id'] ?? '');
if ($fr === '') { http_response_code(412); echo json_encode(['ok'=>false,'error'=>'franchise_missing']); exit; }
$fstmt = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
$fstmt->bind_param('s', $fr);
$fstmt->execute();
$frow = $fstmt->get_result()->fetch_assoc();
if (!$frow || ($frow['status'] ?? '') !== 'Endorsed') { http_response_code(412); echo json_encode(['ok'=>false,'error'=>'franchise_not_endorsed']); exit; }
$rstmt = $db->prepare("SELECT max_vehicle_limit FROM routes WHERE route_id=?");
$rstmt->bind_param('s', $route);
$rstmt->execute();
$r = $rstmt->get_result()->fetch_assoc();
if (!$r) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'route_not_found']); exit; }
$staticCap = (int)($r['max_vehicle_limit'] ?? 0);
$limit = $staticCap > 0 ? $staticCap : -1; // -1 means unlimited

$dstmt = $db->prepare("SELECT cap FROM route_cap_schedule WHERE route_id=? AND ts<=NOW() ORDER BY ts DESC LIMIT 1");
$dstmt->bind_param('s', $route);
$dstmt->execute();
$drow = $dstmt->get_result()->fetch_assoc();

if ($drow) {
  $dcap = isset($drow['cap']) ? (int)$drow['cap'] : -1;
  if ($dcap >= 0) {
    if ($limit === -1 || $dcap < $limit) {
      $limit = $dcap;
    }
  }
}

$cstmt = $db->prepare("SELECT COUNT(*) AS c FROM terminal_assignments WHERE route_id=?");
$cstmt->bind_param('s', $route);
$cstmt->execute();
$cnt = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);

if ($limit !== -1 && $cnt >= $limit) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'route_capacity_reached','capacity'=>$limit,'assigned'=>$cnt]); exit; }
$upd = $db->prepare("UPDATE vehicles SET route_id=? WHERE plate_number=?");
$upd->bind_param('ss', $route, $plate);
$upd->execute();
$ins = $db->prepare("INSERT INTO terminal_assignments(plate_number, route_id, terminal_name, status) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), terminal_name=VALUES(terminal_name), status=VALUES(status)");
$ins->bind_param('ssss', $plate, $route, $terminal, $status);
$ok = $ins->execute();
echo json_encode(['ok'=>$ok, 'plate_number'=>$plate, 'route_id'=>$route, 'terminal_name'=>$terminal, 'status'=>$status]);
?> 
