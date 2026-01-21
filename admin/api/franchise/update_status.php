<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');
$id = (int)($_POST['application_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$permit = trim($_POST['permit_number'] ?? '');
if ($id <= 0 || $status === '') { echo json_encode(['error'=>'missing_fields']); exit; }

$st = $status;
$s = strtolower($status);
if ($s === 'endorsed') $st = 'LGU-Endorsed';
elseif ($s === 'approved') $st = 'LTFRB-Approved';

$allowed = ['Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','Rejected'];
if (!in_array($st, $allowed, true)) {
  echo json_encode(['ok' => false, 'error' => 'invalid_status']);
  exit;
}

if ($st === 'LTFRB-Approved' || $st === 'Approved') {
  $stmtA = $db->prepare("SELECT operator_id, vehicle_count FROM franchise_applications WHERE application_id=? LIMIT 1");
  if ($stmtA) {
    $stmtA->bind_param('i', $id);
    $stmtA->execute();
    $app = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
    $operatorId = (int)($app['operator_id'] ?? 0);
    $need = (int)($app['vehicle_count'] ?? 0);
    if ($need <= 0) $need = 1;
    if ($operatorId > 0) {
      $stmtVeh = $db->prepare("SELECT COUNT(DISTINCT v.id) AS c
                               FROM vehicles v
                               JOIN vehicle_documents vd ON vd.vehicle_id=v.id AND vd.doc_type='ORCR' AND COALESCE(vd.is_verified,0)=1
                               WHERE v.operator_id=? AND (COALESCE(v.record_status,'') <> 'Archived')");
      if ($stmtVeh) {
        $stmtVeh->bind_param('i', $operatorId);
        $stmtVeh->execute();
        $row = $stmtVeh->get_result()->fetch_assoc();
        $stmtVeh->close();
        $have = (int)($row['c'] ?? 0);
        if ($have < $need) {
          echo json_encode(['ok' => false, 'error' => 'orcr_required_for_approval', 'need' => $need, 'have' => $have]);
          exit;
        }
      }
    }
  }
}

// If attempting to endorse, ensure the linked cooperative has an LGU approval number.
if ($st === 'LGU-Endorsed' || $st === 'Endorsed') {
  $stmtCheck = $db->prepare("SELECT c.lgu_approval_number, c.coop_name FROM franchise_applications fa LEFT JOIN coops c ON fa.coop_id = c.id WHERE fa.application_id = ?");
  $stmtCheck->bind_param('i', $id);
  $stmtCheck->execute();
  $resCheck = $stmtCheck->get_result();
  $rowCheck = $resCheck ? $resCheck->fetch_assoc() : null;
  $lguNo = $rowCheck['lgu_approval_number'] ?? '';
  if ($lguNo === null) $lguNo = '';
  $lguNo = trim($lguNo);
  if ($lguNo === '') {
    $coopName = $rowCheck['coop_name'] ?? '';
    echo json_encode([
      'ok' => false,
      'error' => 'Cannot endorse application because the cooperative has no LGU approval number.',
      'error_code' => 'coop_missing_lgu_approval',
      'coop_name' => $coopName
    ]);
    exit;
  }
}

$stmt = $db->prepare("UPDATE franchise_applications SET status=? WHERE application_id=?");
$stmt->bind_param('si', $st, $id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['error'=>'update_failed']); exit; }
if ($st === 'LGU-Endorsed' || $st === 'Endorsed') {
  $stmt2 = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                         VALUES (?, CURDATE(), ?)
                         ON DUPLICATE KEY UPDATE issued_date=issued_date, permit_number=IF(permit_number IS NULL OR permit_number='', VALUES(permit_number), permit_number)");
  if ($stmt2) {
    $stmt2->bind_param('is', $id, $permit);
    $stmt2->execute();
    $stmt2->close();
  }
}
echo json_encode(['ok'=>true, 'application_id'=>$id, 'status'=>$st, 'permit_number'=>$permit]);
?> 
