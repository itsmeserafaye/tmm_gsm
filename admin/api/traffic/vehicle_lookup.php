<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
header('Content-Type: application/json');
require_any_permission(['module3.issue','module3.read']);
 
$db = db();
$plateRaw = strtoupper(trim((string)($_GET['plate'] ?? '')));
$plateRaw = preg_replace('/\s+/', '', $plateRaw);
$plateNoDash = preg_replace('/[^A-Z0-9]/', '', $plateRaw);
$plate = $plateRaw;
if ($plate !== '' && strpos($plate, '-') === false) {
  if (preg_match('/^([A-Z0-9]+)(\d{3,4})$/', $plateNoDash, $m)) {
    $plate = $m[1] . '-' . $m[2];
  }
}
if ($plate === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_plate']);
  exit;
}
if (!preg_match('/^[A-Z0-9\-]{4,16}$/', $plateRaw)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'invalid_plate']);
  exit;
}
 
$stmt = $db->prepare("SELECT plate_number, operator_name FROM vehicles WHERE plate_number=? OR REPLACE(plate_number,'-','')=? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('ss', $plate, $plateNoDash);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
 
if (!$row) {
  echo json_encode(['ok' => true, 'data' => null]);
  exit;
}
 
echo json_encode(['ok' => true, 'data' => [
  'plate_number' => (string)($row['plate_number'] ?? ''),
  'operator_name' => (string)($row['operator_name'] ?? ''),
]]);

