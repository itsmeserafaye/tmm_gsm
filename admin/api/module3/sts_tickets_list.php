<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_any_permission(['module3.read','module3.issue','module3.analytics']);

$db = db();
header('Content-Type: application/json');

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$allowed = ['Pending Payment','Paid','Closed'];
if ($status !== '' && !in_array($status, $allowed, true)) $status = '';

$sql = "SELECT t.sts_ticket_id, t.sts_ticket_no, t.issued_by, t.date_issued, t.fine_amount, t.status, t.verification_notes, t.ticket_scan_path,
               t.linked_violation_id,
               v.plate_number, v.violation_type, vt.description AS violation_desc, v.location, v.violation_date
        FROM sts_tickets t
        LEFT JOIN violations v ON v.id=t.linked_violation_id
        LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type";
$conds = [];
$params = [];
$types = '';
if ($status !== '') {
  $conds[] = "t.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($from !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $from)) {
  $conds[] = "COALESCE(t.date_issued, DATE(t.created_at)) >= ?";
  $params[] = $from;
  $types .= 's';
}
if ($to !== '' && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $to)) {
  $conds[] = "COALESCE(t.date_issued, DATE(t.created_at)) <= ?";
  $params[] = $to;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(t.sts_ticket_no LIKE ? OR t.issued_by LIKE ? OR v.plate_number LIKE ? OR vt.description LIKE ?)";
  $like = '%' . $q . '%';
  for ($i=0;$i<4;$i++) { $params[] = $like; $types .= 's'; }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY COALESCE(t.date_issued, DATE(t.created_at)) DESC, t.sts_ticket_id DESC LIMIT " . (int)$limit;

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
