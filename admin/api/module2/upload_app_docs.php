<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_permission('module2.franchises.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$appId = (int)($_POST['application_id'] ?? 0);
if ($appId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}

$check = $db->prepare("SELECT application_id FROM franchise_applications WHERE application_id=? LIMIT 1");
if (!$check) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$check->bind_param('i', $appId);
$check->execute();
$exists = (bool)$check->get_result()->fetch_row();
$check->close();
if (!$exists) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'application_not_found']);
  exit;
}

$uploadDir = __DIR__ . '/../../uploads/franchise/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}

$allowedExt = ['pdf','jpg','jpeg','png','xlsx','xls','csv'];
$uploaded = [];
$errors = [];

$map = [
  'doc_ltfrb' => 'ltfrb',
  'doc_coop' => 'coop',
  'doc_members' => 'members',
];

foreach ($map as $field => $type) {
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
  if ($tmp === '' || $orig === '') {
    $errors[] = $field . ': invalid_file';
    continue;
  }
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    $errors[] = $orig . ': invalid_type';
    continue;
  }
  $filename = 'APP' . $appId . '_' . $type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
  $dest = $uploadDir . $filename;
  if (!move_uploaded_file($tmp, $dest)) {
    $errors[] = $orig . ': upload_failed';
    continue;
  }
  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = $orig . ': failed_scan';
    continue;
  }
  $dbPath = 'franchise/' . $filename;
  $ins = $db->prepare("INSERT INTO documents (plate_number, type, file_path, uploaded_by, application_id) VALUES (NULL, ?, ?, 'admin', ?)");
  if (!$ins) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = $orig . ': db_prepare_failed';
    continue;
  }
  $ins->bind_param('ssi', $type, $dbPath, $appId);
  if ($ins->execute()) {
    $uploaded[] = ['type' => $type, 'file_path' => $dbPath];
  } else {
    if (is_file($dest)) @unlink($dest);
    $errors[] = $orig . ': db_insert_failed';
  }
  $ins->close();
}

if (!$uploaded && !$errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'no_files']);
  exit;
}

echo json_encode(['ok' => empty($errors), 'uploaded' => $uploaded, 'errors' => $errors]);

