<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');
$plate = strtoupper(trim($_POST['plate_number'] ?? ''));
if ($plate === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'plate_required']); exit; }
$veh = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$veh->bind_param('s', $plate);
$veh->execute();
$vrow = $veh->get_result()->fetch_assoc();
if (!$vrow) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'vehicle_not_found']); exit; }
$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) { @mkdir($uploads_dir, 0777, true); }
$uploaded = [];
$errors = [];
$hasAnyInput = (isset($_FILES['cr']) && $_FILES['cr']['error'] !== UPLOAD_ERR_NO_FILE) || (isset($_FILES['or']) && $_FILES['or']['error'] !== UPLOAD_ERR_NO_FILE);
if (!$hasAnyInput) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no_files']); exit; }
foreach (['cr','or'] as $type) {
  if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES[$type]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'])) { $errors[] = "$type: invalid_type"; continue; }
    if (($_FILES[$type]['size'] ?? 0) > 10485760) { $errors[] = "$type: too_large"; continue; }
    $filename = $plate . '_' . $type . '_' . time() . '.' . $ext;
    $dest = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($_FILES[$type]['tmp_name'], $dest)) {
      $uploaded[] = $filename;
      $stmt = $db->prepare("INSERT INTO documents (plate_number, type, file_path, verified) VALUES (?,?,?,1)");
      $stmt->bind_param('sss', $plate, $type, $filename);
      $stmt->execute();
    } else { $errors[] = "$type: move_failed"; }
  }
}
$hasCR = false; $hasOR = false;
if (!empty($uploaded)) {
  $chk = $db->prepare("SELECT type FROM documents WHERE plate_number=? AND verified=1");
  $chk->bind_param('s', $plate);
  $chk->execute();
  $res = $chk->get_result();
  while ($row = $res->fetch_assoc()) {
    if (($row['type'] ?? '') === 'cr') $hasCR = true;
    if (($row['type'] ?? '') === 'or') $hasOR = true;
  }
  $upd = $db->prepare("UPDATE inspection_schedules SET cr_verified=?, or_verified=? WHERE plate_number=? AND status IN ('Scheduled','Pending Verification')");
  $cr = $hasCR ? 1 : 0; $or = $hasOR ? 1 : 0;
  $upd->bind_param('iis', $cr, $or, $plate);
  $upd->execute();
}
if (!empty($errors) && empty($uploaded)) { http_response_code(400); echo json_encode(['ok'=>false,'errors'=>$errors]); exit; }
echo json_encode(['ok'=>true,'files'=>$uploaded,'cr_verified'=>$hasCR,'or_verified'=>$hasOR]);
?> 
