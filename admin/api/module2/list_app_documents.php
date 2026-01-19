<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.view','module2.franchises.manage']);

$appId = (int)($_GET['application_id'] ?? 0);
if ($appId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}

$stmt = $db->prepare("SELECT id, type, file_path, uploaded_at FROM documents WHERE application_id=? ORDER BY uploaded_at DESC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $appId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);

