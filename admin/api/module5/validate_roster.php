<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin','Encoder','Inspector']);
header('Content-Type: application/json');

$platesCsv = trim($_POST['plates'] ?? ($_GET['plates'] ?? ''));
if ($platesCsv === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_plates']);
  exit;
}
$plates = array_values(array_filter(array_map(function($p){ return strtoupper(trim($p)); }, explode(',', $platesCsv)), function($p){ return $p !== ''; }));
if (empty($plates)) {
  echo json_encode(['ok'=>false,'error'=>'no_valid_plates']);
  exit;
}

$results = [];
foreach ($plates as $plate) {
  $valid = true;
  $reasons = [];

  $stmtV = $db->prepare("SELECT plate_number, status, franchise_id, inspection_status FROM vehicles WHERE plate_number=?");
  $stmtV->bind_param('s', $plate);
  $stmtV->execute();
  $veh = $stmtV->get_result()->fetch_assoc();
  if (!$veh) { $valid = false; $reasons[] = 'vehicle_not_found'; }
  else {
    if (($veh['status'] ?? '') === 'Suspended' || ($veh['status'] ?? '') === 'Deactivated') { $valid = false; $reasons[] = 'vehicle_inactive'; }
    if (strtoupper($veh['inspection_status'] ?? '') !== 'PASSED') { $valid = false; $reasons[] = 'inspection_required'; }
    $fr = trim($veh['franchise_id'] ?? '');
    if ($fr === '') { $valid = false; $reasons[] = 'franchise_missing'; }
    else {
      $stmtF = $db->prepare("SELECT status FROM franchise_applications WHERE franchise_ref_number=?");
      $stmtF->bind_param('s', $fr);
      $stmtF->execute();
      $frow = $stmtF->get_result()->fetch_assoc();
      if (!$frow || ($frow['status'] ?? '') !== 'Endorsed') { $valid = false; $reasons[] = 'franchise_not_endorsed'; }
    }
  }

  $results[] = ['plate_number'=>$plate, 'valid'=>$valid, 'reasons'=>$reasons];
}

echo json_encode(['ok'=>true, 'results'=>$results]);
?> 
