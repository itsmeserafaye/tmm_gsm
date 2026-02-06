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
               r.route_id AS route_code,
               r.origin, r.destination, r.structure, r.distance_km, r.authorized_units, r.status AS route_status,
               f.ltfrb_ref_no, f.decision_order_no, f.expiry_date AS franchise_expiry_date, f.status AS franchise_status
        FROM franchise_applications fa
        LEFT JOIN operators o ON o.id=fa.operator_id
        LEFT JOIN routes r ON r.id=fa.route_id
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

if ($row && isset($row['endorsed_until']) && in_array((string)($row['status'] ?? ''), ['Endorsed','LGU-Endorsed'], true)) {
  $eu = (string)($row['endorsed_until'] ?? '');
  if ($eu !== '' && strtotime($eu) !== false && strtotime($eu) < strtotime(date('Y-m-d'))) {
    @$db->query("UPDATE franchise_applications SET status='Expired' WHERE application_id=" . (int)$appId . " AND status IN ('Endorsed','LGU-Endorsed')");
    $row['status'] = 'Expired';
  }
}

echo json_encode(['ok' => true, 'data' => $row]);
