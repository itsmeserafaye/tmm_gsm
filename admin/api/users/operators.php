<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

function ou_send(bool $ok, $payload = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok] + (is_array($payload) ? $payload : ['data' => $payload]));
  exit;
}

try {
  $db = db();
  require_role(['SuperAdmin']);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    if ($status !== '' && !in_array($status, ['Active','Inactive','Locked'], true)) $status = '';

    $sql = "
      SELECT u.id, u.email, u.full_name, u.contact_info, u.association_name, u.status, u.created_at,
             GROUP_CONCAT(p.plate_number ORDER BY p.plate_number SEPARATOR ', ') AS plates
      FROM operator_portal_users u
      LEFT JOIN operator_portal_user_plates p ON p.user_id=u.id
    ";
    $conds = [];
    $params = [];
    $types = '';

    if ($q !== '') {
      $conds[] = "(u.email LIKE ? OR u.full_name LIKE ? OR u.contact_info LIKE ? OR u.association_name LIKE ? OR p.plate_number LIKE ?)";
      $like = '%' . $q . '%';
      $params = array_merge($params, [$like,$like,$like,$like,$like]);
      $types .= 'sssss';
    }
    if ($status !== '') {
      $conds[] = "u.status=?";
      $params[] = $status;
      $types .= 's';
    }

    if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 1000";

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
        'full_name' => (string)($row['full_name'] ?? ''),
        'contact_info' => (string)($row['contact_info'] ?? ''),
        'association_name' => (string)($row['association_name'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'plates' => (string)($row['plates'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
      ];
    }
    if (!empty($stmt)) $stmt->close();
    ou_send(true, ['users' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ou_send(false, ['error' => 'method_not_allowed'], 405);
  }

  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true);
  if (!is_array($input)) ou_send(false, ['error' => 'invalid_json'], 400);

  $action = trim((string)($input['action'] ?? ''));
  $userId = (int)($input['user_id'] ?? 0);
  if ($userId <= 0) ou_send(false, ['error' => 'invalid_user_id'], 400);

  $stmtC = $db->prepare("SELECT 1 FROM operator_portal_users WHERE id=? LIMIT 1");
  if (!$stmtC) ou_send(false, ['error' => 'db_prepare_failed'], 500);
  $stmtC->bind_param('i', $userId);
  $stmtC->execute();
  $exists = (bool)($stmtC->get_result()->fetch_row());
  $stmtC->close();
  if (!$exists) ou_send(false, ['error' => 'not_found'], 404);

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
    if (!in_array($newStatus, ['Active','Inactive','Locked'], true)) ou_send(false, ['error' => 'invalid_status'], 400);
    $stmt = $db->prepare("UPDATE operator_portal_users SET status=? WHERE id=?");
    if (!$stmt) ou_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('si', $newStatus, $userId);
    $stmt->execute();
    $stmt->close();
    ou_send(true, ['message' => 'Status updated']);
  }

  if ($action === 'reset_password') {
    $temp = $generateTempPassword();
    $hash = password_hash($temp, PASSWORD_DEFAULT);
    if ($hash === false) ou_send(false, ['error' => 'hash_failed'], 500);
    $stmt = $db->prepare("UPDATE operator_portal_users SET password_hash=?, status='Active' WHERE id=?");
    if (!$stmt) ou_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();
    ou_send(true, ['message' => 'Temporary password generated.', 'temporary_password' => $temp]);
  }

  if ($action === 'delete') {
    $stmt = $db->prepare("DELETE FROM operator_portal_users WHERE id=?");
    if (!$stmt) ou_send(false, ['error' => 'db_prepare_failed'], 500);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    ou_send(true, ['message' => 'Account deleted']);
  }

  ou_send(false, ['error' => 'invalid_action'], 400);
} catch (Throwable $e) {
  ou_send(false, ['error' => $e->getMessage()], 500);
}
