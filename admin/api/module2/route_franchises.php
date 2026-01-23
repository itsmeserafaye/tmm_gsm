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
  f.franchise_id,
  fa.application_id,
  fa.franchise_ref_number,
  fa.operator_id,
  COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,'')) AS operator_name,
  fa.status
FROM franchise_applications fa
LEFT JOIN franchises f ON f.application_id=fa.application_id
LEFT JOIN operators o ON o.id=fa.operator_id
WHERE fa.route_id=? AND fa.status IN ('Approved','LTFRB-Approved')
ORDER BY COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,''), fa.franchise_ref_number) ASC");
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

