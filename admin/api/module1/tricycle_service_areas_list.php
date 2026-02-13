<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.read','module1.write']);

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
if ($status !== '' && !in_array($status, ['Active','Inactive'], true)) $status = '';

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = '%' . $q . '%';
  $conds[] = "(a.area_code LIKE ? OR a.area_name LIKE ? OR COALESCE(a.barangay,'') LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'sss';
}
if ($status !== '') {
  $conds[] = "a.status=?";
  $params[] = $status;
  $types .= 's';
}

$sql = "SELECT
  a.id,
  a.area_code,
  a.area_name,
  a.barangay,
  a.terminal_id,
  a.authorized_units,
  a.fare_min,
  a.fare_max,
  a.coverage_notes,
  a.status,
  a.created_at,
  a.updated_at,
  COALESCE(p.points_count,0) AS points_count,
  COALESCE(p.points, '') AS points
FROM tricycle_service_areas a
LEFT JOIN (
  SELECT area_id,
         COUNT(*) AS points_count,
         GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR ' • ') AS points
  FROM tricycle_service_area_points
  GROUP BY area_id
) p ON p.area_id=a.id
WHERE " . implode(' AND ', $conds) . "
ORDER BY a.status='Active' DESC, a.area_name ASC, a.id DESC
LIMIT 2000";

$rows = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
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
?>

