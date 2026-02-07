<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_permission('module1.write');

$db = db();
header('Content-Type: application/json');

$status = trim((string)($_GET['status'] ?? 'Pending'));
$q = trim((string)($_GET['q'] ?? ''));

$allowed = ['Pending','Approved','Rejected'];
if (!in_array($status, $allowed, true)) $status = 'Pending';

$sql = "SELECT request_id, portal_user_id, plate_number, requested_operator_id, status, submitted_at, submitted_by_name,
               reviewed_by_name, reviewed_at, remarks
        FROM vehicle_link_requests";
$conds = [];
$params = [];
$types = '';

if ($status !== '') {
  $conds[] = "status=?";
  $params[] = $status;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(plate_number LIKE ? OR submitted_by_name LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like;
  $params[] = $like;
  $types .= 'ss';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY submitted_at DESC, request_id DESC LIMIT 300";

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
