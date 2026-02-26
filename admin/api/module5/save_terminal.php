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

// --- Module 5 Enhancement: Owner & Agreement Logic ---
$ownerName = trim((string)($_POST['owner_name'] ?? ''));
$agreeId = isset($_POST['agreement_id']) ? (int)$_POST['agreement_id'] : 0;

if ($ownerName !== '') {
    // 1. Manage Owner
    $ownerType = trim((string)($_POST['owner_type'] ?? 'Other'));
    $ownerContact = trim((string)($_POST['owner_contact'] ?? ''));
    $ownerId = 0;

    // Check if owner exists by name
    $stmtO = $db->prepare("SELECT id FROM facility_owners WHERE name=? LIMIT 1");
    if ($stmtO) {
        $stmtO->bind_param('s', $ownerName);
        $stmtO->execute();
        $resO = $stmtO->get_result();
        if ($rowO = $resO->fetch_assoc()) {
            $ownerId = (int)$rowO['id'];
            // Update details
            $stmtUpd = $db->prepare("UPDATE facility_owners SET type=?, contact_info=? WHERE id=?");
            if ($stmtUpd) {
                $stmtUpd->bind_param('ssi', $ownerType, $ownerContact, $ownerId);
                $stmtUpd->execute();
            }
        } else {
            // Create new
            $stmtIns = $db->prepare("INSERT INTO facility_owners (name, type, contact_info) VALUES (?, ?, ?)");
            if ($stmtIns) {
                $stmtIns->bind_param('sss', $ownerName, $ownerType, $ownerContact);
                $stmtIns->execute();
                $ownerId = (int)$stmtIns->insert_id;
            }
        }
    }

    // 2. Manage Agreement
    if ($ownerId > 0) {
        $agreeType = trim((string)($_POST['agreement_type'] ?? 'MOA'));
        $refNo = trim((string)($_POST['agreement_reference_no'] ?? ''));
        $rentAmt = (float)($_POST['rent_amount'] ?? 0);
        $rentFreq = trim((string)($_POST['rent_frequency'] ?? 'Monthly'));
        $terms = trim((string)($_POST['terms_summary'] ?? ''));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $status = trim((string)($_POST['agreement_status'] ?? 'Active'));

        $sDate = $startDate !== '' ? $startDate : null;
        $eDate = $endDate !== '' ? $endDate : null;

        if ($agreeId > 0) {
            // Update existing agreement
            $stmtA = $db->prepare("UPDATE facility_agreements SET owner_id=?, agreement_type=?, reference_no=?, rent_amount=?, rent_frequency=?, terms_summary=?, start_date=?, end_date=?, status=? WHERE id=?");
            if ($stmtA) {
                $stmtA->bind_param('issdsssssi', $ownerId, $agreeType, $refNo, $rentAmt, $rentFreq, $terms, $sDate, $eDate, $status, $agreeId);
                $stmtA->execute();
            }
        } else {
            // Create new agreement
            $stmtA = $db->prepare("INSERT INTO facility_agreements (terminal_id, owner_id, agreement_type, reference_no, rent_amount, rent_frequency, terms_summary, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmtA) {
                $stmtA->bind_param('iissssssss', $terminalId, $ownerId, $agreeType, $refNo, $rentAmt, $rentFreq, $terms, $sDate, $eDate, $status);
                $stmtA->execute();
                $agreeId = (int)$stmtA->insert_id;
            }
        }
    }
}

// 3. Manage Documents (if agreement exists or just terminal docs)
// Note: We link docs to agreement if available, else just terminal
$targetAgreeId = $agreeId > 0 ? $agreeId : null;

function save_terminal_doc($db, $fileKey, $docType, $termId, $agreeId) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
            $uploads_dir = __DIR__ . '/../../uploads';
            if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);
            $fname = 'term_' . $termId . '_' . $fileKey . '_' . time() . '.' . $ext;
            $dest = $uploads_dir . DIRECTORY_SEPARATOR . $fname;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
                $stmtD = $db->prepare("INSERT INTO facility_documents (terminal_id, agreement_id, doc_type, file_path) VALUES (?, ?, ?, ?)");
                if ($stmtD) {
                    $stmtD->bind_param('iiss', $termId, $agreeId, $docType, $fname);
                    $stmtD->execute();
                }
            }
        }
    }
}

save_terminal_doc($db, 'moa_file', 'MOA', $terminalId, $targetAgreeId);
save_terminal_doc($db, 'contract_file', 'Contract', $terminalId, $targetAgreeId);
save_terminal_doc($db, 'permit_file', 'Terminal Permit', $terminalId, $targetAgreeId);

// Handle other attachments array
if (isset($_FILES['other_attachments']['name']) && is_array($_FILES['other_attachments']['name'])) {
    $cnt = count($_FILES['other_attachments']['name']);
    for ($i = 0; $i < $cnt; $i++) {
        if (($_FILES['other_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['other_attachments']['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
                $uploads_dir = __DIR__ . '/../../uploads';
                if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0777, true);
                $fname = 'term_' . $terminalId . '_other_' . $i . '_' . time() . '.' . $ext;
                $dest = $uploads_dir . DIRECTORY_SEPARATOR . $fname;
                if (move_uploaded_file($_FILES['other_attachments']['tmp_name'][$i], $dest)) {
                    $stmtD = $db->prepare("INSERT INTO facility_documents (terminal_id, agreement_id, doc_type, file_path) VALUES (?, ?, 'Other', ?)");
                    if ($stmtD) {
                        $stmtD->bind_param('iis', $terminalId, $targetAgreeId, $fname);
                        $stmtD->execute();
                    }
                }
            }
        }
    }
}

// Deprecated old permit logic (kept for fallback but simplified)
// ... (Logic removed in favor of facility_documents) ...

echo json_encode(['success' => true, 'ok' => true, 'message' => 'Saved successfully', 'terminal_id' => $terminalId, 'id' => $terminalId]);
?>
