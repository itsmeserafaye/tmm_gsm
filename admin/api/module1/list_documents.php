<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
$plate = trim($_GET['plate'] ?? '');
$type = trim($_GET['type'] ?? '');
header('Content-Type: application/json');

if ($plate === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_plate']);
  exit;
}

$sql = "SELECT id, plate_number, type, file_path, uploaded_at FROM documents WHERE plate_number=?";
$params = [$plate];
$types = 's';
if ($type !== '') {
  $sql .= " AND type=?";
  $params[] = $type;
  $types .= 's';
}
$sql .= " ORDER BY uploaded_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

echo json_encode(['ok' => true, 'data' => $rows]);
?>