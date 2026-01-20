<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/rbac.php';

header('Content-Type: application/json');

function cu_send(bool $ok, $payload = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok] + (is_array($payload) ? $payload : ['data' => $payload]));
  exit;
}

try {
  $db = db();
  rbac_ensure_schema($db);
  require_role(['SuperAdmin']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    if ($status !== '' && !in_array($status, ['Active','Inactive','Locked'], true)) $status = '';

    $sql = "
      SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.last_login_at, u.created_at,
             MAX(COALESCE(p.mobile,'')) AS mobile,
             MAX(COALESCE(p.barangay,'')) AS barangay
      FROM rbac_users u
      LEFT JOIN user_profiles p ON p.user_id=u.id
      WHERE EXISTS (
        SELECT 1
        FROM rbac_user_roles ur
        JOIN rbac_roles r ON r.id=ur.role_id
        WHERE ur.user_id=u.id AND r.name='Commuter'
      )
      AND NOT EXISTS (
        SELECT 1
        FROM rbac_user_roles ur2
        JOIN rbac_roles r2 ON r2.id=ur2.role_id
        WHERE ur2.user_id=u.id AND r2.name <> 'Commuter'
      )
    ";
    $conds = [];
    $params = [];
    $types = '';

    if ($q !== '') {
      $conds[] = "(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.mobile LIKE ? OR p.barangay LIKE ?)";
      $like = '%' . $q . '%';
      $params = array_merge($params, [$like,$like,$like,$like,$like]);
      $types .= 'sssss';
    }
    if ($status !== '') {
      $conds[] = "u.status=?";
      $params[] = $status;
      $types .= 's';
    }
    if ($conds) $sql .= " AND " . implode(" AND ", $conds);
    $sql .= " GROUP BY u.id, u.email, u.first_name, u.last_name, u.status, u.last_login_at, u.created_at";
    $sql .= " ORDER BY u.created_at DESC LIMIT 1000";

    if ($params) {
      $stmt = $db->prepare($sql);
      if (!$stmt) throw new Exception('db_prepare_failed');
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
    } else {
      $res = $db->query($sql);
    }

    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
      $rows[] = [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
        'mobile' => (string)($row['mobile'] ?? ''),
        'barangay' => (string)($row['barangay'] ?? ''),
        'status' => (string)$row['status'],
        'last_login_at' => (string)($row['last_login_at'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
    if (!empty($stmt)) $stmt->close();
    cu_send(true, ['users' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cu_send(false, ['error' => 'method_not_allowed'], 405);
  }

  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input)) cu_send(false, ['error' => 'invalid_json'], 400);

  $action = trim((string)($input['action'] ?? ''));
  $userId = (int)($input['user_id'] ?? 0);
  if ($userId <= 0) cu_send(false, ['error' => 'invalid_user_id'], 400);

  $check = $db->prepare("SELECT u.id FROM rbac_users u JOIN rbac_user_roles ur ON ur.user_id=u.id JOIN rbac_roles r ON r.id=ur.role_id WHERE u.id=? AND r.name='Commuter' LIMIT 1");
  if (!$check) cu_send(false, ['error' => 'db_prepare_failed'], 500);
  $check->bind_param('i', $userId);
  $check->execute();
  $exists = (bool)($check->get_result()->fetch_row());
  $check->close();
  if (!$exists) cu_send(false, ['error' => 'not_found'], 404);

  $generateTempPassword = function (): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $special = '!@#$%&*?';
    $parts = [
      $alphabet[random_int(0, strlen($alphabet) - 1)],
      $lower[random_int(0, strlen($lower) - 1)],
      $digits[random_int(0, strlen($digits) - 1)],
      $special[random_int(0, strlen($special) - 1)],
    ];
    $all = $alphabet . $lower . $digits . $special;
    while (count($parts) < 12) {
      $parts[] = $all[random_int(0, strlen($all) - 1)];
    }
    shuffle($parts);
    return implode('', $parts);
  };

  if ($action === 'set_status') {
    $newStatus = trim((string)($input['status'] ?? ''));
    if (!in_array($newStatus, ['Active','Inactive','Locked'], true)) cu_send(false, ['error' => 'invalid_status'], 400);
    $stmt = $db->prepare("UPDATE rbac_users SET status=? WHERE id=?");
    if (!$stmt) cu_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('si', $newStatus, $userId);
    $stmt->execute();
    $stmt->close();
    cu_send(true, ['message' => 'Status updated']);
  }

  if ($action === 'reset_password') {
    $temp = $generateTempPassword();
    $hash = password_hash($temp, PASSWORD_DEFAULT);
    if ($hash === false) cu_send(false, ['error' => 'hash_failed'], 500);
    $stmt = $db->prepare("UPDATE rbac_users SET password_hash=?, status='Active' WHERE id=?");
    if (!$stmt) cu_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();
    cu_send(true, ['message' => 'Temporary password generated.', 'temporary_password' => $temp]);
  }

  if ($action === 'activity') {
    $stmt = $db->prepare("SELECT ok, ip_address, user_agent, created_at FROM rbac_login_audit WHERE user_id=? ORDER BY id DESC LIMIT 25");
    if (!$stmt) cu_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($res && ($row = $res->fetch_assoc())) {
      $items[] = [
        'ok' => (int)($row['ok'] ?? 0) === 1,
        'ip' => (string)($row['ip_address'] ?? ''),
        'ua' => (string)($row['user_agent'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
    $stmt->close();
    cu_send(true, ['items' => $items]);
  }

  if ($action === 'delete') {
    $stmt = $db->prepare("DELETE FROM rbac_users WHERE id=?");
    if (!$stmt) cu_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    cu_send(true, ['message' => 'Account deleted']);
  }

  cu_send(false, ['error' => 'invalid_action'], 400);
} catch (Throwable $e) {
  cu_send(false, ['error' => $e->getMessage()], 500);
}
