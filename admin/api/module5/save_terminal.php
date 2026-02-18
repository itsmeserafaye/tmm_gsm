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

// ----------------------------------------------------------------------------
// Schema Migration (Lazy)
// ----------------------------------------------------------------------------
try {
  $db->query("CREATE TABLE IF NOT EXISTS terminal_permits (
      id INT AUTO_INCREMENT PRIMARY KEY,
      terminal_id INT NOT NULL,
      file_path VARCHAR(255),
      doc_type VARCHAR(50),
      status VARCHAR(50),
      issue_date DATE,
      expiry_date DATE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (terminal_id)
  )");
  // Try to add columns if missing (suppress errors if they exist, but IF NOT EXISTS is standard in MySQL 8 / MariaDB 10)
  // For older versions, this might fail, so we wrap in try-catch or just ignore error
  $db->query("ALTER TABLE terminals ADD COLUMN IF NOT EXISTS owner VARCHAR(100) DEFAULT NULL");
  $db->query("ALTER TABLE terminals ADD COLUMN IF NOT EXISTS operator VARCHAR(100) DEFAULT NULL");
} catch (Throwable $e) {
  // Ignore schema errors, assume mostly correct
}

// ----------------------------------------------------------------------------
// Input Capture
// ----------------------------------------------------------------------------
$terminalPk = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));
$location = trim((string)($_POST['location'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'Terminal'));
$capacity = (int)($_POST['capacity'] ?? 0);
$category = trim((string)($_POST['category'] ?? ''));
$owner = trim((string)($_POST['owner'] ?? ''));
$operator = trim((string)($_POST['operator'] ?? ''));

// Permit Metadata
$permitStatus = trim((string)($_POST['permit_status'] ?? 'Pending'));
$agreementType = trim((string)($_POST['agreement_type'] ?? 'MOA'));
$validFrom = trim((string)($_POST['valid_from'] ?? ''));
$validTo = trim((string)($_POST['valid_to'] ?? ''));

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

$locationFinal = $location !== '' ? $location : ($address !== '' ? $address : null);
$addressFinal = $address !== '' ? $address : ($location !== '' ? $location : null);
$typeFinal = $type !== '' ? $type : 'Terminal';
$cityFinal = $city !== '' ? $city : null;
$ownerFinal = $owner !== '' ? $owner : null;
$operatorFinal = $operator !== '' ? $operator : null;

// Validation for Caloocan
if (strcasecmp($typeFinal, 'Terminal') === 0) {
  if ($cityFinal === null || $cityFinal === '') $cityFinal = 'Caloocan City';
  $c = strtolower(trim((string)$cityFinal));
  if ($c === 'caloocan') $cityFinal = 'Caloocan City';
  if (strtolower(trim((string)$cityFinal)) !== 'caloocan city') {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Only Caloocan City terminals are allowed']);
    exit;
  }
}

// ----------------------------------------------------------------------------
// Column Discovery
// ----------------------------------------------------------------------------
$hasCity = false;
$hasCategory = false;
$hasOwner = false;
$hasOperator = false;

$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminals'");
if ($colRes) {
  while ($r = $colRes->fetch_assoc()) {
    $cn = (string)($r['COLUMN_NAME'] ?? '');
    if ($cn === 'city') $hasCity = true;
    if ($cn === 'category') $hasCategory = true;
    if ($cn === 'owner') $hasOwner = true;
    if ($cn === 'operator') $hasOperator = true;
  }
}

// ----------------------------------------------------------------------------
// Save Terminal
// ----------------------------------------------------------------------------
if ($terminalPk > 0) {
  // Check existence
  $chk = $db->prepare("SELECT id FROM terminals WHERE id=? LIMIT 1");
  if (!$chk) { http_response_code(500); echo json_encode(['success'=>false, 'message'=>'db_prepare_failed']); exit; }
  $chk->bind_param('i', $terminalPk);
  $chk->execute();
  if (!$chk->get_result()->fetch_row()) { http_response_code(404); echo json_encode(['success'=>false, 'message'=>'not_found']); exit; }
  $chk->close();

  // Build Update
  $setParts = ["name=?", "location=?", "address=?", "type=?", "capacity=?"];
  $params = [$name, $locationFinal, $addressFinal, $typeFinal, $capacity];
  $types = "ssssi";

  if ($hasCity) { $setParts[] = "city=?"; $params[] = $cityFinal; $types .= "s"; }
  if ($hasCategory) { $setParts[] = "category=?"; $params[] = $categoryFinal; $types .= "s"; }
  if ($hasOwner) { $setParts[] = "owner=?"; $params[] = $ownerFinal; $types .= "s"; }
  if ($hasOperator) { $setParts[] = "operator=?"; $params[] = $operatorFinal; $types .= "s"; }

  $params[] = $terminalPk;
  $types .= "i";
  
  $sql = "UPDATE terminals SET " . implode(", ", $setParts) . " WHERE id=?";
  $stmt = $db->prepare($sql);
} else {
  // Create
  // Enforce mandatory permit document on create
  $uploadErr = $_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($uploadErr !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Permit/MOA document is required when creating a terminal or parking area.']);
    exit;
  }

  $cols = ["name", "location", "address", "type", "capacity"];
  $vals = ["?", "?", "?", "?", "?"];
  $params = [$name, $locationFinal, $addressFinal, $typeFinal, $capacity];
  $types = "ssssi";

  if ($hasCity) { $cols[] = "city"; $vals[] = "?"; $params[] = $cityFinal; $types .= "s"; }
  if ($hasCategory) { $cols[] = "category"; $vals[] = "?"; $params[] = $categoryFinal; $types .= "s"; }
  if ($hasOwner) { $cols[] = "owner"; $vals[] = "?"; $params[] = $ownerFinal; $types .= "s"; }
  if ($hasOperator) { $cols[] = "operator"; $vals[] = "?"; $params[] = $operatorFinal; $types .= "s"; }

  $sql = "INSERT INTO terminals (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
  $stmt = $db->prepare($sql);
}

if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'db_prepare_failed']);
  exit;
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'db_error: ' . $stmt->error]);
  exit;
}

