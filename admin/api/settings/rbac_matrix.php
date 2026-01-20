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

  $rolesRes = $db->query("SELECT id, name, COALESCE(description,'') AS description FROM rbac_roles ORDER BY name ASC");
  $roles = [];
  if ($rolesRes) {
    while ($r = $rolesRes->fetch_assoc()) {
      $roles[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'description' => (string)$r['description']];
    }
  }

  $permRes = $db->query("SELECT id, code, COALESCE(description,'') AS description FROM rbac_permissions ORDER BY code ASC");
  $perms = [];
  if ($permRes) {
    while ($p = $permRes->fetch_assoc()) {
      $perms[] = ['id' => (int)$p['id'], 'code' => (string)$p['code'], 'description' => (string)$p['description']];
    }
  }

  $mapRes = $db->query("SELECT role_id, permission_id FROM rbac_role_permissions");
  $map = [];
  if ($mapRes) {
    while ($m = $mapRes->fetch_assoc()) {
      $rid = (int)$m['role_id'];
      $pid = (int)$m['permission_id'];
      if ($rid > 0 && $pid > 0) {
        if (!isset($map[$rid])) $map[$rid] = [];
        $map[$rid][$pid] = true;
      }
    }
  }

  rbac_json_out(200, ['ok' => true, 'roles' => $roles, 'permissions' => $perms, 'map' => $map]);
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

