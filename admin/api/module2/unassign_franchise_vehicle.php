<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.endorse','module2.approve']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$fvId = (int)($_POST['fv_id'] ?? 0);
if ($fvId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_fv_id']);
  exit;
}

$stmt = $db->prepare("UPDATE franchise_vehicles SET status='Inactive' WHERE fv_id=?");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $fvId);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok]);

