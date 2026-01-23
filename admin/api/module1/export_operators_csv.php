<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');
$format = tmm_export_format();
tmm_send_export_headers($format, 'operators');

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['operator_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT id AS operator_id, operator_type, COALESCE(NULLIF(name,''), full_name) AS display_name, full_name, name, address, contact_no, email, status, created_at FROM operators";
$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(name LIKE ? OR full_name LIKE ? OR contact_no LIKE ? OR email LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'ssss';
}
if ($type !== '') {
  $conds[] = "operator_type=?";
  $params[] = $type;
  $types .= 's';
}
if ($status !== '') {
  $conds[] = "status=?";
  $params[] = $status;
  $types .= 's';
}

if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY created_at DESC";

if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['operator_id','operator_type','display_name','full_name','name','address','contact_no','email','status','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'operator_id' => $r['operator_id'] ?? '',
    'operator_type' => $r['operator_type'] ?? '',
    'display_name' => $r['display_name'] ?? '',
    'full_name' => $r['full_name'] ?? '',
    'name' => $r['name'] ?? '',
    'address' => $r['address'] ?? '',
    'contact_no' => $r['contact_no'] ?? '',
    'email' => $r['email'] ?? '',
    'status' => $r['status'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
