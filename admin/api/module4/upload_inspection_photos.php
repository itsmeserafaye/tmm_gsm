<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
header('Content-Type: application/json');
require_permission('module4.inspections.manage');

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_schedule']);
  exit;
}
if (!isset($_FILES['photos'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_files']);
  exit;
}
$sch = $db->prepare("SELECT plate_number FROM inspection_schedules WHERE schedule_id=?");
if (!$sch) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$sch->bind_param('i', $scheduleId);
$sch->execute();
$srow = $sch->get_result()->fetch_assoc();
if (!$srow) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'schedule_not_found']);
  exit;
}

$rs = $db->prepare("SELECT result_id FROM inspection_results WHERE schedule_id=? ORDER BY submitted_at DESC LIMIT 1");
if (!$rs) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$rs->bind_param('i', $scheduleId);
$rs->execute();
$rrow = $rs->get_result()->fetch_assoc();
$resultId = (int)($rrow['result_id'] ?? 0);
if ($resultId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_result']);
  exit;
}

$uploadDir = __DIR__ . '/../../uploads/inspection/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

$allowedExt = ['jpg','jpeg','png'];
$uploaded = [];
$errors = [];

$files = $_FILES['photos'];
$count = is_array($files['name'] ?? null) ? count($files['name']) : 0;
for ($i = 0; $i < $count; $i++) {
  $err = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
  if ($err !== UPLOAD_ERR_OK) {
    continue;
  }
  $tmp = (string)($files['tmp_name'][$i] ?? '');
  $orig = (string)($files['name'][$i] ?? '');
  if ($tmp === '' || $orig === '') {
    continue;
  }
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    $errors[] = $orig . ': invalid_type';
    continue;
  }
  $filename = 'SCH' . $scheduleId . '_R' . $resultId . '_' . time() . '_' . $i . '.' . $ext;
  $dest = $uploadDir . $filename;
  if (!move_uploaded_file($tmp, $dest)) {
    $errors[] = $orig . ': upload_failed';
    continue;
  }
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) { @unlink($dest); }
    $errors[] = $orig . ': failed_scan';
    continue;
  }
  $dbPath = 'inspection/' . $filename;
  $ins = $db->prepare("INSERT INTO inspection_photos (result_id, file_path) VALUES (?, ?)");
  if ($ins) {
    $ins->bind_param('is', $resultId, $dbPath);
    if ($ins->execute()) {
      $uploaded[] = $dbPath;
    } else {
      if (is_file($dest)) { @unlink($dest); }
      $errors[] = $orig . ': db_insert_failed';
    }
  } else {
    if (is_file($dest)) { @unlink($dest); }
    $errors[] = $orig . ': db_prepare_failed';
  }
}

if (!$uploaded && !$errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_files']);
  exit;
}

echo json_encode(['ok' => !$errors, 'uploaded' => $uploaded, 'errors' => $errors]);
?>

