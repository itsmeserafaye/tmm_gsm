<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
require_any_permission(['module3.read','module3.issue','module3.analytics']);

$db = db();
header('Content-Type: application/json');

$q = trim((string)($_GET['q'] ?? ''));
$workflow = trim((string)($_GET['workflow_status'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit <= 0) $limit = 200;
if ($limit > 500) $limit = 500;

$allowedWorkflow = ['Pending','Verified','Closed'];
if ($workflow !== '' && !in_array($workflow, $allowedWorkflow, true)) $workflow = '';

$sql = "SELECT v.id, v.plate_number, v.violation_type, vt.description AS violation_desc,
               v.location, v.violation_date, v.workflow_status, v.remarks, v.evidence_path,
               v.operator_id, o.id AS operator_id2, COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS operator_name
        FROM violations v
        LEFT JOIN violation_types vt ON vt.violation_code=v.violation_type
        LEFT JOIN operators o ON o.id=v.operator_id";

$conds = [];
$params = [];
$types = '';

if ($workflow !== '') {
  $conds[] = "v.workflow_status=?";
  $params[] = $workflow;
  $types .= 's';
}
if ($q !== '') {
  $conds[] = "(v.plate_number LIKE ? OR v.violation_type LIKE ? OR vt.description LIKE ? OR v.location LIKE ? OR COALESCE(o.registered_name,o.name,o.full_name) LIKE ?)";
  $like = '%' . $q . '%';
  for ($i=0;$i<5;$i++) { $params[] = $like; $types .= 's'; }
}
if ($conds) $sql .= " WHERE " . implode(" AND ", $conds);
$sql .= " ORDER BY v.violation_date DESC, v.id DESC LIMIT " . (int)$limit;

$rows = [];
if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) error_response(500, 'db_prepare_failed');
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && ($r = $res->fetch_assoc())) {
    $rows[] = $r;
  }
  $stmt->close();
} else {
  $res = $db->query($sql);
  if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);