$terminalId = $terminalPk > 0 ? $terminalPk : (int)$stmt->insert_id;

// ----------------------------------------------------------------------------
// Handle Permit Upload
// ----------------------------------------------------------------------------
try {
  if (isset($_FILES['permit_file']) && is_array($_FILES['permit_file']) && ($_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['permit_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
      if ($terminalPk <= 0) { // If create failed due to invalid file, we should technically rollback or warn
         http_response_code(400);
         echo json_encode(['success' => false, 'message' => 'Invalid permit file type. Allowed: PDF, JPG, PNG.']);
         exit;
      }
    } else {
      $uploads_dir = __DIR__ . '/../../uploads';
      if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);
      $fname = 'terminal_' . $terminalId . '_permit_' . time() . '.' . $ext;
      $dest = rtrim($uploads_dir, '/\\') . DIRECTORY_SEPARATOR . $fname;
      if (move_uploaded_file($_FILES['permit_file']['tmp_name'], $dest)) {
        if (tmm_scan_file_for_viruses($dest)) {
          // Insert into terminal_permits
          // Discovery columns first
          $permCols = [];
          $colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
          if ($colRes) while ($r = $colRes->fetch_assoc()) $permCols[(string)$r['COLUMN_NAME']] = true;

          $tidCol = isset($permCols['terminal_id']) ? 'terminal_id' : '';
          $pathCol = isset($permCols['file_path']) ? 'file_path' : (isset($permCols['document_path']) ? 'document_path' : '');
          
          if ($tidCol && $pathCol) {
             $pCols = [$tidCol, $pathCol];
             $pVals = ["?", "?"];
             $pParams = [$terminalId, $fname];
             $pTypes = "is";

             $docTypeCol = isset($permCols['doc_type']) ? 'doc_type' : (isset($permCols['document_type']) ? 'document_type' : '');
             if ($docTypeCol) {
                 $pCols[] = $docTypeCol; $pVals[] = "?"; $pParams[] = $agreementType; $pTypes .= "s";
             }
             if (isset($permCols['status'])) {
                 $pCols[] = 'status'; $pVals[] = "?"; $pParams[] = $permitStatus; $pTypes .= "s";
             }
             if (isset($permCols['issue_date']) && $validFrom !== '') {
                 $pCols[] = 'issue_date'; $pVals[] = "?"; $pParams[] = $validFrom; $pTypes .= "s";
             }
             if (isset($permCols['expiry_date']) && $validTo !== '') {
                 $pCols[] = 'expiry_date'; $pVals[] = "?"; $pParams[] = $validTo; $pTypes .= "s";
             }

             $sqlP = "INSERT INTO terminal_permits (" . implode(", ", $pCols) . ") VALUES (" . implode(", ", $pVals) . ")";
             $stmtP = $db->prepare($sqlP);
             if ($stmtP) {
                 $stmtP->bind_param($pTypes, ...$pParams);
                 $stmtP->execute();
                 $stmtP->close();
             }
          }
        } else {
          @unlink($dest);
        }
      }
    }
  }
} catch (Throwable $e) {
  // If it was a create, we might want to report error, but terminal is created.
}

echo json_encode(['success' => true, 'ok' => true, 'message' => 'Saved successfully', 'id' => $terminalId]);
?>
