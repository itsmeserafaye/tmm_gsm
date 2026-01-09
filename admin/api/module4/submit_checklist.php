<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Inspector','Admin']);
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'invalid_method']); exit; }
$schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
$overall = trim($_POST['overall_status'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
if ($schedule_id <= 0 && $plate === '') { echo json_encode(['ok'=>false,'error'=>'missing_ref']); exit; }
if ($schedule_id <= 0) {
  $stmtS = $db->prepare("SELECT schedule_id FROM inspection_schedules WHERE plate_number=? AND status IN ('Scheduled','Pending Verification') ORDER BY scheduled_at DESC LIMIT 1");
  $stmtS->bind_param('s', $plate);
  $stmtS->execute();
  $resS = $stmtS->get_result();
  if ($row = $resS->fetch_assoc()) { $schedule_id = (int)$row['schedule_id']; } else { echo json_encode(['ok'=>false,'error'=>'schedule_not_found']); exit; }
}
$stmtR = $db->prepare("INSERT INTO inspection_results (schedule_id, overall_status, remarks) VALUES (?, ?, ?)");
$ov = $overall !== '' ? $overall : 'Pending';
$stmtR->bind_param('iss', $schedule_id, $ov, $remarks);
if (!$stmtR->execute()) { echo json_encode(['ok'=>false,'error'=>'db_error']); exit; }
$result_id = $db->insert_id;
$items = [
  ['LIGHTS','Lights & Horn', trim($_POST['item_LIGHTS'] ?? 'NA')],
  ['BRAKES','Brakes', trim($_POST['item_BRAKES'] ?? 'NA')],
  ['EMISSION','Emission & Smoke Test', trim($_POST['item_EMISSION'] ?? 'NA')],
  ['TIRES','Tires & Wipers', trim($_POST['item_TIRES'] ?? 'NA')],
  ['INTERIOR','Interior Safety', trim($_POST['item_INTERIOR'] ?? 'NA')],
  ['DOCS','Documents & Plate', trim($_POST['item_DOCS'] ?? 'NA')],
];
foreach ($items as $it) {
  $stmtI = $db->prepare("INSERT INTO inspection_checklist_items (result_id, item_code, item_label, status) VALUES (?, ?, ?, ?)");
  $stmtI->bind_param('isss', $result_id, $it[0], $it[1], $it[2]);
  $stmtI->execute();
}
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  $uploads_dir = __DIR__ . '/../../uploads';
  if (!is_dir($uploads_dir)) { mkdir($uploads_dir, 0777, true); }
  $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
  if (in_array($ext, ['jpg','jpeg','png'])) {
    $filename = 'INS-' . $schedule_id . '-' . time() . '.' . $ext;
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
      $stmtP = $db->prepare("INSERT INTO inspection_photos (result_id, file_path) VALUES (?, ?)");
      $stmtP->bind_param('is', $result_id, $filename);
      $stmtP->execute();
    }
  }
}
$finalStatus = $ov;
if ($overall === '') {
  $passAll = true;
  foreach ($items as $it) { if ($it[2] === 'Fail') { $passAll = false; break; } }
  $finalStatus = $passAll ? 'Passed' : 'Failed';
}
$db->query("UPDATE inspection_schedules SET status='Completed' WHERE schedule_id=" . (int)$schedule_id);
$certNo = null;
if ($finalStatus === 'Passed') {
  $tmp = 'TMP-' . uniqid();
  $stmtC = $db->prepare("INSERT INTO inspection_certificates (certificate_number, schedule_id, status) VALUES (?, ?, 'Issued')");
  $stmtC->bind_param('si', $tmp, $schedule_id);
  if ($stmtC->execute()) {
    $cid = $db->insert_id;
    $certNo = 'CERT-' . date('Y') . '-' . str_pad((string)$cid, 4, '0', STR_PAD_LEFT);
    $vehCol = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles'");
    $haveStatus = false; $haveDate = false; $haveRef = false;
    if ($vehCol) {
      while ($c = $vehCol->fetch_assoc()) {
        if (($c['COLUMN_NAME'] ?? '') === 'inspection_status') $haveStatus = true;
        if (($c['COLUMN_NAME'] ?? '') === 'inspection_last_date') $haveDate = true;
        if (($c['COLUMN_NAME'] ?? '') === 'inspection_cert_ref') $haveRef = true;
      }
    }
    $stmtSV = $db->prepare("SELECT plate_number, inspector_id FROM inspection_schedules WHERE schedule_id=?");
    $stmtSV->bind_param('i', $schedule_id);
    $stmtSV->execute();
    $schedRow = $stmtSV->get_result()->fetch_assoc();
    if ($schedRow) {
      $pn = $schedRow['plate_number'];
      $approvedBy = (int)($schedRow['inspector_id'] ?? 0);
      $qrRef = 'QR-' . substr(sha1($certNo . '|' . $pn), 0, 16);
      $stmtUC = $db->prepare("UPDATE inspection_certificates SET certificate_number=?, approved_by=NULLIF(?,0), qr_ref=? WHERE cert_id=?");
      $stmtUC->bind_param('sisi', $certNo, $approvedBy, $qrRef, $cid);
      $stmtUC->execute();
      if ($haveStatus) { $db->query("UPDATE vehicles SET inspection_status='Passed' WHERE plate_number='" . $db->real_escape_string($pn) . "'"); }
      if ($haveDate) { $db->query("UPDATE vehicles SET inspection_last_date=NOW() WHERE plate_number='" . $db->real_escape_string($pn) . "'"); }
      if ($haveRef) { $db->query("UPDATE vehicles SET inspection_cert_ref='" . $db->real_escape_string($certNo) . "' WHERE plate_number='" . $db->real_escape_string($pn) . "'"); }
    }
  }
}
echo json_encode(['ok'=>true,'result_id'=>$result_id,'overall_status'=>$finalStatus,'certificate_number'=>$certNo]);
