<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/edit_permission.php';

header('Content-Type: application/json');

try {
  require_role(['SuperAdmin']);
  $db = db();
  $targetId = (int)($_GET['target_user_id'] ?? $_POST['target_user_id'] ?? 0);
  if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_target']);
    exit;
  }
  $editorId = (int)($_SESSION['user_id'] ?? 0);
  if ($editorId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
  }
  $res = ep_authorized($db, $editorId, $targetId);
  echo json_encode(['ok' => true, 'authorized' => (bool)($res['authorized'] ?? false), 'granted_at' => $res['granted_at'] ?? null]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server_error']);
}
?>
