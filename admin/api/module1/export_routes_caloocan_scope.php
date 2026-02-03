<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('reports.export');

$disabled = true;
if ($disabled) {
  header('Content-Type: application/json');
  http_response_code(410);
  echo json_encode(['ok' => false, 'error' => 'disabled']);
  exit;
}

$format = tmm_export_format();
tmm_send_export_headers($format, 'routes_caloocan_scope');

$scopeFilter = strtolower(trim((string)($_GET['scope'] ?? '')));
$allowedScopes = [
  'within_caloocan' => true,
  'caloocan_to_outside' => true,
  'outside_to_caloocan' => true,
  'outside_to_outside' => true,
];
if ($scopeFilter !== '' && !isset($allowedScopes[$scopeFilter])) $scopeFilter = '';

$caloocanKeys = [
  'caloocan',
  'monumento',
  'mcu',
  'bagong silang',
  'bagong barrio',
  'grace park',
  'sangandaan',
  'camarin',
  'deparo',
  'tala',
  'novaliches',
  'zabarte',
];

$isCaloocan = function (string $s) use ($caloocanKeys): bool {
  $v = strtolower(trim($s));
  if ($v === '') return false;
  foreach ($caloocanKeys as $k) {
    if (strpos($v, $k) !== false) return true;
  }
  return false;
};

$sql = "SELECT route_id, route_code, route_name, vehicle_type, origin, destination, via, status
        FROM routes
        ORDER BY status='Active' DESC, COALESCE(NULLIF(route_code,''), route_id) ASC, id DESC
        LIMIT 5000";
$res = $db->query($sql);

$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $origin = (string)($r['origin'] ?? '');
    $dest = (string)($r['destination'] ?? '');
    $o = $isCaloocan($origin);
    $d = $isCaloocan($dest);
    if ($o && $d) $scope = 'within_caloocan';
    elseif ($o && !$d) $scope = 'caloocan_to_outside';
    elseif (!$o && $d) $scope = 'outside_to_caloocan';
    else $scope = 'outside_to_outside';

    if ($scopeFilter !== '' && $scope !== $scopeFilter) continue;

    $rows[] = [
      'route_id' => $r['route_id'] ?? '',
      'route_code' => $r['route_code'] ?? '',
      'route_name' => $r['route_name'] ?? '',
      'vehicle_type' => $r['vehicle_type'] ?? '',
      'origin' => $origin,
      'destination' => $dest,
      'via' => $r['via'] ?? '',
      'status' => $r['status'] ?? '',
      'scope' => $scope,
    ];
  }
}

$headers = ['route_id','route_code','route_name','vehicle_type','origin','destination','via','status','scope'];
if ($format === 'excel') {
  tmm_export_write_excel($headers, $rows);
} else {
  tmm_export_write_csv($headers, $rows);
}

