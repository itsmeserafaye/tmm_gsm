<?php
if (php_sapi_name() !== 'cli') {
  http_response_code(403);
  echo "forbidden\n";
  exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';

function gen_password(): string {
  return 'Tmm@' . bin2hex(random_bytes(6)) . 'A1!';
}

function out(array $payload, int $code = 0): void {
  echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
  exit($code);
}

$opts = getopt('', [
  'list',
  'reset',
  'ensure',
  'user-id:',
  'email:',
  'new-email:',
  'password:',
  'generate'
]);

$db = db();
rbac_ensure_schema($db);

if (isset($opts['list'])) {
  $sql = "
    SELECT u.id, u.email, u.first_name, u.last_name, u.status
    FROM rbac_users u
    JOIN rbac_user_roles ur ON ur.user_id=u.id
    JOIN rbac_roles r ON r.id=ur.role_id
    WHERE r.name='SuperAdmin'
    ORDER BY u.id ASC
  ";
  $rows = [];
  $res = $db->query($sql);
  while ($res && ($row = $res->fetch_assoc())) {
    $rows[] = [
      'id' => (int)$row['id'],
      'email' => (string)$row['email'],
      'first_name' => (string)$row['first_name'],
      'last_name' => (string)$row['last_name'],
      'status' => (string)$row['status'],
    ];
  }
  out(['ok' => true, 'superadmins' => $rows]);
}

if (!isset($opts['reset']) && !isset($opts['ensure'])) {
  out([
    'ok' => false,
    'error' => 'usage',
    'examples' => [
      'php admin/tools/reset_superadmin_credentials.php --list',
      'php admin/tools/reset_superadmin_credentials.php --reset --generate',
      'php admin/tools/reset_superadmin_credentials.php --reset --email=ict.admin@city.gov.ph --password="NewStrongPass@123"',
      'php admin/tools/reset_superadmin_credentials.php --ensure --generate --new-email=ict.admin@city.gov.ph',
    ],
  ], 2);
}

$targetUserId = isset($opts['user-id']) ? (int)$opts['user-id'] : 0;
$targetEmail = isset($opts['email']) ? strtolower(trim((string)$opts['email'])) : '';
$newEmail = isset($opts['new-email']) ? strtolower(trim((string)$opts['new-email'])) : '';
$password = isset($opts['password']) ? (string)$opts['password'] : '';
$generate = isset($opts['generate']);

if ($generate && $password === '') {
  $password = gen_password();
}

if ($password !== '') {
  $valid = (strlen($password) >= 10)
    && preg_match('/[A-Z]/', $password)
    && preg_match('/[a-z]/', $password)
    && preg_match('/\d/', $password)
    && preg_match('/[^A-Za-z0-9]/', $password);
  if (!$valid) {
    out(['ok' => false, 'error' => 'weak_password'], 2);
  }
}

function find_superadmin_user_id(mysqli $db, int $userId, string $email): int {
  if ($userId > 0) {
    $stmt = $db->prepare("
      SELECT u.id
      FROM rbac_users u
      JOIN rbac_user_roles ur ON ur.user_id=u.id
      JOIN rbac_roles r ON r.id=ur.role_id
      WHERE r.name='SuperAdmin' AND u.id=?
      LIMIT 1
    ");
    if ($stmt) {
      $stmt->bind_param('i', $userId);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      return $row ? (int)$row['id'] : 0;
    }
    return 0;
  }

  if ($email !== '') {
    $stmt = $db->prepare("
      SELECT u.id
      FROM rbac_users u
      JOIN rbac_user_roles ur ON ur.user_id=u.id
      JOIN rbac_roles r ON r.id=ur.role_id
      WHERE r.name='SuperAdmin' AND u.email=?
      LIMIT 1
    ");
    if ($stmt) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      return $row ? (int)$row['id'] : 0;
    }
    return 0;
  }

  $res = $db->query("
    SELECT u.id
    FROM rbac_users u
    JOIN rbac_user_roles ur ON ur.user_id=u.id
    JOIN rbac_roles r ON r.id=ur.role_id
    WHERE r.name='SuperAdmin'
    ORDER BY u.id ASC
    LIMIT 1
  ");
  $row = $res ? $res->fetch_assoc() : null;
  return $row ? (int)$row['id'] : 0;
}

function find_user_id_by_email(mysqli $db, string $email): int {
  $email = strtolower(trim($email));
  if ($email === '') return 0;
  $stmt = $db->prepare("SELECT id FROM rbac_users WHERE email=? LIMIT 1");
  if (!$stmt) return 0;
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ? (int)$row['id'] : 0;
}

if (isset($opts['ensure'])) {
  $roleId = rbac_role_id($db, 'SuperAdmin');
  if (!$roleId) {
    out(['ok' => false, 'error' => 'missing_superadmin_role'], 3);
  }

  $existingId = find_superadmin_user_id($db, 0, $targetEmail);
  if ($existingId > 0) {
    $targetUserId = $existingId;
  } else {
    $emailToUse = $newEmail !== '' ? $newEmail : ($targetEmail !== '' ? $targetEmail : 'ict.admin@city.gov.ph');
    $pwdToUse = $password !== '' ? $password : gen_password();

    $existingUserId = find_user_id_by_email($db, $emailToUse);
    if ($existingUserId > 0) {
      $stmt2 = $db->prepare("INSERT IGNORE INTO rbac_user_roles(user_id, role_id) VALUES(?, ?)");
      if ($stmt2) {
        $stmt2->bind_param('ii', $existingUserId, $roleId);
        $stmt2->execute();
        $stmt2->close();
      }
      $targetUserId = $existingUserId;
      $targetEmail = $emailToUse;
      $newEmail = '';
      $password = $pwdToUse;
    } else {
      $hash = password_hash($pwdToUse, PASSWORD_DEFAULT);
      if ($hash === false) out(['ok' => false, 'error' => 'hash_failed'], 3);

      $first = 'ICTO';
      $last = 'Administrator';
      $dept = 'City ICT Office';
      $pos = 'System Administrator';
      $status = 'Active';
      $stmt = $db->prepare("INSERT INTO rbac_users(email, password_hash, first_name, last_name, department, position_title, status) VALUES(?,?,?,?,?,?,?)");
      if (!$stmt) out(['ok' => false, 'error' => 'db_prepare_failed'], 3);
      $stmt->bind_param('sssssss', $emailToUse, $hash, $first, $last, $dept, $pos, $status);
      $ok = $stmt->execute();
      $userId = (int)$stmt->insert_id;
      $stmt->close();
      if (!$ok || $userId <= 0) out(['ok' => false, 'error' => 'create_failed'], 3);

      $stmt2 = $db->prepare("INSERT IGNORE INTO rbac_user_roles(user_id, role_id) VALUES(?, ?)");
      if ($stmt2) {
        $stmt2->bind_param('ii', $userId, $roleId);
        $stmt2->execute();
        $stmt2->close();
      }

      out([
        'ok' => true,
        'action' => 'created',
        'user_id' => $userId,
        'email' => $emailToUse,
        'password' => $pwdToUse
      ]);
    }
  }
}

$id = find_superadmin_user_id($db, $targetUserId, $targetEmail);
if ($id <= 0) {
  out(['ok' => false, 'error' => 'superadmin_not_found'], 2);
}

$fields = [];
$types = '';
$params = [];

if ($newEmail !== '') { $fields[] = 'email=?'; $types .= 's'; $params[] = $newEmail; }
if ($password !== '') {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  if ($hash === false) out(['ok' => false, 'error' => 'hash_failed'], 3);
  $fields[] = 'password_hash=?'; $types .= 's'; $params[] = $hash;
}
$fields[] = "status='Active'";

if (!$fields) {
  out(['ok' => false, 'error' => 'nothing_to_update'], 2);
}

$sql = "UPDATE rbac_users SET " . implode(', ', $fields) . " WHERE id=?";
$types .= 'i';
$params[] = $id;

$stmt = $db->prepare($sql);
if (!$stmt) out(['ok' => false, 'error' => 'db_prepare_failed'], 3);
$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) out(['ok' => false, 'error' => 'update_failed'], 3);

$stmt = $db->prepare("SELECT email, status FROM rbac_users WHERE id=? LIMIT 1");
$row = null;
if ($stmt) {
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

out([
  'ok' => true,
  'action' => 'updated',
  'user_id' => $id,
  'email' => (string)($row['email'] ?? ''),
  'status' => (string)($row['status'] ?? ''),
  'password' => $password !== '' ? $password : null
]);

