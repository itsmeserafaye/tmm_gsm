<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$terminalName = trim((string)($_GET['terminal_name'] ?? ''));
if ($terminalName === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_terminal_name']);
  exit;
}

$sql = "SELECT ta.route_id, COALESCE(r.route_name, ta.route_id) AS route_name, COUNT(*) AS units
        FROM terminal_assignments ta
        LEFT JOIN routes r ON r.route_id = ta.route_id
        WHERE ta.terminal_name = ? AND (ta.status IS NULL OR ta.status = 'Authorized')
        GROUP BY ta.route_id, route_name
        ORDER BY units DESC, route_name ASC";
$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmt->bind_param('s', $terminalName);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
$total = 0;
while ($res && ($r = $res->fetch_assoc())) {
  $u = (int)($r['units'] ?? 0);
  $total += $u;
  $rows[] = [
    'route_id' => (string)($r['route_id'] ?? ''),
    'route_name' => (string)($r['route_name'] ?? ''),
    'units' => $u,
  ];
}
$stmt->close();

echo json_encode([
  'ok' => true,
  'terminal_name' => $terminalName,
  'total_units' => $total,
  'routes' => $rows,
]);

