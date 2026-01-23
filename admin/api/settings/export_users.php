<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_role(['SuperAdmin']);

$format = tmm_export_format();
tmm_send_export_headers($format, 'rbac_users');

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, ['Active','Inactive','Locked'], true)) $status = '';

$sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.middle_name, u.suffix, u.employee_no, u.department, u.position_title, u.status, u.last_login_at, u.created_at,
               GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
        FROM rbac_users u
        LEFT JOIN rbac_user_roles ur ON ur.user_id=u.id
        LEFT JOIN rbac_roles r ON r.id=ur.role_id
        WHERE u.id NOT IN (
          SELECT ur2.user_id
          FROM rbac_user_roles ur2
          JOIN rbac_roles r2 ON r2.id=ur2.role_id
          WHERE r2.name='Commuter'
        )";

$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $like = '%' . $q . '%';
  $conds[] = "(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_no LIKE ? OR u.department LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sssss';
}
if ($status !== '') {
  $conds[] = "u.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($conds) $sql .= " AND " . implode(" AND ", $conds);
$sql .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 5000";

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['id','email','first_name','last_name','middle_name','suffix','employee_no','department','position_title','roles','status','last_login_at','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'id' => $r['id'] ?? '',
    'email' => $r['email'] ?? '',
    'first_name' => $r['first_name'] ?? '',
    'last_name' => $r['last_name'] ?? '',
    'middle_name' => $r['middle_name'] ?? '',
    'suffix' => $r['suffix'] ?? '',
    'employee_no' => $r['employee_no'] ?? '',
    'department' => $r['department'] ?? '',
    'position_title' => $r['position_title'] ?? '',
    'roles' => $r['roles'] ?? '',
    'status' => $r['status'] ?? '',
    'last_login_at' => $r['last_login_at'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
