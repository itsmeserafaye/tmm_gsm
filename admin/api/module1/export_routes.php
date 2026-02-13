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
$routeCategory = trim((string)($_GET['route_category'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes' AND COLUMN_NAME IN ('fare_min','fare_max')");
$hasFareMin = false;
$hasFareMax = false;
if ($colRes) {
  while ($c = $colRes->fetch_assoc()) {
    $cn = (string)($c['COLUMN_NAME'] ?? '');
    if ($cn === 'fare_min') $hasFareMin = true;
    if ($cn === 'fare_max') $hasFareMax = true;
  }
}

$conds = ["1=1"];
$params = [];
$types = '';
if ($q !== '') {
  $like = "%$q%";
  $conds[] = "(r.route_id LIKE ? OR r.route_code LIKE ? OR r.route_name LIKE ? OR r.origin LIKE ? OR r.destination LIKE ? OR r.via LIKE ?)";
  $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
  $types .= 'ssssss';
}
if ($status !== '' && $status !== 'Status') {
  $conds[] = "r.status=?";
  $params[] = $status;
  $types .= 's';
}
if ($routeCategory !== '') {
  $conds[] = "r.route_category=?";
  $params[] = $routeCategory;
  $types .= 's';
}

$hasAlloc = false;
$tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
if ($tAlloc && $tAlloc->fetch_row()) $hasAlloc = true;

if ($hasAlloc) {
  if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
    $conds[] = "EXISTS (SELECT 1 FROM route_vehicle_types aa WHERE aa.route_id=r.id AND aa.vehicle_type=? AND aa.vehicle_type<>'Tricycle')";
    $params[] = $vehicleType;
    $types .= 's';
  }
  $sql = "SELECT
    r.id,
    r.route_id,
    r.route_code,
    r.route_name,
    r.route_category,
    r.origin,
    r.destination,
    r.via,
    r.structure,
    r.status,
    a.vehicle_type,
    a.authorized_units,
    a.fare_min,
    a.fare_max,
    a.status AS allocation_status,
    COALESCE(u.used_units,0) AS used_units,
    r.created_at,
    r.updated_at
  FROM routes r
  LEFT JOIN route_vehicle_types a ON a.route_id=r.id AND a.vehicle_type<>'Tricycle'
  LEFT JOIN (
    SELECT route_id, vehicle_type, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved')
    GROUP BY route_id, vehicle_type
  ) u ON u.route_id=r.id AND u.vehicle_type=a.vehicle_type
  WHERE " . implode(' AND ', $conds) . "
  ORDER BY r.status='Active' DESC, COALESCE(NULLIF(r.route_code,''), r.route_id) ASC, a.vehicle_type ASC, r.id DESC";
} else {
  if ($vehicleType !== '' && $vehicleType !== 'Vehicle type') {
    $conds[] = "r.vehicle_type=?";
    $params[] = $vehicleType;
    $types .= 's';
  }
  $sql = "SELECT
    r.id,
    r.route_id,
    r.route_code,
    r.route_name,
    r.vehicle_type,
    r.route_category,
    r.origin,
    r.destination,
    r.via,
    r.structure,
    r.authorized_units,
    r.fare,
    " . ($hasFareMin ? "r.fare_min" : "NULL") . " AS fare_min,
    " . ($hasFareMax ? "r.fare_max" : "NULL") . " AS fare_max,
    r.status,
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
}

if ($params) {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo 'db_prepare_failed'; exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}

$headers = $hasAlloc
  ? ['id','route_id','route_code','route_name','route_category','origin','destination','via','structure','corridor_status','vehicle_type','allocation_status','authorized_units','used_units','remaining_units','fare_min','fare_max','created_at','updated_at']
  : ['id','route_id','route_code','route_name','route_category','vehicle_type','origin','destination','via','structure','authorized_units','used_units','remaining_units','fare_min','fare_max','status','created_at','updated_at'];
tmm_export_from_result($format, $headers, $res, function ($r) use ($hasAlloc) {
  $au = (int)($r['authorized_units'] ?? 0);
  $used = (int)($r['used_units'] ?? 0);
  $rem = $au > 0 ? max(0, $au - $used) : 0;
  $fareMin = $r['fare_min'] === null || $r['fare_min'] === '' ? null : (float)$r['fare_min'];
  $fareMax = $r['fare_max'] === null || $r['fare_max'] === '' ? null : (float)$r['fare_max'];
  if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
  $cat = $r['route_category'] ?? '';
  if ($hasAlloc) {
    return [
      'id' => $r['id'] ?? '',
      'route_id' => $r['route_id'] ?? '',
      'route_code' => $r['route_code'] ?? '',
      'route_name' => $r['route_name'] ?? '',
      'route_category' => $cat,
      'origin' => $r['origin'] ?? '',
      'destination' => $r['destination'] ?? '',
      'via' => $r['via'] ?? '',
      'structure' => $r['structure'] ?? '',
      'corridor_status' => $r['status'] ?? '',
      'vehicle_type' => $r['vehicle_type'] ?? '',
      'allocation_status' => $r['allocation_status'] ?? '',
      'authorized_units' => $r['authorized_units'] ?? '',
      'used_units' => $used,
      'remaining_units' => $rem,
      'fare_min' => $fareMin === null ? '' : $fareMin,
      'fare_max' => $fareMax === null ? '' : $fareMax,
      'created_at' => $r['created_at'] ?? '',
      'updated_at' => $r['updated_at'] ?? '',
    ];
  }
  $fare = $r['fare'] === null || $r['fare'] === '' ? null : (float)$r['fare'];
  if ($fareMin === null && $fare !== null) $fareMin = $fare;
  if ($fareMax === null && $fare !== null) $fareMax = $fare;
  if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
  return [
    'id' => $r['id'] ?? '',
    'route_id' => $r['route_id'] ?? '',
    'route_code' => $r['route_code'] ?? '',
    'route_name' => $r['route_name'] ?? '',
    'route_category' => $cat,
    'vehicle_type' => $r['vehicle_type'] ?? '',
    'origin' => $r['origin'] ?? '',
    'destination' => $r['destination'] ?? '',
    'via' => $r['via'] ?? '',
    'structure' => $r['structure'] ?? '',
    'authorized_units' => $r['authorized_units'] ?? '',
    'used_units' => $used,
    'remaining_units' => $rem,
    'fare_min' => $fareMin === null ? '' : $fareMin,
    'fare_max' => $fareMax === null ? '' : $fareMax,
    'status' => $r['status'] ?? '',
    'created_at' => $r['created_at'] ?? '',
    'updated_at' => $r['updated_at'] ?? '',
  ];
});
