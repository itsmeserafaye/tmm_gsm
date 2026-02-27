<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
header('Content-Type: application/json');
require_permission('module5.manage_terminal');
$db = db();

// Auto-fix missing tables
$db->query("CREATE TABLE IF NOT EXISTS `facility_owners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT 'Person',
  `contact_info` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS `facility_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `agreement_type` varchar(50) DEFAULT 'MOA',
  `reference_no` varchar(100) DEFAULT NULL,
  `rent_amount` decimal(12,2) DEFAULT '0.00',
  `rent_frequency` varchar(50) DEFAULT 'Monthly',
  `status` varchar(50) DEFAULT 'Active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `terms_summary` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->query("CREATE TABLE IF NOT EXISTS `facility_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `terminal_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `agreement_id` int(11) DEFAULT NULL,
  `doc_type` varchar(50) DEFAULT 'Document',
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `terminal_id` (`terminal_id`),
  KEY `agreement_id` (`agreement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid method']);
  exit;
}

function tmm_table_exists(mysqli $db, string $table): bool {
  $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
  if (!$stmt) return false;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

function tmm_table_columns(mysqli $db, string $table): array {
  $cols = [];
  $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
  if (!$stmt) return $cols;
  $stmt->bind_param('s', $table);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $cn = (string)($row['COLUMN_NAME'] ?? '');
    if ($cn !== '') $cols[$cn] = true;
  }
  $stmt->close();
  return $cols;
}

$terminalId = isset($_POST['terminal_id']) ? (int)$_POST['terminal_id'] : 0;
$agreementId = isset($_POST['agreement_id']) ? (int)$_POST['agreement_id'] : 0;

if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid terminal_id']);
  exit;
}

$ownerName = trim((string)($_POST['owner_name'] ?? ''));
$ownerType = trim((string)($_POST['owner_type'] ?? 'Other'));
$ownerContact = trim((string)($_POST['owner_contact'] ?? ''));

$agreeType = trim((string)($_POST['agreement_type'] ?? 'MOA'));
$refNo = trim((string)($_POST['agreement_reference_no'] ?? ''));
$rentAmtRaw = (string)($_POST['rent_amount'] ?? '');
$rentAmt = $rentAmtRaw === '' ? 0.0 : (float)$rentAmtRaw;
$rentFreq = trim((string)($_POST['rent_frequency'] ?? 'Monthly'));
$terms = trim((string)($_POST['terms_summary'] ?? ''));
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$status = trim((string)($_POST['agreement_status'] ?? 'Active'));

if ($ownerName === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Owner name is required']);
  exit;
}
if ($rentAmt < 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Rent amount must be >= 0']);
  exit;
}
if ($startDate === '' || $endDate === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Start date and end date are required']);
  exit;
}
if (strtotime($endDate) < strtotime($startDate)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'End date must be on or after start date']);
  exit;
}

if (!tmm_table_exists($db, 'terminals')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Missing terminals table']);
  exit;
}

$chkT = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
if (!$chkT) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'db_prepare_failed']);
  exit;
}
$chkT->bind_param('i', $terminalId);
$chkT->execute();
$existsT = (bool)$chkT->get_result()->fetch_row();
$chkT->close();
if (!$existsT) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Terminal not found']);
  exit;
}

if (!tmm_table_exists($db, 'facility_owners') || !tmm_table_exists($db, 'facility_agreements')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Agreements tables are missing in database']);
  exit;
}

$ownerId = 0;
$stmtO = $db->prepare("SELECT id FROM facility_owners WHERE name=? LIMIT 1");
if ($stmtO) {
  $stmtO->bind_param('s', $ownerName);
  $stmtO->execute();
  $row = $stmtO->get_result()->fetch_assoc();
  $stmtO->close();
  if ($row && isset($row['id'])) $ownerId = (int)$row['id'];
}
if ($ownerId > 0) {
  $stmtU = $db->prepare("UPDATE facility_owners SET type=?, contact_info=? WHERE id=?");
  if ($stmtU) {
    $stmtU->bind_param('ssi', $ownerType, $ownerContact, $ownerId);
    $stmtU->execute();
    $stmtU->close();
  }
} else {
  $stmtI = $db->prepare("INSERT INTO facility_owners (name, type, contact_info) VALUES (?, ?, ?)");
  if (!$stmtI) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'db_prepare_failed']);
    exit;
  }
  $stmtI->bind_param('sss', $ownerName, $ownerType, $ownerContact);
  if (!$stmtI->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to save owner']);
    exit;
  }
  $ownerId = (int)$stmtI->insert_id;
  $stmtI->close();
}

