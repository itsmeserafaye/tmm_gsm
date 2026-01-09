<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false,'error'=>'invalid_method']);
  exit;
}

$terminalName = trim($_POST['terminal_name'] ?? '');
$vehicle = strtoupper(trim($_POST['vehicle_plate'] ?? ''));
$action = trim($_POST['action'] ?? 'Arrival');
$remarks = trim($_POST['remarks'] ?? '');
if ($terminalName === '' || $vehicle === '' || ($action !== 'Arrival' && $action !== 'Departure' && $action !== 'Dispatch')) {
  echo json_encode(['ok'=>false,'error'=>'missing_fields']);
  exit;
}

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

$stmtA = $db->prepare("SELECT status, terminal_name FROM terminal_assignments WHERE plate_number=?");
$stmtA->bind_param('s', $vehicle);
$stmtA->execute();
$assign = $stmtA->get_result()->fetch_assoc();
if (!$assign || ($assign['status'] ?? '') !== 'Authorized' || strcasecmp($assign['terminal_name'] ?? '', $terminalName) !== 0) {
  echo json_encode(['ok'=>false,'error'=>'not_authorized']);
  exit;
}

$stmtVS = $db->prepare("SELECT status FROM vehicles WHERE plate_number=?");
$stmtVS->bind_param('s', $vehicle);
$stmtVS->execute();
$vs = $stmtVS->get_result()->fetch_assoc();
if ($vs && (($vs['status'] ?? '') === 'Suspended' || ($vs['status'] ?? '') === 'Deactivated')) {
  echo json_encode(['ok'=>false,'error'=>'vehicle_suspended']);
  exit;
}
$stmtCS = $db->prepare("SELECT compliance_status FROM compliance_summary WHERE vehicle_plate=?");
$stmtCS->bind_param('s', $vehicle);
$stmtCS->execute();
$cs = $stmtCS->get_result()->fetch_assoc();
if ($cs && (($cs['compliance_status'] ?? '') === 'Suspended')) {
  echo json_encode(['ok'=>false,'error'=>'vehicle_suspended']);
  exit;
}

$stmtVF = $db->prepare("SELECT franchise_id FROM vehicles WHERE plate_number=?");
$stmtVF->bind_param('s', $vehicle);
$stmtVF->execute();
$vf = $stmtVF->get_result()->fetch_assoc();
if ($vf && !empty($vf['franchise_id'])) {
  $fr = $vf['franchise_id'];
  $stmtFS = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
  $stmtFS->bind_param('s', $fr);
  $stmtFS->execute();
  $frow = $stmtFS->get_result()->fetch_assoc();
  if ($frow && ($frow['status'] ?? '') === 'Suspended') {
    echo json_encode(['ok'=>false,'error'=>'franchise_suspended']);
    exit;
  }
}

$opId = null;
$stmtV = $db->prepare("SELECT operator_name FROM vehicles WHERE plate_number=?");
$stmtV->bind_param('s', $vehicle);
$stmtV->execute();
$v = $stmtV->get_result()->fetch_assoc();
if ($v && !empty($v['operator_name'])) {
  $stmtO = $db->prepare("SELECT id FROM operators WHERE full_name=?");
  $stmtO->bind_param('s', $v['operator_name']);
  $stmtO->execute();
  $o = $stmtO->get_result()->fetch_assoc();
  if ($o) { $opId = (int)$o['id']; }
}

if ($action === 'Arrival') {
  $stmtL = $db->prepare("INSERT INTO terminal_logs(terminal_id, vehicle_plate, operator_id, time_in, activity_type, remarks) VALUES (?, ?, ?, NOW(), 'Arrival', ?)");
  $opParam = $opId !== null ? $opId : null;
  if ($opParam === null) {
    $stmtL->bind_param('isss', $terminalId, $vehicle, $opParam, $remarks);
  } else {
    $stmtL->bind_param('isis', $terminalId, $vehicle, $opParam, $remarks);
  }
  $ok = $stmtL->execute();
  echo json_encode(['ok'=>$ok, 'log_id'=>$ok ? $db->insert_id : null]);
  exit;
}

if ($action === 'Departure') {
  $stmtFind = $db->prepare("SELECT log_id FROM terminal_logs WHERE terminal_id=? AND vehicle_plate=? AND time_out IS NULL ORDER BY time_in DESC LIMIT 1");
  $stmtFind->bind_param('is', $terminalId, $vehicle);
  $stmtFind->execute();
  $open = $stmtFind->get_result()->fetch_assoc();
  if (!$open) { echo json_encode(['ok'=>false,'error'=>'no_open_arrival']); exit; }
  $lid = (int)$open['log_id'];
  $stmtU = $db->prepare("UPDATE terminal_logs SET time_out=NOW(), remarks=? WHERE log_id=?");
  $stmtU->bind_param('si', $remarks, $lid);
  $ok = $stmtU->execute();
  echo json_encode(['ok'=>$ok, 'log_id'=>$lid]);
  exit;
}

if ($action === 'Dispatch') {
  $stmtL2 = $db->prepare("INSERT INTO terminal_logs(terminal_id, vehicle_plate, operator_id, time_in, activity_type, remarks) VALUES (?, ?, ?, NOW(), 'Dispatch', ?)");
  $opParam2 = $opId !== null ? $opId : null;
  if ($opParam2 === null) {
    $stmtL2->bind_param('isss', $terminalId, $vehicle, $opParam2, $remarks);
  } else {
    $stmtL2->bind_param('isis', $terminalId, $vehicle, $opParam2, $remarks);
  }
  $ok2 = $stmtL2->execute();
  echo json_encode(['ok'=>$ok2, 'log_id'=>$ok2 ? $db->insert_id : null]);
  exit;
}
?> 
