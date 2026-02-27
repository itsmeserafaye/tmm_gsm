<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
header('Content-Type: application/json');
require_permission('module5.manage_terminal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$terminalPk = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$name = trim((string)($_POST['name'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'Terminal'));
$capacity = (int)($_POST['capacity'] ?? 0);
$category = trim((string)($_POST['category'] ?? ''));

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

$locationFinal = $location !== '' ? $location : ($address !== '' ? $address : null);
$addressFinal = $address !== '' ? $address : ($location !== '' ? $location : null);
$typeFinal = $type !== '' ? $type : 'Terminal';

$cityFinal = $city !== '' ? $city : null;
if (strcasecmp($typeFinal, 'Terminal') === 0) {
  if ($cityFinal === null || $cityFinal === '') {
    $cityFinal = 'Caloocan City';
  }
  $c = strtolower(trim((string)$cityFinal));
  if ($c === 'caloocan') $cityFinal = 'Caloocan City';
  if (strtolower(trim((string)$cityFinal)) !== 'caloocan city') {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Only Caloocan City terminals are allowed']);
    exit;
  }
}

$hasCity = false;
$colRes = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals' AND COLUMN_NAME='city' LIMIT 1");
if ($colRes && $colRes->fetch_row()) $hasCity = true;
$hasCategory = false;
$colRes2 = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals' AND COLUMN_NAME='category' LIMIT 1");
if ($colRes2 && $colRes2->fetch_row()) $hasCategory = true;

$categoryFinal = $category !== '' ? $category : null;

if ($terminalPk > 0) {
  $chk = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
  if (!$chk) {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'db_prepare_failed']);
    exit;
  }
  $chk->bind_param('i', $terminalPk);
  $chk->execute();
  $exists = $chk->get_result()->fetch_row();
  $chk->close();
  if (!$exists) {
    http_response_code(404);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'not_found']);
    exit;
  }

  if ($hasCity && $hasCategory) {
    $stmt = $db->prepare("UPDATE terminals SET name=?, location=?, city=?, address=?, type=?, capacity=?, category=? WHERE id=?");
  } elseif ($hasCity) {
    $stmt = $db->prepare("UPDATE terminals SET name=?, location=?, city=?, address=?, type=?, capacity=? WHERE id=?");
  } elseif ($hasCategory) {
    $stmt = $db->prepare("UPDATE terminals SET name=?, location=?, address=?, type=?, capacity=?, category=? WHERE id=?");
  } else {
    $stmt = $db->prepare("UPDATE terminals SET name=?, location=?, address=?, type=?, capacity=? WHERE id=?");
  }
} else {
  if ($hasCity && $hasCategory) {
    $stmt = $db->prepare("INSERT INTO terminals (name, location, city, address, type, capacity, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
  } elseif ($hasCity) {
    $stmt = $db->prepare("INSERT INTO terminals (name, location, city, address, type, capacity) VALUES (?, ?, ?, ?, ?, ?)");
  } elseif ($hasCategory) {
    $stmt = $db->prepare("INSERT INTO terminals (name, location, address, type, capacity, category) VALUES (?, ?, ?, ?, ?, ?)");
  } else {
    $stmt = $db->prepare("INSERT INTO terminals (name, location, address, type, capacity) VALUES (?, ?, ?, ?, ?)");
  }
}
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'ok' => false, 'message' => 'db_prepare_failed']);
  exit;
}
if ($terminalPk > 0) {
  if ($hasCity && $hasCategory) {
    $stmt->bind_param('sssssisi', $name, $locationFinal, $cityFinal, $addressFinal, $typeFinal, $capacity, $categoryFinal, $terminalPk);
  } elseif ($hasCity) {
    $stmt->bind_param('sssssii', $name, $locationFinal, $cityFinal, $addressFinal, $typeFinal, $capacity, $terminalPk);
  } elseif ($hasCategory) {
    $stmt->bind_param('ssssisi', $name, $locationFinal, $addressFinal, $typeFinal, $capacity, $categoryFinal, $terminalPk);
  } else {
    $stmt->bind_param('ssssii', $name, $locationFinal, $addressFinal, $typeFinal, $capacity, $terminalPk);
  }
} else {
  if ($hasCity && $hasCategory) {
    $stmt->bind_param('sssssis', $name, $locationFinal, $cityFinal, $addressFinal, $typeFinal, $capacity, $categoryFinal);
  } elseif ($hasCity) {
    $stmt->bind_param('sssssi', $name, $locationFinal, $cityFinal, $addressFinal, $typeFinal, $capacity);
  } elseif ($hasCategory) {
    $stmt->bind_param('ssssis', $name, $locationFinal, $addressFinal, $typeFinal, $capacity, $categoryFinal);
  } else {
    $stmt->bind_param('ssssi', $name, $locationFinal, $addressFinal, $typeFinal, $capacity);
  }
}
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'ok' => false, 'message' => 'db_error']);
  exit;
}
$terminalId = $terminalPk > 0 ? $terminalPk : (int)$stmt->insert_id;

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

