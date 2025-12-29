<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$ticket = trim($_POST['ticket_number'] ?? '');
if ($ticket === '') { echo json_encode(['error' => 'Ticket number required']); exit; }

$stmt = $db->prepare("SELECT ticket_id FROM tickets WHERE ticket_number = ?");
$stmt->bind_param('s', $ticket);
$stmt->execute();
$res = $stmt->get_result();
if (!($row = $res->fetch_assoc())) { echo json_encode(['error' => 'Ticket not found']); exit; }
$tid = (int)$row['ticket_id'];

$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) { mkdir($uploads_dir, 0777, true); }

if (!isset($_FILES['evidence']) || $_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['error' => 'No file uploaded']);
  exit;
}

$ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','pdf','mp4','mov'])) { echo json_encode(['error' => 'Invalid file type']); exit; }

$filename = $ticket . '_ev_' . time() . '.' . $ext;
$dest = $uploads_dir . '/' . $filename;
if (move_uploaded_file($_FILES['evidence']['tmp_name'], $dest)) {
  $type = in_array($ext, ['mp4','mov']) ? 'video' : ($ext === 'pdf' ? 'pdf' : 'image');
  $stmtE = $db->prepare("INSERT INTO evidence (ticket_id, file_path, file_type) VALUES (?, ?, ?)");
  $stmtE->bind_param('iss', $tid, $filename, $type);
  $stmtE->execute();
  echo json_encode(['ok' => true, 'file' => $filename]);
} else {
  echo json_encode(['error' => 'Failed to save file']);
}
?> 
