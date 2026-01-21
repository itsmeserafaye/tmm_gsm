<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
$plate = trim((string)($_POST['plate_number'] ?? ($_POST['plate_no'] ?? '')));
$vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : 0;
header('Content-Type: application/json');
require_permission('module1.vehicles.write');

if ($vehicleId <= 0 && $plate === '') {
    echo json_encode(['error' => 'missing_vehicle']);
    exit;
}

$exists = null;
if ($vehicleId > 0) {
    $chk = $db->prepare("SELECT id, plate_number FROM vehicles WHERE id=?");
    $chk->bind_param('i', $vehicleId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
} else {
    $chk = $db->prepare("SELECT id, plate_number FROM vehicles WHERE plate_number=?");
    $chk->bind_param('s', $plate);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
}
if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'vehicle_not_found']);
    exit;
}
$vehicleId = (int)($exists['id'] ?? 0);
$plate = (string)($exists['plate_number'] ?? $plate);

$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$uploaded = [];
$errors = [];

foreach (['or', 'cr', 'deed', 'orcr', 'insurance', 'emission', 'others'] as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $errors[] = "$field: Invalid file type ($ext)";
            continue;
        }
        
        $filename = $plate . '_' . $field . '_' . time() . '.' . $ext;
        $dest = $uploads_dir . '/' . $filename;
        
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
            $errors[] = "$field: Failed to move file";
            continue;
        }

        $safe = tmm_scan_file_for_viruses($dest);
        if (!$safe) {
            if (is_file($dest)) { @unlink($dest); }
            $errors[] = "$field: File failed security scan";
            continue;
        }

        $uploaded[] = $filename;
        $docType = 'Others';
        $legacyType = 'deed';
        if ($field === 'or' || $field === 'cr' || $field === 'orcr') { $docType = 'ORCR'; $legacyType = ($field === 'cr' ? 'cr' : 'or'); }
        elseif ($field === 'insurance') { $docType = 'Insurance'; $legacyType = 'insurance'; }
        elseif ($field === 'emission') { $docType = 'Emission'; $legacyType = 'others'; }
        elseif ($field === 'deed') { $docType = 'Others'; $legacyType = 'deed'; }

        $stmt = $db->prepare("INSERT INTO vehicle_documents (vehicle_id, doc_type, file_path, is_verified) VALUES (?, ?, ?, 0)");
        if ($stmt) {
            $stmt->bind_param('iss', $vehicleId, $docType, $filename);
            if (!$stmt->execute()) {
                if (is_file($dest)) { @unlink($dest); }
                $errors[] = "$field: DB insert failed";
                continue;
            }
        }

        $stmtLegacy = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
        if ($stmtLegacy) {
            $stmtLegacy->bind_param('sss', $plate, $legacyType, $filename);
            $stmtLegacy->execute();
        }
    }
}

if (empty($uploaded) && empty($errors)) {
    echo json_encode(['error' => 'No files selected']);
} elseif (!empty($errors)) {
    echo json_encode(['error' => implode(', ', $errors), 'uploaded' => $uploaded]);
} else {
    echo json_encode(['ok' => true, 'files' => $uploaded, 'vehicle_id' => $vehicleId, 'plate_number' => $plate]);
}
