<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

$routeId = (int)($_GET['route_id'] ?? 0);
if ($routeId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_route_id']);
  exit;
}

$stmt = $db->prepare("SELECT
  fv.fv_id,
  fv.status AS assignment_status,
  fv.assigned_at,
  fv.franchise_ref_number,
  fv.franchise_id,
  v.id AS vehicle_id,
  v.plate_number,
  v.vehicle_type,
  COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '-') AS operator_name,
  CASE WHEN COALESCE(NULLIF(v.coop_name,''), '') <> '' THEN 'Cooperative' ELSE 'Individual' END AS operator_type
FROM franchise_vehicles fv
JOIN vehicles v ON v.id=fv.vehicle_id
LEFT JOIN franchises f ON f.franchise_id=fv.franchise_id
LEFT JOIN franchise_applications fa ON fa.application_id=f.application_id OR fa.franchise_ref_number=fv.franchise_ref_number
LEFT JOIN operators o ON o.id=fa.operator_id
WHERE COALESCE(fv.route_id, fa.route_id)=?
ORDER BY fv.status ASC, fv.assigned_at DESC, v.plate_number ASC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $routeId);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);

