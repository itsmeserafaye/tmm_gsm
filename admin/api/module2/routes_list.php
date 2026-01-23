<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

$res = $db->query("SELECT
  r.id,
  r.route_id,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.route_name,
  r.origin,
  r.destination,
  r.fare,
  COALESCE(r.authorized_units, r.max_vehicle_limit, 0) AS authorized_units,
  COALESCE(SUM(CASE WHEN fv.status='Active' THEN 1 ELSE 0 END), 0) AS active_units
FROM routes r
LEFT JOIN franchise_vehicles fv ON fv.route_id=r.id AND fv.status='Active'
GROUP BY r.id
ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
LIMIT 1000");

$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);

