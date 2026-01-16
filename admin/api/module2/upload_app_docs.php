<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/security.php';
$db = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$app_id = (int)($_POST['application_id'] ?? 0);
if ($app_id === 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing Application ID']);
    exit;
}

$uploadDir = __DIR__ . '/../../../uploads/franchise/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

$uploaded = [];
$errors = [];

foreach ($_FILES as $key => $file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $errors[] = "$key: Invalid file type";
            continue;
        }

        $filename = "APP{$app_id}_{$key}_" . time() . ".$ext";
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errors[] = "$key: Upload failed";
            continue;
        }

        $safe = tmm_scan_file_for_viruses($dest);
        if (!$safe) {
            if (is_file($dest)) { @unlink($dest); }
            $errors[] = "$key: File failed security scan";
            continue;
        }

        $db_path = "uploads/franchise/$filename";
        $type = ucfirst(str_replace('doc_', '', $key));

        $stmt = $db->prepare("INSERT INTO documents (application_id, type, file_path) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iss', $app_id, $type, $db_path);
            $stmt->execute();
            $uploaded[] = $filename;
        } else {
            $errors[] = "$key: Database error: " . $db->error;
        }
    }
}

echo json_encode(['ok' => true, 'uploaded' => $uploaded, 'errors' => $errors]);
