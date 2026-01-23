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

$stmt = $db->prepare("SELECT
  t.id AS terminal_id,
  t.name AS terminal_name,
  tr.route_id AS route_ref,
  r.id AS route_db_id,
  COALESCE(NULLIF(r.route_name,''), r.route_id) AS route_name,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.origin,
  r.destination,
  r.fare,
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

