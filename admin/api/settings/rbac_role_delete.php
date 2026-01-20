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

  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) rbac_json_out(400, ['ok' => false, 'error' => 'missing_id']);

  $db->begin_transaction();
  try {
    $stmt = $db->prepare("SELECT name FROM rbac_roles WHERE id=? FOR UPDATE");
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) rbac_json_out(404, ['ok' => false, 'error' => 'role_not_found']);

    $name = (string)$row['name'];
    if ($name === 'SuperAdmin' || $name === 'Commuter') {
      rbac_json_out(400, ['ok' => false, 'error' => 'role_delete_not_allowed']);
    }

    $del = $db->prepare("DELETE FROM rbac_roles WHERE id=?");
    if (!$del) throw new Exception('db_prepare_failed');
    $del->bind_param('i', $id);
    if (!$del->execute()) throw new Exception('delete_failed: ' . (string)$del->error);
    $del->close();

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

