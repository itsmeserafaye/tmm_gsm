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

  $res = $db->query("SELECT id, code FROM rbac_permissions");
  $codeToId = [];
  while ($res && ($row = $res->fetch_assoc())) {
    $codeToId[(string)$row['code']] = (int)$row['id'];
  }

  $allCodes = array_keys($codeToId);
  $mapCodes = rbac_recommended_role_permission_codes($allCodes);

  $map = [];
  foreach ($mapCodes as $roleName => $codes) {
    $ids = [];
    foreach ($codes as $c) {
      if (isset($codeToId[$c])) $ids[] = $codeToId[$c];
    }
    $map[$roleName] = [
      'permission_codes' => array_values(array_unique($codes)),
      'permission_ids' => array_values(array_unique($ids)),
    ];
  }

  rbac_json_out(200, ['ok' => true, 'recommended' => $map]);
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

