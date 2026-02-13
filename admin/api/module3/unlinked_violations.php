<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_any_permission(['module3.read','module3.issue','module3.analytics']);

$db = db();
header('Content-Type: application/json');

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$hasLinked = false;
$t = $db->query("SHOW COLUMNS FROM sts_tickets LIKE 'linked_violation_id'");
if ($t && $t->num_rows > 0) $hasLinked = true;

$sql = "SELECT v.id,
               v.plate_number,
               v.violation_type,
               vt.description AS violation_desc,
               COALESCE(vt.fine_amount, 0) AS fine_amount,
               v.location,
               v.violation_date,
               v.workflow_status
        FROM violations v
        LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type
        WHERE 1=1";

if ($hasLinked) {
  $sql .= " AND v.id NOT IN (SELECT DISTINCT linked_violation_id FROM sts_tickets WHERE linked_violation_id IS NOT NULL AND linked_violation_id>0)";
}

$params = [];
$types = '';
if ($q !== '') {
  $like = '%' . $q . '%';
  $sql .= " AND (v.plate_number LIKE ? OR v.violation_type LIKE ? OR vt.description LIKE ? OR v.location LIKE ?)";
  for ($i = 0; $i < 4; $i++) { $params[] = $like; $types .= 's'; }
}

$sql .= " ORDER BY v.violation_date DESC, v.id DESC LIMIT " . (int)$limit;

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
?>

