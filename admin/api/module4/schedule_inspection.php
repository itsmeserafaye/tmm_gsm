<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder']);
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'invalid_method']); exit; }
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$dt = trim($_POST['scheduled_at'] ?? '');
$loc = trim($_POST['location'] ?? '');
$insp = isset($_POST['inspector_id']) ? (int)$_POST['inspector_id'] : null;
if ($plate === '' || $dt === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit; }
$stmtV = $db->prepare("SELECT status, franchise_id FROM vehicles WHERE plate_number=?");
$stmtV->bind_param('s', $plate);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
if (!$veh) { echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
if (($veh['status'] ?? '') === 'Suspended' || ($veh['status'] ?? '') === 'Deactivated') { echo json_encode(['ok'=>false,'error'=>'vehicle_inactive']); exit; }
$fr = trim($veh['franchise_id'] ?? '');
if ($fr === '') { echo json_encode(['ok'=>false,'error'=>'franchise_missing']); exit; }
$stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
$stmtF->bind_param('s', $fr);
$stmtF->execute();
$frow = $stmtF->get_result()->fetch_assoc();
if (!$frow || ($frow['status'] ?? '') !== 'Endorsed') { echo json_encode(['ok'=>false,'error'=>'franchise_not_endorsed']); exit; }
$stmtCR = $db->prepare("SELECT verified FROM documents WHERE plate_number=? AND type='cr' ORDER BY uploaded_at DESC LIMIT 1");
$stmtCR->bind_param('s', $plate);
$stmtCR->execute();
$rowCR = $stmtCR->get_result()->fetch_assoc();
$stmtOR = $db->prepare("SELECT verified FROM documents WHERE plate_number=? AND type='or' ORDER BY uploaded_at DESC LIMIT 1");
$stmtOR->bind_param('s', $plate);
$stmtOR->execute();
$rowOR = $stmtOR->get_result()->fetch_assoc();
$crv = (int)($rowCR['verified'] ?? 0);
$orv = (int)($rowOR['verified'] ?? 0);
if ($crv !== 1 || $orv !== 1) { echo json_encode(['ok'=>false,'error'=>'docs_unverified']); exit; }
if ($insp) {
  $stmtI = $db->prepare("SELECT officer_id FROM officers WHERE officer_id=? AND role LIKE '%Inspector%'");
  $stmtI->bind_param('i', $insp);
  $stmtI->execute();
  $irow = $stmtI->get_result()->fetch_assoc();
  if (!$irow) { echo json_encode(['ok'=>false,'error'=>'invalid_inspector']); exit; }
}
$stmt = $db->prepare("INSERT INTO inspection_schedules (plate_number, scheduled_at, location, inspector_id, status, cr_verified, or_verified) VALUES (?, ?, ?, NULLIF(?, 0), 'Scheduled', ?, ?)");
$stmt->bind_param('sssiii', $plate, $dt, $loc, $insp, $crv, $orv);
$ok = $stmt->execute();
if ($ok) {
  $sid = $db->insert_id;
  echo json_encode(['ok'=>true,'schedule_id'=>$sid,'plate_number'=>$plate,'scheduled_at'=>$dt,'location'=>$loc,'inspector_id'=>$insp,'cr_verified'=>$crv,'or_verified'=>$orv]);
} else {
  echo json_encode(['ok'=>false,'error'=>'db_error']);
}
