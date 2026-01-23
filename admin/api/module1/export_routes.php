<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';

$db = db();
require_permission('reports.export');

$format = tmm_export_format();
tmm_send_export_headers($format, 'routes_lptrp');

$q = trim((string)($_GET['q'] ?? ''));
$vehicleType = trim((string)($_GET['vehicle_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ? OR r.via LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssssss';
}
if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
  $conds[] = "r.vehicle_type=?";
  $params[] = $vehicleType;
  $types .= 's';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "r.status=?";
  $params[] = $status;
  $types .= 's';
}

$sql = "SELECT
  r.id,
  r.route_id,
  r.route_code,
  r.route_name,
  r.vehicle_type,
  r.origin,
  r.destination,
  r.via,
  r.structure,
  r.authorized_units,
  r.status,
  r.approved_by,
  r.approved_date,
  COALESCE(u.used_units,0) AS used_units,
  r.created_at,
  r.updated_at
FROM routes r
LEFT JOIN (
  SELECT route_id, COALESCE(SUM(vehicle_count),0) AS used_units
  FROM franchise_applications
  WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
  GROUP BY route_id
) u ON u.route_id=r.id
WHERE " . implode(' AND ', $conds) . "
ORDER BY r.status='Active' DESC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, r.id DESC";

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = ['id','route_id','route_code','route_name','vehicle_type','origin','destination','via','structure','authorized_units','used_units','remaining_units','status','approved_by','approved_date','created_at','updated_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  $au = (int)($r['authorized_units'] ?? 0);
  $used = (int)($r['used_units'] ?? 0);
  $rem = $au > 0 ? max(0, $au - $used) : 0;
  return [
    'id' => $r['id'] ?? '',
    'route_id' => $r['route_id'] ?? '',
    'route_code' => $r['route_code'] ?? '',
    'route_name' => $r['route_name'] ?? '',
    'vehicle_type' => $r['vehicle_type'] ?? '',
    'origin' => $r['origin'] ?? '',
    'destination' => $r['destination'] ?? '',
    'via' => $r['via'] ?? '',
    'structure' => $r['structure'] ?? '',
    'authorized_units' => $r['authorized_units'] ?? '',
    'used_units' => $used,
    'remaining_units' => $rem,
    'status' => $r['status'] ?? '',
    'approved_by' => $r['approved_by'] ?? '',
    'approved_date' => $r['approved_date'] ?? '',
    'created_at' => $r['created_at'] ?? '',
    'updated_at' => $r['updated_at'] ?? '',
  ];
});
