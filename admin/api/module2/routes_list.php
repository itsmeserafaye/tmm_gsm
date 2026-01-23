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
  COALESCE(COUNT(DISTINCT v.id), 0) AS active_units
FROM routes r
LEFT JOIN franchise_applications fa ON fa.route_id=r.id AND fa.status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
LEFT JOIN vehicles v ON COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=fa.operator_id AND COALESCE(v.record_status,'') <> 'Archived'
GROUP BY r.id
ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
LIMIT 1000");

$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['ok' => true, 'data' => $rows]);
