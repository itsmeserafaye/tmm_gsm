<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.vehicles.write', 'module1.write']);

$operatorId = isset($_POST['operator_id']) ? (int)$_POST['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$stmtO = $db->prepare("SELECT id, name, full_name FROM operators WHERE id=? LIMIT 1");
if (!$stmtO) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtO->bind_param('i', $operatorId);
$stmtO->execute();
$op = $stmtO->get_result()->fetch_assoc();
$stmtO->close();
if (!$op) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'operator_not_found']);
  exit;
}

$nameSlugBase = trim((string)($op['name'] ?? ''));
if ($nameSlugBase === '') $nameSlugBase = trim((string)($op['full_name'] ?? ''));
if ($nameSlugBase === '') $nameSlugBase = 'operator_' . $operatorId;
$nameSlug = preg_replace('/[^a-z0-9]+/i', '_', $nameSlugBase);
$nameSlug = trim((string)$nameSlug, '_');
if ($nameSlug === '') $nameSlug = 'operator_' . $operatorId;

$uploadsDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadsDir)) {
  mkdir($uploadsDir, 0777, true);
}

$uploaded = [];
$errors = [];

$fields = [
  'gov_id' => ['type' => 'GovID', 'label' => 'Valid Government ID'],
  'proof_address' => ['type' => 'BarangayCert', 'label' => 'Proof of Address'],
  'cda_registration' => ['type' => 'CDA', 'label' => 'CDA Registration Certificate'],
  'cda_good_standing' => ['type' => 'CDA', 'label' => 'CDA Certificate of Good Standing'],
  'sec_certificate' => ['type' => 'SEC', 'label' => 'SEC Certificate of Registration'],
  'corp_articles_bylaws' => ['type' => 'SEC', 'label' => 'Articles of Incorporation / By-laws'],
  'board_resolution' => ['type' => 'Others', 'label' => 'Board Resolution'],
  'nbi_clearance' => ['type' => 'Others', 'label' => 'NBI Clearance'],
  'authorization_letter' => ['type' => 'Others', 'label' => 'Authorization Letter'],
  'members_list' => ['type' => 'Others', 'label' => 'List of Members'],
  'coop_articles_bylaws' => ['type' => 'Others', 'label' => 'Articles of Cooperation / By-laws'],
  'mayors_permit' => ['type' => 'Others', 'label' => "Mayor's Permit"],
  'business_permit' => ['type' => 'Others', 'label' => 'Business Permit'],

  'id_doc' => ['type' => 'GovID', 'label' => null],
  'cda_doc' => ['type' => 'CDA', 'label' => null],
  'sec_doc' => ['type' => 'SEC', 'label' => null],
  'barangay_doc' => ['type' => 'BarangayCert', 'label' => null],
  'others_doc' => ['type' => 'Others', 'label' => null],
];

foreach ($fields as $field => $cfg) {
  $docType = (string)($cfg['type'] ?? '');
  $label = isset($cfg['label']) ? $cfg['label'] : null;
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) continue;
  $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
    $errors[] = "$field: invalid_file_type";
    continue;
  }

  $fieldSlug = preg_replace('/[^a-z0-9]+/i', '_', (string)$field);
  $fieldSlug = trim((string)$fieldSlug, '_');
  if ($fieldSlug === '') $fieldSlug = 'doc';
  $filename = $nameSlug . '_' . strtolower($docType) . '_' . $fieldSlug . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
  $dest = $uploadsDir . '/' . $filename;
  if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
    $errors[] = "$field: move_failed";
    continue;
  }

  $safe = tmm_scan_file_for_viruses($dest);
  if (!$safe) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: security_scan_failed";
    continue;
  }

  $stmt = $db->prepare("INSERT INTO operator_documents (operator_id, doc_type, file_path, doc_status, remarks, is_verified) VALUES (?, ?, ?, 'Pending', ?, 0)");
  if (!$stmt) {
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: db_prepare_failed";
    continue;
  }
  $stmt->bind_param('isss', $operatorId, $docType, $filename, $label);
  if (!$stmt->execute()) {
    $stmt->close();
    if (is_file($dest)) @unlink($dest);
    $errors[] = "$field: db_insert_failed";
    continue;
  }
  $stmt->close();

  $uploaded[] = $filename;
}

if ($errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'upload_failed', 'details' => $errors, 'files' => $uploaded]);
  exit;
}

if ($uploaded) {
  $stmtS = $db->prepare("UPDATE operators SET workflow_status=CASE WHEN workflow_status='Draft' THEN 'Pending Validation' ELSE workflow_status END WHERE id=? AND workflow_status<>'Inactive'");
  if ($stmtS) {
    $stmtS->bind_param('i', $operatorId);
    $stmtS->execute();
    $stmtS->close();
  }
}

echo json_encode(['ok' => true, 'operator_id' => $operatorId, 'files' => $uploaded]);
