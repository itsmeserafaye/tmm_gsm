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
  if (!is_array($input)) {
    rbac_json_out(400, ['ok' => false, 'error' => 'invalid_json']);
  }

  $id = (int)($input['id'] ?? 0);
  $name = trim((string)($input['name'] ?? ''));
  $description = trim((string)($input['description'] ?? ''));

  if ($name === '') rbac_json_out(400, ['ok' => false, 'error' => 'missing_name']);
  if (strlen($name) < 3 || strlen($name) > 64) rbac_json_out(400, ['ok' => false, 'error' => 'invalid_name_length']);

  $db->begin_transaction();
  try {
    if ($id > 0) {
      $lock = $db->prepare("SELECT name FROM rbac_roles WHERE id=? FOR UPDATE");
      if (!$lock) throw new Exception('db_prepare_failed');
      $lock->bind_param('i', $id);
      $lock->execute();
      $row = $lock->get_result()->fetch_assoc();
      $lock->close();
      if (!$row) rbac_json_out(404, ['ok' => false, 'error' => 'role_not_found']);
      if ((string)$row['name'] === 'SuperAdmin' && $name !== 'SuperAdmin') {
        rbac_json_out(400, ['ok' => false, 'error' => 'superadmin_rename_not_allowed']);
      }

      $stmt = $db->prepare("UPDATE rbac_roles SET name=?, description=? WHERE id=?");
      if (!$stmt) throw new Exception('db_prepare_failed');
      $stmt->bind_param('ssi', $name, $description, $id);
      if (!$stmt->execute()) throw new Exception('update_failed: ' . (string)$stmt->error);
      $stmt->close();
      $roleId = $id;
    } else {
      $stmt = $db->prepare("INSERT INTO rbac_roles(name, description) VALUES(?, ?)");
      if (!$stmt) throw new Exception('db_prepare_failed');
      $stmt->bind_param('ss', $name, $description);
      if (!$stmt->execute()) throw new Exception('insert_failed: ' . (string)$stmt->error);
      $roleId = (int)$stmt->insert_id;
      $stmt->close();
    }

    $db->commit();
    rbac_json_out(200, ['ok' => true, 'role_id' => $roleId]);
  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }
} catch (Throwable $e) {
  if (defined('TMM_TEST')) throw $e;
  rbac_json_out(500, ['ok' => false, 'error' => $e->getMessage()]);
}

