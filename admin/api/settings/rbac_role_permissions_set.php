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
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rbac_json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  }

  $db = db();
  rbac_ensure_schema($db);
  require_role(['SuperAdmin']);

  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) rbac_json_out(400, ['ok' => false, 'error' => 'invalid_json']);

  $roleId = (int)($input['role_id'] ?? 0);
  $permIds = $input['permission_ids'] ?? null;
  if ($roleId <= 0) rbac_json_out(400, ['ok' => false, 'error' => 'missing_role_id']);
  if (!is_array($permIds)) rbac_json_out(400, ['ok' => false, 'error' => 'missing_permission_ids']);

  $permIdsClean = [];
  foreach ($permIds as $pid) {
    $pid = (int)$pid;
    if ($pid > 0) $permIdsClean[] = $pid;
  }
  $permIdsClean = array_values(array_unique($permIdsClean));

  $db->begin_transaction();
  try {
    $roleStmt = $db->prepare("SELECT name FROM rbac_roles WHERE id=? FOR UPDATE");
    if (!$roleStmt) throw new Exception('db_prepare_failed');
    $roleStmt->bind_param('i', $roleId);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();
    if (!$roleRow) rbac_json_out(404, ['ok' => false, 'error' => 'role_not_found']);

    $name = (string)$roleRow['name'];
    if ($name === 'SuperAdmin') {
      rbac_json_out(400, ['ok' => false, 'error' => 'superadmin_permissions_locked']);
    }

    $del = $db->prepare("DELETE FROM rbac_role_permissions WHERE role_id=?");
    if (!$del) throw new Exception('db_prepare_failed');
    $del->bind_param('i', $roleId);
    if (!$del->execute()) throw new Exception('delete_failed: ' . (string)$del->error);
    $del->close();

    if ($permIdsClean) {
      $ins = $db->prepare("INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id) VALUES(?, ?)");
      if (!$ins) throw new Exception('db_prepare_failed');
      foreach ($permIdsClean as $pid) {
        $ins->bind_param('ii', $roleId, $pid);
        if (!$ins->execute()) throw new Exception('insert_failed: ' . (string)$ins->error);
      }
      $ins->close();
    }

    $db->commit();
    rbac_json_out(200, ['ok' => true]);
  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

