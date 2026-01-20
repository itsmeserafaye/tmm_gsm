<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../../includes/rbac.php';

header('Content-Type: application/json');

function cu_send_users(bool $ok, $payload = null, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => $ok] + (is_array($payload) ? $payload : ['data' => $payload]));
  exit;
}

try {
  $db = db();
  // Ensure schema is up to date (this handles the unique constraint fix if possible)
  rbac_ensure_schema($db); 
  
  require_role(['SuperAdmin', 'Admin', 'Admin / Transport Officer']);

  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    cu_send_users(false, ['error' => 'method_not_allowed'], 405);
  }

  $q = trim((string)($_GET['q'] ?? ''));
  $status = trim((string)($_GET['status'] ?? ''));

  // 1. Fetch Users (Base info)
  $sql = "
    SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, u.employee_no, 
           u.department, u.position_title, u.status, u.last_login_at
    FROM rbac_users u
    LEFT JOIN rbac_user_roles ur ON ur.user_id = u.id
    LEFT JOIN rbac_roles r ON r.id = ur.role_id
    WHERE 1=1
  ";
  
  // Filter logic: Exclude pure commuters (users who ONLY have 'Commuter' role)
  // We do this by ensuring they have at least one role that is NOT 'Commuter' OR they have no roles (yet).
  // Actually, simpler: "WHERE u.id IN (SELECT user_id FROM rbac_user_roles ur2 JOIN rbac_roles r2 ON r2.id=ur2.role_id WHERE r2.name != 'Commuter')"
  // OR show everyone, and UI filters? No, backend should filter.
  // The logic "Exclude pure commuters" means:
  // Show if (Count of Non-Commuter Roles > 0) OR (Count of Roles == 0)
  
  // Efficient approach:
  $sql .= " AND (
    NOT EXISTS (SELECT 1 FROM rbac_user_roles ur_c JOIN rbac_roles r_c ON r_c.id = ur_c.role_id WHERE ur_c.user_id = u.id)
    OR
    EXISTS (SELECT 1 FROM rbac_user_roles ur_ok JOIN rbac_roles r_ok ON r_ok.id = ur_ok.role_id WHERE ur_ok.user_id = u.id AND r_ok.name <> 'Commuter')
  )";

  $params = [];
  $types = '';

  if ($q !== '') {
    $sql .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_no LIKE ?)";
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
  }

  if ($status !== '' && $status !== 'All Status') {
    $sql .= " AND u.status = ?";
    $params[] = $status;
    $types .= 's';
  }

  $sql .= " ORDER BY u.created_at DESC LIMIT 500";

  $stmt = $db->prepare($sql);
  if ($params) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  
  $users = [];
  $userIds = [];
  while ($row = $res->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['roles'] = []; // Init empty
    $users[$row['id']] = $row;
    $userIds[] = $row['id'];
  }
  $stmt->close();

  // 2. Fetch Roles for these users
  if (!empty($userIds)) {
    $idList = implode(',', $userIds);
    // Fetch role names
    $rSql = "
      SELECT ur.user_id, r.id as role_id, r.name, r.description
      FROM rbac_user_roles ur
      JOIN rbac_roles r ON r.id = ur.role_id
      WHERE ur.user_id IN ($idList)
      ORDER BY r.name ASC
    ";
    $rRes = $db->query($rSql);
    while ($rRow = $rRes->fetch_assoc()) {
      $uid = (int)$rRow['user_id'];
      if (isset($users[$uid])) {
        $users[$uid]['roles'][] = [
          'id' => (int)$rRow['role_id'],
          'name' => $rRow['name'],
          'description' => $rRow['description']
        ];
      }
    }
  }

  cu_send_users(true, ['users' => array_values($users)]);

} catch (Throwable $e) {
  cu_send_users(false, ['error' => $e->getMessage()], 500);
}
