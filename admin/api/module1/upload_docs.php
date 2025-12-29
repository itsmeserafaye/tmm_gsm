<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_POST['plate_number'] ?? '');
header('Content-Type: application/json');

if ($plate === '') {
    echo json_encode(['error' => 'Plate number required']);
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
        
        if (move_uploaded_file($_FILES[$type]['tmp_name'], $dest)) {
            $uploaded[] = $filename;
            
            // Update DB
            $stmt = $db->prepare("INSERT INTO documents (plate_number, type, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $plate, $type, $filename);
            $stmt->execute();
        } else {
            $errors[] = "$type: Failed to move file";
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
?> 
