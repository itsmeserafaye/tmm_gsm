<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

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

$res = $db->query("SELECT
  r.id,
  r.route_id,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.route_name,
  r.origin,
  r.destination,
  $fareMinExpr AS fare_min,
  $fareMaxExpr AS fare_max,
  CASE
    WHEN $fareMinExpr IS NULL THEN NULL
    WHEN ABS($fareMinExpr - $fareMaxExpr) < 0.001 THEN $fareMinExpr
    ELSE CONCAT($fareMinExpr, ' - ', $fareMaxExpr)
  END AS fare,
  COALESCE(r.authorized_units, r.max_vehicle_limit, 0) AS authorized_units,
  COALESCE(COUNT(DISTINCT v.id), 0) AS active_units
FROM routes r
LEFT JOIN franchise_applications fa ON fa.route_id=r.id AND fa.status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
LEFT JOIN vehicles v ON COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=fa.operator_id AND COALESCE(v.record_status,'') <> 'Archived'
GROUP BY r.id
ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
LIMIT 1000");

$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);
