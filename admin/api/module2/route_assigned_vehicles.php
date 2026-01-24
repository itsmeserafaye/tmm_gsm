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
  v.id AS vehicle_id,
  v.plate_number,
  v.vehicle_type,
  v.status AS vehicle_status,
  COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), NULLIF(v.operator_name,''), '-') AS operator_name,
  COALESCE(o.operator_type, CASE WHEN COALESCE(NULLIF(v.coop_name,''), '') <> '' THEN 'Cooperative' ELSE 'Individual' END) AS operator_type,
  fa.franchise_ref_number,
  fa.status AS franchise_status
FROM (
  SELECT fa1.operator_id, fa1.franchise_ref_number, fa1.status
  FROM franchise_applications fa1
  JOIN (
    SELECT operator_id, MAX(application_id) AS max_id
    FROM franchise_applications
    WHERE route_id=? AND status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
    GROUP BY operator_id
  ) x ON x.max_id=fa1.application_id
) fa
JOIN vehicles v ON COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)=fa.operator_id
LEFT JOIN operators o ON o.id=fa.operator_id
WHERE COALESCE(v.record_status,'') <> 'Archived'
ORDER BY operator_name ASC, v.plate_number ASC");
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
