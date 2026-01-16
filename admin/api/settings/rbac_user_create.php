<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function json_out($code, $payload) {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

function rand_password($len = 14) {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
  $out = '';
  for ($i = 0; $i < $len; $i++) {
    $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  return $out;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
  $db = db();
  require_role(['SuperAdmin']);

  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $first = trim((string)($_POST['first_name'] ?? ''));
  $last = trim((string)($_POST['last_name'] ?? ''));
  $middle = trim((string)($_POST['middle_name'] ?? ''));
  $suffix = trim((string)($_POST['suffix'] ?? ''));
  $employeeNo = trim((string)($_POST['employee_no'] ?? ''));
  $dept = trim((string)($_POST['department'] ?? ''));
  $pos = trim((string)($_POST['position_title'] ?? ''));
  $status = trim((string)($_POST['status'] ?? 'Active'));
  if (!in_array($status, ['Active','Inactive','Locked'], true)) $status = 'Active';

  $roleIds = [];
  if (isset($_POST['role_ids'])) {
    if (is_array($_POST['role_ids'])) {
      foreach ($_POST['role_ids'] as $rid) {
        $rid = (int)$rid;
        if ($rid > 0) $roleIds[] = $rid;
      }
    } else {
      $rid = (int)$_POST['role_ids'];
      if ($rid > 0) $roleIds[] = $rid;
    }
  }
  $roleIds = array_values(array_unique($roleIds));

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(400, ['ok' => false, 'error' => 'invalid_email']);
  if ($first === '' || $last === '') json_out(400, ['ok' => false, 'error' => 'missing_name']);

  $inputPassword = trim((string)($_POST['password'] ?? ''));
  $tmpPassword = $inputPassword !== '' ? $inputPassword : rand_password(14);
  $hash = password_hash($tmpPassword, PASSWORD_DEFAULT);

  $stmt = $db->prepare("INSERT INTO rbac_users(email, password_hash, first_name, last_name, middle_name, suffix, employee_no, department, position_title, status)
                        VALUES(?,?,?,?,?,?,?,?,?,?)");
  if (!$stmt) json_out(500, ['ok' => false, 'error' => 'db_prepare_failed']);
  $stmt->bind_param('ssssssssss', $email, $hash, $first, $last, $middle, $suffix, $employeeNo, $dept, $pos, $status);
  $ok = $stmt->execute();
  if (!$ok) {
    $err = (string)$stmt->error;
    $stmt->close();
    json_out(400, ['ok' => false, 'error' => 'create_failed', 'detail' => $err]);
  }
  $userId = (int)$stmt->insert_id;
  $stmt->close();

  if ($userId > 0) {
    if (!$roleIds) {
      $res = $db->query("SELECT id FROM rbac_roles WHERE name='Viewer' LIMIT 1");
      if ($res && ($row = $res->fetch_assoc())) $roleIds = [(int)$row['id']];
    }
    foreach ($roleIds as $rid) {
      $st = $db->prepare("INSERT IGNORE INTO rbac_user_roles(user_id, role_id) VALUES(?, ?)");
      if ($st) {
        $st->bind_param('ii', $userId, $rid);
        $st->execute();
        $st->close();
      }
    }
  }

  json_out(200, ['ok' => true, 'user_id' => $userId, 'temporary_password' => $tmpPassword]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  json_out(400, ['ok' => false, 'error' => $e->getMessage()]);
}
