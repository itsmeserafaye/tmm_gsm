<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

$status = trim((string)($_GET['status'] ?? 'Submitted'));
$q = trim((string)($_GET['q'] ?? ''));

$allowed = ['Submitted','Approved','Rejected'];
if (!in_array($status, $allowed, true)) $status = 'Submitted';

$sql = "SELECT submission_id, portal_user_id, plate_number, vehicle_type, engine_no, chassis_no, make, model, year_model,
               or_number, cr_number, cr_issue_date, registered_owner, cr_file_path, or_file_path, or_expiry_date,
               status, submitted_at, submitted_by_name, approved_at, approved_by_name, approval_remarks, vehicle_id
        FROM vehicle_record_submissions";
$conds = [];
$params = [];
$types = '';

if ($status !== '') {
  $conds[] = "status=?";
  $params[] = $status;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(plate_number LIKE ? OR vehicle_type LIKE ? OR submitted_by_name LIKE ? OR cr_number LIKE ? OR chassis_no LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= 'sssss';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY submitted_at DESC, submission_id DESC LIMIT 300";

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
