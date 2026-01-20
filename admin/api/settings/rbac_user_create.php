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

  $email = trim((string) ($input['email'] ?? ''));
  // Auto-format names: Title Case
  $firstName = ucwords(strtolower(trim((string) ($input['first_name'] ?? ''))));
  $lastName = ucwords(strtolower(trim((string) ($input['last_name'] ?? ''))));
  $roleIds = $input['roles'] ?? []; // Array of role IDs

  if ($email === '' || $firstName === '' || $lastName === '') {
    cu_fail('missing_required_fields');
  }
  if (strlen($firstName) < 2 || strlen($lastName) < 2) {
    cu_fail('names_too_short');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    cu_fail('invalid_email_format');
  }
  if (!is_array($roleIds) || count($roleIds) === 0) {
    cu_fail('roles_required');
  }

  // Transaction Start
  $db->begin_transaction();

  try {
    // 1. Check duplicate email
    $check = $db->prepare("SELECT id FROM rbac_users WHERE email = ? FOR UPDATE");
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
      throw new Exception('email_already_exists');
    }
    $check->close();

    // 2. Insert User
    // Generate temp password
    $tempPass = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789!@#$%'), 0, 10);
    $hash = password_hash($tempPass, PASSWORD_DEFAULT);

    // Auto-format details
    $empNo = strtoupper(trim((string) ($input['employee_no'] ?? '')));
    $dept = trim((string) ($input['department'] ?? ''));
    $title = ucwords(strtolower(trim((string) ($input['position_title'] ?? ''))));

    $stmt = $db->prepare("INSERT INTO rbac_users (email, password_hash, first_name, last_name, employee_no, department, position_title, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
    $stmt->bind_param('sssssss', $email, $hash, $firstName, $lastName, $empNo, $dept, $title);
    if (!$stmt->execute()) {
      throw new Exception('insert_failed: ' . $stmt->error);
    }
    $userId = $stmt->insert_id;
    $stmt->close();

    // 3. Assign Roles
    if (is_array($roleIds) && !empty($roleIds)) {
      $roleStmt = $db->prepare("INSERT IGNORE INTO rbac_user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
      if (!$roleStmt) {
        throw new Exception('roles_insert_prepare_failed');
      }
      foreach ($roleIds as $rid) {
        $rid = (int) $rid;
        if ($rid > 0) {
          $roleStmt->bind_param('ii', $userId, $rid);
          if (!$roleStmt->execute()) {
            throw new Exception('roles_insert_failed: ' . (string) $roleStmt->error);
          }
        }
      }
      $roleStmt->close();
    }

    $db->commit();

    echo json_encode([
      'ok' => true,
      'message' => 'User created successfully',
      'user_id' => $userId,
      'temporary_password' => $tempPass
    ]);

  } catch (Throwable $e) {
    $db->rollback();
    throw $e;
  }

} catch (Throwable $e) {
  cu_fail($e->getMessage(), 500);
}
