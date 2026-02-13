<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module4.inspect','module4.certify','module4.inspections.manage']);

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_schedule']);
  exit;
}

$sch = $db->prepare("SELECT plate_number, vehicle_id FROM inspection_schedules WHERE schedule_id=?");
if (!$sch) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$sch->bind_param('i', $scheduleId);
$sch->execute();
$srow = $sch->get_result()->fetch_assoc();
$sch->close();
if (!$srow) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}

$plate = trim((string)($srow['plate_number'] ?? ''));
$vehicleId = (int)($srow['vehicle_id'] ?? 0);
if ($vehicleId <= 0 && $plate !== '') {
  $stmtV = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtV) {
    $stmtV->bind_param('s', $plate);
    $stmtV->execute();
    $vr = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
    $vehicleId = (int)($vr['id'] ?? 0);
  }
}

$db->query("CREATE TABLE IF NOT EXISTS inspection_documents (
  doc_id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  doc_code VARCHAR(32) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_schedule_doc (schedule_id, doc_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$uploadDir = __DIR__ . '/../../uploads/inspection_docs/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

$map = [
  'doc_or' => 'DOC_OR',
  'doc_cr' => 'DOC_CR',
  'doc_cmvi' => 'DOC_CMVI',
  'doc_ctpl' => 'DOC_CTPL',
];

$allowedExt = ['jpg','jpeg','png','pdf'];
$uploaded = [];
$errors = [];

$vehUploadsDir = __DIR__ . '/../../uploads/';
if (!is_dir($vehUploadsDir)) {
  @mkdir($vehUploadsDir, 0777, true);
}
$vehMap = [
  'DOC_OR' => 'OR',
  'DOC_CR' => 'CR',
  'DOC_CMVI' => 'Emission',
  'DOC_CTPL' => 'Insurance',
];

foreach ($map as $field => $code) {
  if (!isset($_FILES[$field])) continue;
  $f = $_FILES[$field];
  $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err === UPLOAD_ERR_NO_FILE) continue;
  if ($err !== UPLOAD_ERR_OK) {
    $errors[] = $field . ': upload_error';
    continue;
  }
  $tmp = (string)($f['tmp_name'] ?? '');
  $orig = (string)($f['name'] ?? '');
  if ($tmp === '' || $orig === '') continue;
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    $errors[] = $field . ': invalid_type';
    continue;
  }

  $filename = 'SCH' . $scheduleId . '_' . $code . '_' . time() . '.' . $ext;
  $dest = $uploadDir . $filename;
  if (!move_uploaded_file($tmp, $dest)) {
    $errors[] = $field . ': upload_failed';
    continue;
  }
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = $field . ': failed_scan';
    continue;
  }

  $sel = $db->prepare("SELECT file_path FROM inspection_documents WHERE schedule_id=? AND doc_code=? LIMIT 1");
  $oldPath = '';
  if ($sel) {
    $sel->bind_param('is', $scheduleId, $code);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    $oldPath = trim((string)($row['file_path'] ?? ''));
  }

  $dbPath = 'inspection_docs/' . $filename;
  $stmt = $db->prepare("INSERT INTO inspection_documents (schedule_id, doc_code, file_path) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), uploaded_at=CURRENT_TIMESTAMP");
  if (!$stmt) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = $field . ': db_prepare_failed';
    continue;
  }
  $stmt->bind_param('iss', $scheduleId, $code, $dbPath);
  if (!$stmt->execute()) {
    $stmt->close();
    if (is_file($dest)) @unlink($dest);
    $errors[] = $field . ': db_insert_failed';
    continue;
  }
  $stmt->close();

  if ($oldPath !== '') {
    $oldFull = $uploadDir . basename($oldPath);
    if (is_file($oldFull)) @unlink($oldFull);
  }
  $uploaded[$code] = $dbPath;

  if ($vehicleId > 0 && isset($vehMap[$code])) {
    $vehType = (string)$vehMap[$code];
    $vehFilename = 'VEH' . $vehicleId . '_' . $vehType . '_' . time() . '.' . $ext;
    $vehDest = $vehUploadsDir . $vehFilename;
    @copy($dest, $vehDest);
    if (is_file($vehDest)) {
      $safeVeh = tmm_scan_file_for_viruses($vehDest);
      if (!$safeVeh) {
        @unlink($vehDest);
      } else {
        $stmtVd = $db->prepare("INSERT INTO vehicle_documents (vehicle_id, doc_type, file_path, uploaded_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
        if ($stmtVd) {
          $stmtVd->bind_param('iss', $vehicleId, $vehType, $vehFilename);
          $stmtVd->execute();
          $stmtVd->close();
        }
      }
    }
  }
}

if (!$uploaded && !$errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_files']);
  exit;
}

echo json_encode(['ok' => empty($errors), 'uploaded' => $uploaded, 'errors' => $errors]);
?>
