<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('reports.export');

$terminalName = trim((string)($_GET['terminal_name'] ?? ''));
if ($terminalName === '') {
  http_response_code(400);
  echo 'missing_terminal_name';
  exit;
}

$format = tmm_export_format();
$base = 'route_supply_' . $terminalName . '_' . date('Ymd_His');
tmm_send_export_headers($format, $base);

$sql = "SELECT ta.route_id, COALESCE(r.route_name, ta.route_id) AS route_name, COUNT(*) AS units
        FROM terminal_assignments ta
        LEFT JOIN routes r ON r.route_id = ta.route_id
        WHERE ta.terminal_name = ? AND (ta.status IS NULL OR ta.status = 'Authorized')
        GROUP BY ta.route_id, route_name
        ORDER BY units DESC, route_name ASC";
$stmt = $db->prepare($sql);
if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
$stmt->bind_param('s', $terminalName);
$stmt->execute();
$res = $stmt->get_result();

$headers = ['terminal_name','route_id','route_name','units'];
tmm_export_from_result($format, $headers, $res, function ($r) use ($terminalName) {
  return [
    'terminal_name' => $terminalName,
    'route_id' => $r['route_id'] ?? '',
    'route_name' => $r['route_name'] ?? '',
    'units' => $r['units'] ?? '',
  ];
});
$stmt->close();
