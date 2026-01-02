<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$scheduled_at = trim($_POST['scheduled_at'] ?? '');
$location = trim($_POST['location'] ?? '');
$inspector_id = (int)($_POST['inspector_id'] ?? 0);
if ($plate === '' || $scheduled_at === '' || $location === '' || $inspector_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$ts = strtotime($scheduled_at);
if ($ts === false) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid_datetime']); exit; }
if ($ts <= time()+300) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'must_be_future']); exit; }
$chk = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
if (!$exists) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$oi = $db->prepare("SELECT active_status FROM officers WHERE officer_id=?");
$oi->bind_param('i', $inspector_id);
$oi->execute();
$o = $oi->get_result()->fetch_assoc();
if (!$o || (int)$o['active_status'] !== 1) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'inspector_inactive']); exit; }
$dup = $db->prepare("SELECT schedule_id FROM inspection_schedules WHERE plate_number=? AND status IN ('Scheduled','Pending Verification') AND scheduled_at>=NOW()");
$dup->bind_param('s', $plate);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'existing_schedule']); exit; }
$crv = 0; $orv = 0;
$dc = $db->prepare("SELECT type FROM documents WHERE plate_number=? AND verified=1");
$dc->bind_param('s', $plate);
$dc->execute();
$dr = $dc->get_result();
while ($row = $dr->fetch_assoc()) { if (($row['type'] ?? '') === 'cr') $crv = 1; if (($row['type'] ?? '') === 'or') $orv = 1; }
$stmt = $db->prepare("INSERT INTO inspection_schedules(plate_number, scheduled_at, location, inspector_id, status, cr_verified, or_verified) VALUES(?,?,?,?,?,?,?)");
$status = ($crv && $orv) ? 'Scheduled' : 'Pending Verification';
$stmt->bind_param('ssssiii', $plate, $scheduled_at, $location, $inspector_id, $status, $crv, $orv);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['ok'=>false,'error'=>'insert_failed']); exit; }
$id = $db->insert_id;
echo json_encode(['ok'=>true,'schedule_id'=>$id,'plate_number'=>$plate,'scheduled_at'=>$scheduled_at,'location'=>$location,'inspector_id'=>$inspector_id,'status'=>$status]);
?> 
