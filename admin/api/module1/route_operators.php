<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module1.read','module1.write','module2.franchises.manage']);

$routeDbId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
if ($routeDbId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_route_id']);
  exit;
}

$sql = "SELECT
  o.id AS operator_id,
  o.operator_type,
  COALESCE(NULLIF(o.registered_name,''), NULLIF(o.name,''), o.full_name) AS operator_name,
  COALESCE(SUM(fa.vehicle_count),0) AS total_units,
  GROUP_CONCAT(DISTINCT fa.status ORDER BY fa.status SEPARATOR ', ') AS statuses
FROM franchise_applications fa
JOIN operators o ON o.id=fa.operator_id
WHERE fa.route_id=?
  AND fa.status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
GROUP BY o.id, o.operator_type, operator_name
ORDER BY total_units DESC, operator_name ASC";

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $routeDbId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'route_id' => $routeDbId, 'operators' => $rows]);

