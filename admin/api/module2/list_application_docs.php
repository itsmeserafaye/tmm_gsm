<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.apply','module2.read','module2.endorse','module2.approve','module2.history','module2.franchises.manage','module2.view']);

$appId = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
if ($appId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}

$check = $db->prepare("SELECT application_id FROM franchise_applications WHERE application_id=? LIMIT 1");
if (!$check) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$check->bind_param('i', $appId);
$check->execute();
$exists = (bool)$check->get_result()->fetch_row();
$check->close();
if (!$exists) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'application_not_found']);
  exit;
}

$stmt = $db->prepare("SELECT id, type, file_path, verified, uploaded_at, uploaded_by
                      FROM documents
                      WHERE application_id=?
                      ORDER BY uploaded_at DESC, id DESC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $appId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
