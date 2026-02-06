<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history','module2.franchises.manage']);

$hasEndUntil = (bool)($db->query("SHOW COLUMNS FROM franchise_applications LIKE 'endorsed_until'")?->num_rows);
if ($hasEndUntil) {
  @$db->query("UPDATE franchise_applications
               SET status='Expired'
               WHERE status IN ('Endorsed','LGU-Endorsed')
                 AND endorsed_until IS NOT NULL
                 AND endorsed_until < CURDATE()");
}

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$excludeStatus = trim((string)($_GET['exclude_status'] ?? ''));
$limit = (int)($_GET['limit'] ?? 100);
if ($limit <= 0) $limit = 100;
if ($limit > 500) $limit = 500;

$sql = "SELECT fa.application_id, fa.franchise_ref_number, fa.operator_id,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               fa.route_id,
               r.route_id AS route_code,
               r.origin, r.destination,
               fa.vehicle_count, fa.representative_name,
               fa.status, fa.submitted_at, fa.endorsed_at, fa.endorsed_until, fa.approved_at
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id";
$conds = [];
$params = [];
$types = '';

if ($q !== '') {
  $conds[] = "(fa.franchise_ref_number LIKE ? OR COALESCE(NULLIF(o.name,''), o.full_name) LIKE ? OR r.route_id LIKE ? OR r.origin LIKE ? OR r.destination LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types .= 'sssss';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "fa.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($excludeStatus !== '') {
  $conds[] = "fa.status<>?";
  $params[] = $excludeStatus;
  $types .= 's';
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY fa.submitted_at DESC LIMIT ?";
$params[] = $limit;
$types .= 'i';

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
