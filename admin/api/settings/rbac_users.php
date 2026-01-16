<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
  $db = db();
  require_role(['SuperAdmin']);

  $q = trim((string)($_GET['q'] ?? ''));
  $status = trim((string)($_GET['status'] ?? ''));
  if ($status !== '' && !in_array($status, ['Active','Inactive','Locked'], true)) $status = '';

  $sql = "SELECT id, email, first_name, last_name, middle_name, suffix, employee_no, department, position_title, status, last_login_at, created_at
          FROM rbac_users
          WHERE id NOT IN (
            SELECT ur.user_id
            FROM rbac_user_roles ur
            JOIN rbac_roles r ON r.id=ur.role_id
            WHERE r.name='Commuter'
          )";
  $conds = [];
  $params = [];
  $types = '';

  if ($q !== '') {
    $conds[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR employee_no LIKE ? OR department LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sssss';
  }
  if ($status !== '') {
    $conds[] = "status = ?";
    $params[] = $status;
    $types .= 's';
  }
  if ($conds) $sql .= " AND " . implode(" AND ", $conds);
  $sql .= " ORDER BY created_at DESC LIMIT 500";

  if ($params) {
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception('db_prepare_failed');
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $db->query($sql);
  }

  $users = [];
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $userId = (int)$row['id'];
      $roles = [];
      $stmtR = $db->prepare("SELECT r.id, r.name FROM rbac_user_roles ur JOIN rbac_roles r ON r.id=ur.role_id WHERE ur.user_id=? ORDER BY r.name ASC");
      if ($stmtR) {
        $stmtR->bind_param('i', $userId);
        $stmtR->execute();
        $rs = $stmtR->get_result();
        while ($rs && ($rr = $rs->fetch_assoc())) {
          $roles[] = ['id' => (int)$rr['id'], 'name' => (string)$rr['name']];
        }
        $stmtR->close();
      }

      $users[] = [
        'id' => $userId,
        'email' => (string)$row['email'],
        'first_name' => (string)$row['first_name'],
        'last_name' => (string)$row['last_name'],
        'middle_name' => (string)($row['middle_name'] ?? ''),
        'suffix' => (string)($row['suffix'] ?? ''),
        'employee_no' => (string)($row['employee_no'] ?? ''),
        'department' => (string)($row['department'] ?? ''),
        'position_title' => (string)($row['position_title'] ?? ''),
        'status' => (string)$row['status'],
        'last_login_at' => $row['last_login_at'],
        'created_at' => $row['created_at'],
        'roles' => $roles,
      ];
    }
  }

  if (!empty($stmt)) $stmt->close();
  echo json_encode(['ok' => true, 'users' => $users]);
} catch (Exception $e) {
  if (defined('TMM_TEST')) throw $e;
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
