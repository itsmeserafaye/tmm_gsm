<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_login();
require_any_permission(['module1.read', 'module1.view', 'module1.write', 'module1.vehicles.write']);

$db = db();
$operatorId = isset($_GET['operator_id']) ? (int)$_GET['operator_id'] : 0;
if ($operatorId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_operator_id']);
  exit;
}

$stmt = $db->prepare("SELECT doc_id, file_path, doc_status, remarks, uploaded_at
                      FROM operator_documents
                      WHERE operator_id=? AND doc_type='Others' AND COALESCE(remarks,'') LIKE 'Declared Fleet%'
                      ORDER BY COALESCE(uploaded_at, verified_at) DESC, doc_id DESC
                      LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $operatorId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'not_found']);
  exit;
}

echo json_encode(['ok' => true, 'data' => [
  'doc_id' => (int)($row['doc_id'] ?? 0),
  'file_path' => (string)($row['file_path'] ?? ''),
  'doc_status' => (string)($row['doc_status'] ?? ''),
  'remarks' => (string)($row['remarks'] ?? ''),
  'uploaded_at' => (string)($row['uploaded_at'] ?? ''),
]]);
?>