$aCols = tmm_table_columns($db, 'facility_agreements');
$tidCol = isset($aCols['terminal_id']) ? 'terminal_id' : (isset($aCols['facility_id']) ? 'facility_id' : '');
$oidCol = isset($aCols['owner_id']) ? 'owner_id' : '';
$typeCol = isset($aCols['agreement_type']) ? 'agreement_type' : (isset($aCols['contract_type']) ? 'contract_type' : '');
$refCol = isset($aCols['reference_no']) ? 'reference_no' : (isset($aCols['reference_number']) ? 'reference_number' : (isset($aCols['contract_ref_no']) ? 'contract_ref_no' : ''));
$rentCol = isset($aCols['rent_amount']) ? 'rent_amount' : (isset($aCols['amount']) ? 'amount' : '');
$freqCol = isset($aCols['rent_frequency']) ? 'rent_frequency' : (isset($aCols['frequency']) ? 'frequency' : '');
$termsCol = isset($aCols['terms_summary']) ? 'terms_summary' : (isset($aCols['terms']) ? 'terms' : (isset($aCols['contract_terms']) ? 'contract_terms' : ''));
$statusCol = isset($aCols['status']) ? 'status' : '';
$startCol = '';
foreach (['start_date', 'contract_start', 'contract_start_date', 'start'] as $c) { if (isset($aCols[$c])) { $startCol = $c; break; } }
$endCol = '';
foreach (['end_date', 'contract_end', 'contract_end_date', 'end'] as $c) { if (isset($aCols[$c])) { $endCol = $c; break; } }

if ($tidCol === '' || $oidCol === '' || $startCol === '' || $endCol === '') {
  http_response_code(500);
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Agreements table schema is missing required columns']);
  exit;
}

if ($agreementId > 0) {
  $sets = [];
  $types = '';
  $bind = [];

  $sets[] = "$oidCol=?";
  $types .= 'i';
  $bind[] = $ownerId;
  if ($typeCol !== '') { $sets[] = "$typeCol=?"; $types .= 's'; $bind[] = $agreeType; }
  if ($refCol !== '') { $sets[] = "$refCol=?"; $types .= 's'; $bind[] = $refNo; }
  if ($rentCol !== '') { $sets[] = "$rentCol=?"; $types .= 'd'; $bind[] = $rentAmt; }
  if ($freqCol !== '') { $sets[] = "$freqCol=?"; $types .= 's'; $bind[] = $rentFreq; }
  if ($termsCol !== '') { $sets[] = "$termsCol=?"; $types .= 's'; $bind[] = $terms; }
  $sets[] = "$startCol=?"; $types .= 's'; $bind[] = $startDate;
  $sets[] = "$endCol=?"; $types .= 's'; $bind[] = $endDate;
  if ($statusCol !== '') { $sets[] = "$statusCol=?"; $types .= 's'; $bind[] = $status; }

  $types .= 'i';
  $bind[] = $agreementId;

  $sql = "UPDATE facility_agreements SET " . implode(', ', $sets) . " WHERE id=?";
  $stmtA = $db->prepare($sql);
  if (!$stmtA) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to prepare agreement update']);
    exit;
  }
  $stmtA->bind_param($types, ...$bind);
  if (!$stmtA->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to update agreement']);
    exit;
  }
  $stmtA->close();
} else {
  $cols = [$tidCol, $oidCol, $startCol, $endCol];
  $placeholders = ['?', '?', '?', '?'];
  $types = 'iiss';
  $bind = [$terminalId, $ownerId, $startDate, $endDate];

  if ($typeCol !== '') { $cols[] = $typeCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $agreeType; }
  if ($refCol !== '') { $cols[] = $refCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $refNo; }
  if ($rentCol !== '') { $cols[] = $rentCol; $placeholders[] = '?'; $types .= 'd'; $bind[] = $rentAmt; }
  if ($freqCol !== '') { $cols[] = $freqCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $rentFreq; }
  if ($termsCol !== '') { $cols[] = $termsCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $terms; }
  if ($statusCol !== '') { $cols[] = $statusCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $status; }

  $sql = "INSERT INTO facility_agreements (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
  $stmtA = $db->prepare($sql);
  if (!$stmtA) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to prepare agreement insert']);
    exit;
  }
  $stmtA->bind_param($types, ...$bind);
  if (!$stmtA->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to save agreement']);
    exit;
  }
  $agreementId = (int)$stmtA->insert_id;
  $stmtA->close();
}

