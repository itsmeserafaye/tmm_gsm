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

// If attempting to endorse, ensure the linked cooperative has an LGU approval number.
if ($status === 'Endorsed') {
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
$stmt->bind_param('si', $status, $id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['error'=>'update_failed']); exit; }
if ($status === 'Endorsed') {
  $stmt2 = $db->prepare("INSERT INTO endorsement_records (application_id, issued_date, permit_number)
                         VALUES (?, CURDATE(), ?)
                         ON DUPLICATE KEY UPDATE issued_date=issued_date, permit_number=IF(permit_number IS NULL OR permit_number='', VALUES(permit_number), permit_number)");
  if ($stmt2) {
    $stmt2->bind_param('is', $id, $permit);
    $stmt2->execute();
    $stmt2->close();
  }
}
echo json_encode(['ok'=>true, 'application_id'=>$id, 'status'=>$status, 'permit_number'=>$permit]);
?> 
