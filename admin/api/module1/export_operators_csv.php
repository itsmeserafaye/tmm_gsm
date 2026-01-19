<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="operators.csv"');

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

$out = fopen('php://output', 'w');
fputcsv($out, ['operator_id','operator_type','display_name','full_name','name','address','contact_no','email','status','created_at']);
while ($res && ($row = $res->fetch_assoc())) {
  fputcsv($out, [
    $row['operator_id'],
    $row['operator_type'],
    $row['display_name'],
    $row['full_name'],
    $row['name'],
    $row['address'],
    $row['contact_no'],
    $row['email'],
    $row['status'],
    $row['created_at'],
  ]);
}
fclose($out);