$hasAnyFile =
  (isset($_FILES['moa_file']) && ($_FILES['moa_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['contract_file']) && ($_FILES['contract_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['permit_file']) && ($_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['other_attachments']['name']) && is_array($_FILES['other_attachments']['name']) && count(array_filter($_FILES['other_attachments']['name'])) > 0);

if ($hasAnyFile) {
  if (!tmm_table_exists($db, 'facility_documents')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Documents table is missing in database']);
    exit;
  }
  $dCols = tmm_table_columns($db, 'facility_documents');
  $dTidCol = isset($dCols['terminal_id']) ? 'terminal_id' : (isset($dCols['facility_id']) ? 'facility_id' : '');
  $dAidCol = isset($dCols['agreement_id']) ? 'agreement_id' : '';
  $dTypeCol = isset($dCols['doc_type']) ? 'doc_type' : (isset($dCols['type']) ? 'type' : '');
  $dPathCol = isset($dCols['file_path']) ? 'file_path' : (isset($dCols['path']) ? 'path' : (isset($dCols['document_path']) ? 'document_path' : ''));
  if ($dTidCol === '' || $dTypeCol === '' || $dPathCol === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Documents table schema is missing required columns']);
    exit;
  }

  $uploadsDir = __DIR__ . '/../../uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $insertDoc = function(string $docType, string $fileName) use ($db, $dTidCol, $dAidCol, $dTypeCol, $dPathCol, $terminalId, $agreementId) {
    $cols = [$dTidCol, $dTypeCol, $dPathCol];
    $placeholders = ['?', '?', '?'];
    $types = 'iss';
    $bind = [$terminalId, $docType, $fileName];
    if ($dAidCol !== '') {
      $cols[] = $dAidCol;
      $placeholders[] = '?';
      $types .= 'i';
      $bind[] = $agreementId;
    }
    $sql = "INSERT INTO facility_documents (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$bind);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
  };

  $saveFile = function(string $fileKey, string $docType) use ($uploadsDir, $insertDoc) {
    if (!isset($_FILES[$fileKey]) || ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return true;
    $ext = strtolower(pathinfo((string)($_FILES[$fileKey]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) return false;
    $fname = 'doc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file((string)$_FILES[$fileKey]['tmp_name'], $dest)) return false;
    if (function_exists('tmm_scan_file_for_viruses') && !tmm_scan_file_for_viruses($dest)) {
      @unlink($dest);
      return false;
    }
    return $insertDoc($docType, $fname);
  };

  if (!$saveFile('moa_file', 'MOA')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid or failed MOA upload']);
    exit;
  }
  if (!$saveFile('contract_file', 'Contract')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid or failed contract upload']);
    exit;
  }
  if (!$saveFile('permit_file', 'Terminal Permit')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid or failed permit upload']);
    exit;
  }

  if (isset($_FILES['other_attachments']['name']) && is_array($_FILES['other_attachments']['name'])) {
    $cnt = count($_FILES['other_attachments']['name']);
    for ($i = 0; $i < $cnt; $i++) {
      if (($_FILES['other_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $ext = strtolower(pathinfo((string)($_FILES['other_attachments']['name'][$i] ?? ''), PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'success' => false, 'message' => 'Invalid file type in other attachments']);
        exit;
      }
      $fname = 'doc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
      $dest = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
      if (!move_uploaded_file((string)$_FILES['other_attachments']['tmp_name'][$i], $dest)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to upload other attachment']);
        exit;
      }
      if (function_exists('tmm_scan_file_for_viruses') && !tmm_scan_file_for_viruses($dest)) {
        @unlink($dest);
        http_response_code(400);
        echo json_encode(['ok' => false, 'success' => false, 'message' => 'File rejected by scanner']);
        exit;
      }
      if (!$insertDoc('Other', $fname)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'success' => false, 'message' => 'Failed to save attachment record']);
        exit;
      }
    }
  }
}

echo json_encode(['ok' => true, 'success' => true, 'message' => 'Details saved', 'terminal_id' => $terminalId, 'agreement_id' => $agreementId]);
?>

