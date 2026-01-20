<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/rbac.php';

header('Content-Type: application/json');

function rbac_json_out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    rbac_json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  }

  $db = db();
  rbac_ensure_schema($db);
  require_role(['SuperAdmin']);

  $roleId = (int)($_GET['role_id'] ?? 0);
  if ($roleId <= 0) rbac_json_out(400, ['ok' => false, 'error' => 'missing_role_id']);

  $stmt = $db->prepare("
    SELECT rp.permission_id, p.code
    FROM rbac_role_permissions rp
    JOIN rbac_permissions p ON p.id = rp.permission_id
    WHERE rp.role_id=?
    ORDER BY p.code ASC
  ");
  if (!$stmt) rbac_json_out(500, ['ok' => false, 'error' => 'db_prepare_failed']);
  $stmt->bind_param('i', $roleId);
  $stmt->execute();
  $res = $stmt->get_result();
  $ids = [];
  $codes = [];
  while ($row = $res->fetch_assoc()) {
    $ids[] = (int)$row['permission_id'];
    $codes[] = (string)$row['code'];
  }
  $stmt->close();

  rbac_json_out(200, ['ok' => true, 'permission_ids' => $ids, 'permission_codes' => $codes]);
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

