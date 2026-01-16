<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
header('Content-Type: application/json');
require_permission('module1.vehicles.write');

if ($plate === '') {
    echo json_encode(['error' => 'Plate number required']);
    exit;
}

$chk = $db->prepare("SELECT plate_number FROM vehicles WHERE plate_number=?");
$chk->bind_param('s', $plate);
$chk->execute();
$exists = $chk->get_result()->fetch_assoc();
if (!$exists) {
    http_response_code(404);
    echo json_encode(['error' => 'vehicle_not_found']);
    exit;
}

$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$uploaded = [];
$errors = [];

foreach (['or', 'cr', 'deed'] as $type) {
    if (isset($_FILES[$type]) && $_FILES[$type]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$type]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $errors[] = "$type: Invalid file type ($ext)";
            continue;
        }
        
        $filename = $plate . '_' . $type . '_' . time() . '.' . $ext;
        $dest = $uploads_dir . '/' . $filename;
        
        if (!move_uploaded_file($_FILES[$type]['tmp_name'], $dest)) {
            $errors[] = "$type: Failed to move file";
            continue;
        }

        $safe = tmm_scan_file_for_viruses($dest);
        if (!$safe) {
            if (is_file($dest)) { @unlink($dest); }
            $errors[] = "$type: File failed security scan";
            continue;
        }

        $uploaded[] = $filename;
        $stmt = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $plate, $type, $filename);
        if (!$stmt->execute()) {
            if (is_file($dest)) { @unlink($dest); }
            $errors[] = "$type: DB insert failed";
        }
    }
}

if (empty($uploaded) && empty($errors)) {
    echo json_encode(['error' => 'No files selected']);
} elseif (!empty($errors)) {
    echo json_encode(['error' => implode(', ', $errors), 'uploaded' => $uploaded]);
} else {
    echo json_encode(['ok' => true, 'files' => $uploaded]);
}
