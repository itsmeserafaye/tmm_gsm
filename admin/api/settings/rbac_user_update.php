<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/rbac.php';

header('Content-Type: application/json');

function cu_fail(string $msg, int $code = 400): void
{
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

try {
  $db = db();
  require_role(['SuperAdmin']);
  rbac_ensure_schema($db);

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cu_fail('method_not_allowed', 405);
  }

  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input))
    cu_fail('invalid_json');

  $userId = (int) ($input['id'] ?? 0);
  if ($userId <= 0)
    cu_fail('invalid_user_id');

  $email = trim((string) ($input['email'] ?? ''));
  // Auto-format
  $firstName = ucwords(strtolower(trim((string) ($input['first_name'] ?? ''))));
  $lastName = ucwords(strtolower(trim((string) ($input['last_name'] ?? ''))));
  $roleIds = $input['roles'] ?? null; // Null means don't update roles, array means sync

  if ($email === '' || $firstName === '' || $lastName === '') {
    cu_fail('missing_required_fields');
  }
  if (strlen($firstName) < 2 || strlen($lastName) < 2) {
    cu_fail('names_too_short');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    cu_fail('invalid_email_format');
  }
  if (is_array($roleIds) && count($roleIds) === 0) {
    cu_fail('roles_required');
  }

  $db->begin_transaction();

  try {
    // 1. Check user exists and email uniqueness
    $stmt = $db->prepare("SELECT id FROM rbac_users WHERE email = ? AND id != ? FOR UPDATE");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
      throw new Exception('email_already_taken');
    }
    $stmt->close();

    // 2. Update User Profile
    $empNo = strtoupper(trim((string) ($input['employee_no'] ?? '')));
    $dept = trim((string) ($input['department'] ?? ''));
    $title = ucwords(strtolower(trim((string) ($input['position_title'] ?? ''))));
    $status = trim((string) ($input['status'] ?? 'Active'));

    // Check if password reset requested (optional)
    // If 'password' is sent and not empty
    $passSql = "";
    $types = "ssssssi";
    $params = [$email, $firstName, $lastName, $empNo, $dept, $title, $userId];

    // Basic update
    $sql = "UPDATE rbac_users SET email=?, first_name=?, last_name=?, employee_no=?, department=?, position_title=? WHERE id=?";

    // If status is provided, handle it (careful not to lock self out if we were updating self, but require_role check happens at start)
    if (isset($input['status'])) {
      // We might want to allow status update here too
      $sql = "UPDATE rbac_users SET email=?, first_name=?, last_name=?, employee_no=?, department=?, position_title=?, status=? WHERE id=?";
      $types = "sssssssi";
      $params = [$email, $firstName, $lastName, $empNo, $dept, $title, $status, $userId];
    }

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
      throw new Exception('update_failed: ' . $stmt->error);
    }
    $stmt->close();

    // 3. Sync Roles (if provided)
    if (is_array($roleIds)) {
      // Remove all existing roles
      $del = $db->prepare("DELETE FROM rbac_user_roles WHERE user_id = ?");
      $del->bind_param('i', $userId);
      if (!$del->execute()) {
        throw new Exception('roles_delete_failed: ' . (string) $del->error);
      }
      $del->close();

      // Insert new roles
      if (!empty($roleIds)) {
        $ins = $db->prepare("INSERT IGNORE INTO rbac_user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
        if (!$ins) {
          throw new Exception('roles_insert_prepare_failed');
        }
        foreach ($roleIds as $rid) {
          $rid = (int) $rid;
          if ($rid > 0) {
            $ins->bind_param('ii', $userId, $rid);
            if (!$ins->execute()) {
              throw new Exception('roles_insert_failed: ' . (string) $ins->error);
            }
          }
        }
        $ins->close();
      }
    }

    $db->commit();
    echo json_encode(['ok' => true, 'message' => 'User updated successfully']);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  cu_fail($e->getMessage(), 500);
}
