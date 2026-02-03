<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module5.manage_terminal','module5.read']);

$terminalId = (int)($_GET['terminal_id'] ?? 0);
if ($terminalId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_id']);
  exit;
}

$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('fare_min','fare_max')");
$hasFareMin = false;
$hasFareMax = false;
if ($colRes) {
  while ($c = $colRes->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'fare_min') $hasFareMin = true;
    if ($cn === 'fare_max') $hasFareMax = true;
  }
}
$fareMinExpr = $hasFareMin ? "COALESCE(r.fare_min, r.fare)" : "r.fare";
$fareMaxExpr = $hasFareMax ? "COALESCE(r.fare_max, r.fare)" : "r.fare";

$stmt = $db->prepare("SELECT
  t.id AS terminal_id,
  t.name AS terminal_name,
  tr.route_id AS route_ref,
  r.id AS route_db_id,
  COALESCE(NULLIF(r.route_name,''), r.route_id) AS route_name,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.origin,
  r.destination,
  $fareMinExpr AS fare_min,
  $fareMaxExpr AS fare_max,
  CASE
    WHEN $fareMinExpr IS NULL THEN NULL
    WHEN ABS($fareMinExpr - $fareMaxExpr) < 0.001 THEN $fareMinExpr
    ELSE CONCAT($fareMinExpr, ' - ', $fareMaxExpr)
  END AS fare,
  r.vehicle_type,
  r.status
FROM terminals t
JOIN terminal_routes tr ON tr.terminal_id=t.id
LEFT JOIN routes r ON r.route_id=tr.route_id OR r.route_code=tr.route_id
WHERE t.id=?
ORDER BY COALESCE(NULLIF(r.route_name,''), tr.route_id) ASC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $terminalId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) {
  $rows[] = $r;
}
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
