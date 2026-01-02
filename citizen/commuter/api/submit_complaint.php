<?php
header('Content-Type: application/json');
require_once 'db.php';

$conn = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$complaint_type = $_POST['complaint_type'] ?? '';
$description = $_POST['description'] ?? '';
$plate_number = $_POST['plate_number'] ?? '';
$is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
$ai_category = $_POST['ai_category'] ?? $complaint_type; // Frontend AI or Fallback

// --- AI Integration Removed (Client-side only now) ---
// The classification is now handled by the frontend regex or manual selection.
// We trust the frontend 'ai_category' or fallback to 'complaint_type'.
// ----------------------------------------------------

if (empty($complaint_type) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Type and Description are required']);
    exit;
}

// Handle File Upload
$media_path = null;
if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
    $targetFile = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['media']['tmp_name'], $targetFile)) {
        $media_path = 'uploads/' . $fileName;
    }
}

// Generate Reference Number (e.g., COM-20250101-RANDOM)
$ref_number = 'COM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

$stmt = $conn->prepare("INSERT INTO complaints (reference_number, vehicle_plate, complaint_type, description, media_path, status, ai_category, is_anonymous) VALUES (?, ?, ?, ?, ?, 'Submitted', ?, ?)");
$stmt->bind_param("sssssssi", $ref_number, $plate_number, $complaint_type, $description, $media_path, $ai_category, $is_anonymous);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'reference_number' => $ref_number,
        'message' => 'Complaint submitted successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>