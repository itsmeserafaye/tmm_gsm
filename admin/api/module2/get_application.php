<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.view','module2.franchises.manage']);

$appId = (int)($_GET['application_id'] ?? 0);
if ($appId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_application_id']);
  exit;
}

$sql = "SELECT fa.*,
               COALESCE(NULLIF(o.name,''), o.full_name) AS operator_name,
               o.operator_type,
               o.status AS operator_status,
               COALESCE(NULLIF(r.route_code,''), r.route_id, sa.area_code) AS route_code,
               COALESCE(NULLIF(r.route_name,''), sa.area_name, '') AS route_name,
               COALESCE(r.origin, sap.points, '') AS origin,
               COALESCE(r.destination, '') AS destination,
               r.structure, r.distance_km, r.authorized_units, r.status AS route_status,
               sa.status AS service_area_status,
               f.ltfrb_ref_no, f.decision_order_no, f.authority_type, f.issue_date, f.expiry_date AS franchise_expiry_date, f.status AS franchise_status
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id
        LEFT JOIN tricycle_service_areas sa ON sa.id=fa.service_area_id
        LEFT JOIN (
          SELECT area_id, GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR ' • ') AS points
          FROM tricycle_service_area_points
          GROUP BY area_id
        ) sap ON sap.area_id=sa.id
        LEFT JOIN franchises f ON f.application_id=fa.application_id
        WHERE fa.application_id=? LIMIT 1";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('i', $appId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'application_not_found']);
  exit;
}

$hasFranchises = (bool)($db->query("SHOW TABLES LIKE 'franchises'")?->fetch_row());
if ($hasFranchises) {
  @$db->query("UPDATE franchises SET status='Expired' WHERE status='Active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
  @$db->query("UPDATE franchise_applications fa
               JOIN franchises f ON f.application_id=fa.application_id
               SET fa.status='Expired'
               WHERE f.status='Expired'
                 AND fa.status IN ('PA Issued','CPC Issued','LTFRB-Approved','Approved')");
  if ($row && in_array((string)($row['status'] ?? ''), ['PA Issued','CPC Issued','LTFRB-Approved','Approved'], true)) {
    $resSt = $db->query("SELECT status FROM franchise_applications WHERE application_id=" . (int)$appId . " LIMIT 1");
    if ($resSt && ($stRow = $resSt->fetch_assoc())) $row['status'] = (string)($stRow['status'] ?? $row['status']);
  }
}

echo json_encode(['ok' => true, 'data' => $row]);
