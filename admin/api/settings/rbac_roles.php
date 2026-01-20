<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
  $db = db();
  require_role(['SuperAdmin']);

  $res = $db->query("SELECT MIN(id) AS id, name, MAX(COALESCE(description,'')) AS description FROM rbac_roles GROUP BY name ORDER BY name ASC");
  $roles = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $roles[] = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'description' => (string)($row['description'] ?? ''),
      ];
    }
  }
  echo json_encode(['ok' => true, 'roles' => $roles]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