$hasOwners = tmm_table_exists($db, 'facility_owners');
$hasAgreements = tmm_table_exists($db, 'facility_agreements');
$hasDocs = tmm_table_exists($db, 'facility_documents');

$ownerName = trim((string)($_POST['owner_name'] ?? ''));
$ownerType = trim((string)($_POST['owner_type'] ?? 'Other'));
$ownerContact = trim((string)($_POST['owner_contact'] ?? ''));
$agreeId = isset($_POST['agreement_id']) ? (int)$_POST['agreement_id'] : 0;
$agreeType = trim((string)($_POST['agreement_type'] ?? 'MOA'));
$refNo = trim((string)($_POST['agreement_reference_no'] ?? ''));
$rentAmtRaw = (string)($_POST['rent_amount'] ?? '');
$rentAmt = $rentAmtRaw === '' ? null : (float)$rentAmtRaw;
$rentFreq = trim((string)($_POST['rent_frequency'] ?? 'Monthly'));
$terms = trim((string)($_POST['terms_summary'] ?? ''));
$startDate = trim((string)($_POST['start_date'] ?? ''));
$endDate = trim((string)($_POST['end_date'] ?? ''));
$status = trim((string)($_POST['agreement_status'] ?? 'Active'));

$hasAnyFile =
  (isset($_FILES['moa_file']) && ($_FILES['moa_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['contract_file']) && ($_FILES['contract_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['permit_file']) && ($_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ||
  (isset($_FILES['other_attachments']['name']) && is_array($_FILES['other_attachments']['name']) && count(array_filter($_FILES['other_attachments']['name'])) > 0);

$hasAgreementInputs =
  $ownerName !== '' ||
  $agreeId > 0 ||
  $startDate !== '' ||
  $endDate !== '' ||
  $refNo !== '' ||
  $terms !== '' ||
  $rentAmtRaw !== '' ||
  $hasAnyFile;

$hasContractDetails =
  $agreeId > 0 ||
  $startDate !== '' ||
  $endDate !== '' ||
  $refNo !== '' ||
  $terms !== '' ||
  $rentAmtRaw !== '' ||
  $status !== 'Active';

if ($hasAgreementInputs) {
  if (!$hasOwners || !$hasAgreements) {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Agreements tables are missing in database.']);
    exit;
  }
  if ($ownerName === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Owner name is required.']);
    exit;
  }
  if ($rentAmt !== null && $rentAmt < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Rent amount must be >= 0.']);
    exit;
  }
  if ($hasContractDetails) {
    if ($startDate === '' || $endDate === '') {
      http_response_code(400);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Start date and end date are required when agreement details are provided.']);
      exit;
    }
    if (strtotime($endDate) < strtotime($startDate)) {
      http_response_code(400);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'End date must be on or after start date.']);
      exit;
    }
  }
}

$ownerId = 0;
if ($hasAgreementInputs && $hasOwners) {
  $stmtO = $db->prepare("SELECT id FROM facility_owners WHERE name=? LIMIT 1");
  if ($stmtO) {
    $stmtO->bind_param('s', $ownerName);
    $stmtO->execute();
    $rowO = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
    if ($rowO && isset($rowO['id'])) $ownerId = (int)$rowO['id'];
  }
  if ($ownerId > 0) {
    $stmtUpd = $db->prepare("UPDATE facility_owners SET type=?, contact_info=? WHERE id=?");
    if ($stmtUpd) {
      $stmtUpd->bind_param('ssi', $ownerType, $ownerContact, $ownerId);
      $stmtUpd->execute();
      $stmtUpd->close();
    }
  } else {
    $stmtIns = $db->prepare("INSERT INTO facility_owners (name, type, contact_info) VALUES (?, ?, ?)");
    if ($stmtIns) {
      $stmtIns->bind_param('sss', $ownerName, $ownerType, $ownerContact);
      if (!$stmtIns->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to save owner.']);
        exit;
      }
      $ownerId = (int)$stmtIns->insert_id;
      $stmtIns->close();
    }
  }
}

if ($hasAgreementInputs && $hasContractDetails && $hasAgreements && $ownerId > 0) {
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

  if ($tidCol === '' || $oidCol === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Agreements table schema is missing required columns.']);
    exit;
  }

  $sDate = $startDate !== '' ? $startDate : null;
  $eDate = $endDate !== '' ? $endDate : null;
  $rentAmtFinal = $rentAmt === null ? 0.0 : $rentAmt;

  if ($agreeId > 0) {
    $sets = [];
    $types = '';
    $bind = [];

    $sets[] = "$oidCol=?";
    $types .= 'i';
    $bind[] = $ownerId;

    if ($typeCol !== '') { $sets[] = "$typeCol=?"; $types .= 's'; $bind[] = $agreeType; }
    if ($refCol !== '') { $sets[] = "$refCol=?"; $types .= 's'; $bind[] = $refNo; }
    if ($rentCol !== '') { $sets[] = "$rentCol=?"; $types .= 'd'; $bind[] = $rentAmtFinal; }
    if ($freqCol !== '') { $sets[] = "$freqCol=?"; $types .= 's'; $bind[] = $rentFreq; }
    if ($termsCol !== '') { $sets[] = "$termsCol=?"; $types .= 's'; $bind[] = $terms; }
    if ($startCol !== '') { $sets[] = "$startCol=?"; $types .= 's'; $bind[] = $sDate; }
    if ($endCol !== '') { $sets[] = "$endCol=?"; $types .= 's'; $bind[] = $eDate; }
    if ($statusCol !== '') { $sets[] = "$statusCol=?"; $types .= 's'; $bind[] = $status; }

    $types .= 'i';
    $bind[] = $agreeId;

    $sql = "UPDATE facility_agreements SET " . implode(', ', $sets) . " WHERE id=?";
    $stmtA = $db->prepare($sql);
    if (!$stmtA) {
      http_response_code(500);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to prepare agreement update.']);
      exit;
    }
    $stmtA->bind_param($types, ...$bind);
    if (!$stmtA->execute()) {
      http_response_code(500);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to update agreement.']);
      exit;
    }
    $stmtA->close();
  } else {
    $cols = [$tidCol, $oidCol];
    $placeholders = ['?', '?'];
    $types = 'ii';
    $bind = [$terminalId, $ownerId];

    if ($typeCol !== '') { $cols[] = $typeCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $agreeType; }
    if ($refCol !== '') { $cols[] = $refCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $refNo; }
    if ($rentCol !== '') { $cols[] = $rentCol; $placeholders[] = '?'; $types .= 'd'; $bind[] = $rentAmtFinal; }
    if ($freqCol !== '') { $cols[] = $freqCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $rentFreq; }
    if ($termsCol !== '') { $cols[] = $termsCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $terms; }
    if ($startCol !== '') { $cols[] = $startCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $sDate; }
    if ($endCol !== '') { $cols[] = $endCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $eDate; }
    if ($statusCol !== '') { $cols[] = $statusCol; $placeholders[] = '?'; $types .= 's'; $bind[] = $status; }

    $sql = "INSERT INTO facility_agreements (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmtA = $db->prepare($sql);
    if (!$stmtA) {
      http_response_code(500);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to prepare agreement insert.']);
      exit;
    }
    $stmtA->bind_param($types, ...$bind);
    if (!$stmtA->execute()) {
      http_response_code(500);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to save agreement.']);
      exit;
    }
    $agreeId = (int)$stmtA->insert_id;
    $stmtA->close();
  }
}

if ($hasAnyFile && !$hasDocs) {
  http_response_code(500);
  echo json_encode(['success' => false, 'ok' => false, 'message' => 'Documents table is missing in database.']);
  exit;
}

if ($hasAnyFile && $hasDocs) {
  $dCols = tmm_table_columns($db, 'facility_documents');
  $tidCol = isset($dCols['terminal_id']) ? 'terminal_id' : (isset($dCols['facility_id']) ? 'facility_id' : '');
  $aidCol = isset($dCols['agreement_id']) ? 'agreement_id' : '';
  $typeCol = isset($dCols['doc_type']) ? 'doc_type' : (isset($dCols['type']) ? 'type' : '');
  $pathCol = isset($dCols['file_path']) ? 'file_path' : (isset($dCols['path']) ? 'path' : (isset($dCols['document_path']) ? 'document_path' : ''));
  if ($tidCol === '' || $typeCol === '' || $pathCol === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Documents table schema is missing required columns.']);
    exit;
  }

  $uploadsDir = __DIR__ . '/../../uploads';
  if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0777, true);

  $insertDoc = function(string $docType, string $fileName) use ($db, $dCols, $tidCol, $aidCol, $typeCol, $pathCol, $terminalId, $agreeId) {
    $cols = [$tidCol, $typeCol, $pathCol];
    $placeholders = ['?', '?', '?'];
    $types = 'iss';
    $bind = [$terminalId, $docType, $fileName];
    if ($aidCol !== '') {
      $cols[] = $aidCol;
      $placeholders[] = '?';
      $types .= 'i';
      $bind[] = $agreeId > 0 ? $agreeId : null;
    }
    $sql = "INSERT INTO facility_documents (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmtD = $db->prepare($sql);
    if (!$stmtD) return false;
    $stmtD->bind_param($types, ...$bind);
    $ok = $stmtD->execute();
    $stmtD->close();
    return $ok;
  };

  $saveFile = function(string $fileKey, string $docType) use ($uploadsDir, $insertDoc) {
    if (!isset($_FILES[$fileKey]) || ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return true;
    $ext = strtolower(pathinfo((string)($_FILES[$fileKey]['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) return false;
    $fname = 'term_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
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
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Invalid or failed MOA upload.']);
    exit;
  }
  if (!$saveFile('contract_file', 'Contract')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Invalid or failed contract upload.']);
    exit;
  }
  if (!$saveFile('permit_file', 'Terminal Permit')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Invalid or failed permit upload.']);
    exit;
  }

  if (isset($_FILES['other_attachments']['name']) && is_array($_FILES['other_attachments']['name'])) {
    $cnt = count($_FILES['other_attachments']['name']);
    for ($i = 0; $i < $cnt; $i++) {
      if (($_FILES['other_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $ext = strtolower(pathinfo((string)($_FILES['other_attachments']['name'][$i] ?? ''), PATHINFO_EXTENSION));
      if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'ok' => false, 'message' => 'Invalid file type in other attachments.']);
        exit;
      }
      $fname = 'term_other_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
      $dest = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
      if (!move_uploaded_file((string)$_FILES['other_attachments']['tmp_name'][$i], $dest)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to upload other attachment.']);
        exit;
      }
      if (function_exists('tmm_scan_file_for_viruses') && !tmm_scan_file_for_viruses($dest)) {
        @unlink($dest);
        http_response_code(400);
        echo json_encode(['success' => false, 'ok' => false, 'message' => 'File rejected by scanner.']);
        exit;
      }
      if (!$insertDoc('Other', $fname)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to save attachment record.']);
        exit;
      }
    }
  }
}

echo json_encode(['success' => true, 'ok' => true, 'message' => 'Saved successfully', 'terminal_id' => $terminalId, 'id' => $terminalId, 'agreement_id' => $agreeId]);
?>
