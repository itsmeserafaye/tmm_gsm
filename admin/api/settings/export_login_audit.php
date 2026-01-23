<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_role(['SuperAdmin']);

$format = tmm_export_format();
tmm_send_export_headers($format, 'rbac_login_audit');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT id, user_id, email, ok, ip_address, user_agent, created_at FROM rbac_login_audit WHERE 1=1";
$params = [];
$types = '';
if ($q !== '') {
  $like = '%' . $q . '%';
  $sql .= " AND (email LIKE ? OR ip_address LIKE ?)";
  $params[] = $like; $params[] = $like;
  $types .= 'ss';
}
$sql .= " ORDER BY created_at DESC LIMIT 5000";

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['id','user_id','email','ok','ip_address','user_agent','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'id' => $r['id'] ?? '',
    'user_id' => $r['user_id'] ?? '',
    'email' => $r['email'] ?? '',
    'ok' => $r['ok'] ?? '',
    'ip_address' => $r['ip_address'] ?? '',
    'user_agent' => $r['user_agent'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
