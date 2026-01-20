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

  $res = $db->query("SELECT id, code, COALESCE(description,'') AS description FROM rbac_permissions ORDER BY code ASC");
  $items = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $items[] = [
        'id' => (int)$row['id'],
        'code' => (string)$row['code'],
        'description' => (string)$row['description'],
      ];
    }
  }

  rbac_json_out(200, ['ok' => true, 'permissions' => $items]);
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

