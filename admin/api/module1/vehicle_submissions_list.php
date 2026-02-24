<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

$status = trim((string)($_GET['status'] ?? 'Submitted'));
$q = trim((string)($_GET['q'] ?? ''));
$vehType = trim((string)($_GET['vehicle_type'] ?? ''));
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);
$operatorId = (int)($_GET['operator_id'] ?? 0);

$allowed = ['Submitted','Approved','Rejected'];
if (!in_array($status, $allowed, true)) $status = 'Submitted';

$sql = "SELECT s.submission_id, s.portal_user_id, s.plate_number, s.vehicle_type, s.engine_no, s.chassis_no, s.make, s.model, s.year_model,
               s.fuel_type, s.color, s.or_number, s.cr_number, s.cr_issue_date, s.registered_owner, s.cr_file_path, s.or_file_path, s.or_expiry_date,
               s.status, s.submitted_at, s.submitted_by_name, s.approved_at, s.approved_by_name, s.approval_remarks, s.vehicle_id,
               u.email AS portal_email, u.full_name AS portal_full_name, u.association_name AS portal_association_name,
               u.puv_operator_id AS operator_id
        FROM vehicle_record_submissions s
        LEFT JOIN operator_portal_users u ON s.portal_user_id = u.id";
$conds = [];
$params = [];
$types = '';

if ($status !== '') {
  $conds[] = "s.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(s.plate_number LIKE ? OR s.vehicle_type LIKE ? OR s.submitted_by_name LIKE ? OR s.cr_number LIKE ? OR s.chassis_no LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sssss';
}
if ($vehType !== '') {
  $conds[] = "s.vehicle_type=?";
  $params[] = $vehType;
  $types .= 's';
}
if ($month >= 1 && $month <= 12) {
  $conds[] = "MONTH(s.submitted_at)=?";
  $params[] = $month;
  $types .= 'i';
}
if ($year >= 2000 && $year <= 2100) {
  $conds[] = "YEAR(s.submitted_at)=?";
  $params[] = $year;
  $types .= 'i';
}
if ($operatorId > 0) {
  $conds[] = "COALESCE(u.puv_operator_id,0)=?";
  $params[] = $operatorId;
  $types .= 'i';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY s.submitted_at DESC, s.submission_id DESC LIMIT 300";

$rows = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) error_response(500, 'db_prepare_failed');
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);
