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
  // Enforce mandatory permit document on create
  $uploadErr = $_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($uploadErr !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Permit/MOA document is required when creating a terminal or parking area.']);
    exit;
  }
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
// Optional: handle permit upload
try {
  if (isset($_FILES['permit_file']) && is_array($_FILES['permit_file']) && ($_FILES['permit_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['permit_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
      http_response_code(400);
      echo json_encode(['success' => false, 'ok' => false, 'message' => 'Invalid permit file type. Allowed: PDF, JPG, PNG.']);
      exit;
    } else {
      $uploads_dir = __DIR__ . '/../../uploads';
      if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);
      $fname = 'terminal_' . $terminalId . '_permit_' . time() . '.' . $ext;
      $dest = rtrim($uploads_dir, '/\\') . DIRECTORY_SEPARATOR . $fname;
      if (move_uploaded_file($_FILES['permit_file']['tmp_name'], $dest)) {
        if (tmm_scan_file_for_viruses($dest)) {
          // Insert into terminal_permits if table exists
          $chk = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits' LIMIT 1");
          if ($chk && $chk->fetch_row()) {
            $cols = [];
            $types = [];
            $colRes = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='terminal_permits'");
            if ($colRes) {
              while ($r = $colRes->fetch_assoc()) {
                $cn = (string)($r['COLUMN_NAME'] ?? '');
                $ct = (string)($r['COLUMN_TYPE'] ?? '');
                if ($cn !== '') $cols[$cn] = true;
                if ($cn !== '') $types[$cn] = $ct;
              }
            }
            $tidCol = isset($cols['terminal_id']) ? 'terminal_id' : '';
            $pathCol = isset($cols['file_path']) ? 'file_path' : (isset($cols['document_path']) ? 'document_path' : (isset($cols['doc_path']) ? 'doc_path' : (isset($cols['path']) ? 'path' : '')));
            if ($tidCol !== '' && $pathCol !== '') {
              $extraCols = [];
              $extraTypes = '';
              $extraBind = [];
              $docTypeCol = isset($cols['doc_type']) ? 'doc_type' : (isset($cols['document_type']) ? 'document_type' : (isset($cols['type']) ? 'type' : ''));
              if ($docTypeCol !== '') {
                $extraCols[] = $docTypeCol;
                $extraTypes .= 's';
                // Attempt to honor enum values if present
                $dtMeta = (string)($types[$docTypeCol] ?? '');
                $val = 'MOA';
                if ($dtMeta !== '' && stripos($dtMeta, 'enum(') === 0) {
                  if (preg_match_all("/'([^']*)'/", $dtMeta, $m) && !empty($m[1])) {
                    $match = null;
                    foreach ($m[1] as $ev) {
                      if (strcasecmp($ev, 'MOA') === 0) { $match = $ev; break; }
                    }
                    if ($match === null) $match = $m[1][0];
                    $val = $match;
                  }
                }
                $extraBind[] = $val;
              }
              if (isset($cols['status'])) {
                $extraCols[] = 'status';
                $extraTypes .= 's';
                $extraBind[] = 'Pending';
              }
              $placeholders = '?,?';
              if ($extraCols) {
                $placeholders .= ',' . implode(',', array_fill(0, count($extraCols), '?'));
              }
              $sqlIns = "INSERT INTO terminal_permits ($tidCol, $pathCol" . ($extraCols ? (", " . implode(", ", $extraCols)) : "") . ") VALUES ($placeholders)";
              $stmtP = $db->prepare($sqlIns);
              if ($stmtP) {
                $bindTypes = 'is' . $extraTypes;
                $bind = array_merge([$terminalId, $fname], $extraBind);
                $stmtP->bind_param($bindTypes, ...$bind);
                $stmtP->execute();
                $stmtP->close();
              }
            }
          }
        } else {
          @unlink($dest);
        }
      }
    }
  }
} catch (Throwable $e) {
  if ($terminalPk <= 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'message' => 'Failed to store permit document.']);
    exit;
  }
}
echo json_encode(['success' => true, 'ok' => true, 'message' => 'Terminal saved', 'terminal_id' => $terminalId, 'id' => $terminalId]);
?>
