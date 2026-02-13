<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

  $useAlloc = false;
  $tAlloc = $db->query("SHOW TABLES LIKE 'route_vehicle_types'");
  if ($tAlloc && $tAlloc->num_rows > 0) {
    $cAlloc = $db->query("SELECT COUNT(*) AS c FROM route_vehicle_types");
    if ($cAlloc && (int)($cAlloc->fetch_assoc()['c'] ?? 0) > 0) $useAlloc = true;
  }

if ($useAlloc) {
  $res = $db->query("SELECT
  r.id AS route_db_id,
  r.route_id,
  COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
  r.route_name,
  r.origin,
  r.destination,
  a.vehicle_type,
  a.fare_min,
  a.fare_max,
  CASE
    WHEN a.fare_min IS NULL AND a.fare_max IS NULL THEN NULL
    WHEN a.fare_max IS NULL OR ABS(a.fare_min - a.fare_max) < 0.001 THEN COALESCE(a.fare_min, a.fare_max)
    ELSE CONCAT(a.fare_min, ' - ', a.fare_max)
  END AS fare,
  COALESCE(a.authorized_units, 0) AS authorized_units,
  COALESCE(u.used_units, 0) AS used_units,
  GREATEST(COALESCE(a.authorized_units,0) - COALESCE(u.used_units,0), 0) AS remaining_units
FROM routes r
JOIN route_vehicle_types a ON a.route_id=r.id AND a.status='Active' AND a.vehicle_type<>'Tricycle'
LEFT JOIN (
  SELECT route_id, vehicle_type, COALESCE(SUM(vehicle_count),0) AS used_units
  FROM franchise_applications
  WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
  GROUP BY route_id, vehicle_type
) u ON u.route_id=r.id AND u.vehicle_type=a.vehicle_type
WHERE r.status='Active'
ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC, a.vehicle_type ASC
LIMIT 2000");
} else {
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
  $fareMinExpr = $hasFareMin ? "COALESCE(r.fare_min, r.fare)" : "r.fare";
  $fareMaxExpr = $hasFareMax ? "COALESCE(r.fare_max, r.fare)" : "r.fare";

  $res = $db->query("SELECT
    r.id AS route_db_id,
    r.route_id,
    COALESCE(NULLIF(r.route_code,''), r.route_id) AS route_code,
    r.route_name,
    r.origin,
    r.destination,
    COALESCE(r.vehicle_type,'') AS vehicle_type,
    $fareMinExpr AS fare_min,
    $fareMaxExpr AS fare_max,
    CASE
      WHEN $fareMinExpr IS NULL THEN NULL
      WHEN ABS($fareMinExpr - $fareMaxExpr) < 0.001 THEN $fareMinExpr
      ELSE CONCAT($fareMinExpr, ' - ', $fareMaxExpr)
    END AS fare,
    COALESCE(r.authorized_units, r.max_vehicle_limit, 0) AS authorized_units,
    COALESCE(SUM(fa.vehicle_count),0) AS used_units,
    GREATEST(COALESCE(r.authorized_units, r.max_vehicle_limit, 0) - COALESCE(SUM(fa.vehicle_count),0), 0) AS remaining_units
  FROM routes r
  LEFT JOIN franchise_applications fa ON fa.route_id=r.id AND fa.status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
  WHERE r.status='Active' AND COALESCE(r.vehicle_type,'')<>'Tricycle'
  GROUP BY r.id
  ORDER BY COALESCE(NULLIF(r.route_name,''), COALESCE(NULLIF(r.route_code,''), r.route_id)) ASC
  LIMIT 2000");
}

$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $r['kind'] = 'route';
    $r['id'] = $r['route_db_id'];
    $rows[] = $r;
  }
}

if ($db->query("SHOW TABLES LIKE 'tricycle_service_areas'") && ($db->query("SHOW TABLES LIKE 'tricycle_service_areas'")->num_rows ?? 0) > 0) {
  $resA = $db->query("SELECT
    a.id AS service_area_id,
    a.area_code,
    a.area_name,
    a.barangay,
    a.fare_min,
    a.fare_max,
    COALESCE(a.authorized_units,0) AS authorized_units,
    COALESCE(u.used_units,0) AS used_units,
    GREATEST(COALESCE(a.authorized_units,0) - COALESCE(u.used_units,0), 0) AS remaining_units,
    COALESCE(p.points, '') AS points
  FROM tricycle_service_areas a
  LEFT JOIN (
    SELECT service_area_id, COALESCE(SUM(vehicle_count),0) AS used_units
    FROM franchise_applications
    WHERE status IN ('Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
      AND COALESCE(vehicle_type,'')='Tricycle'
      AND service_area_id IS NOT NULL
    GROUP BY service_area_id
  ) u ON u.service_area_id=a.id
  LEFT JOIN (
    SELECT area_id, GROUP_CONCAT(point_name ORDER BY sort_order ASC, point_id ASC SEPARATOR ' • ') AS points
    FROM tricycle_service_area_points
    GROUP BY area_id
  ) p ON p.area_id=a.id
  WHERE a.status='Active'
  ORDER BY a.area_name ASC, a.id DESC
  LIMIT 2000");
  if ($resA) {
    while ($a = $resA->fetch_assoc()) {
      $a['kind'] = 'service_area';
      $a['vehicle_type'] = 'Tricycle';
      $a['id'] = $a['service_area_id'];
      $a['route_db_id'] = null;
      $a['route_id'] = $a['area_code'];
      $a['route_code'] = $a['area_code'];
      $a['route_name'] = $a['area_name'];
      $a['origin'] = $a['points'];
      $a['destination'] = '';
      $fm = $a['fare_min'];
      $fx = $a['fare_max'];
      $a['fare'] = ($fm === null && $fx === null) ? null : (($fx === null || abs((float)$fm - (float)$fx) < 0.001) ? (string)($fm ?? $fx) : ((string)$fm . ' - ' . (string)$fx));
      $rows[] = $a;
    }
  }
}

echo json_encode(['ok' => true, 'data' => $rows]);
