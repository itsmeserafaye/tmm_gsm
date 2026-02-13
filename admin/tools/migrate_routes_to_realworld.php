<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
  require_login();
  require_permission('module1.routes.write');
  header('Content-Type: text/plain');
}

$db = db();

$hasTable = function (string $table) use ($db): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return (bool)($r && $r->fetch_row());
};

if (!$hasTable('route_vehicle_types') || !$hasTable('route_legacy_map')) {
  echo "missing_required_tables\n";
  exit(1);
}

$stripPrefix = function (string $code): string {
  $c = strtoupper(trim($code));
  $c = preg_replace('/^(BUS|UV|JEEP|JEEPNEY|TRI|TRICYCLE)\-+/', '', $c);
  $c = trim((string)$c);
  return $c !== '' ? $c : strtoupper(trim($code));
};

$makeName = function (array $row): string {
  $o = trim((string)($row['origin'] ?? ''));
  $d = trim((string)($row['destination'] ?? ''));
  if ($o !== '' && $d !== '') return $o . ' - ' . $d;
  $n = trim((string)($row['route_name'] ?? ''));
  $c = trim((string)($row['route_code'] ?? $row['route_id'] ?? ''));
  return $n !== '' ? $n : $c;
};

$cols = [];
$colRes = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='routes'");
if ($colRes) while ($c = $colRes->fetch_assoc()) $cols[(string)($c['COLUMN_NAME'] ?? '')] = true;
$hasFareMin = isset($cols['fare_min']);
$hasFareMax = isset($cols['fare_max']);

$legacyRows = [];
$sql = "SELECT r.*
        FROM routes r
        LEFT JOIN route_legacy_map m ON m.legacy_route_pk=r.id
        WHERE COALESCE(r.vehicle_type,'')<>'' AND m.legacy_route_pk IS NULL
        ORDER BY r.id ASC";
$res = $db->query($sql);
if ($res) while ($r = $res->fetch_assoc()) $legacyRows[] = $r;

$createdCorridors = 0;
$createdAlloc = 0;
$mapped = 0;

foreach ($legacyRows as $lr) {
  $legacyId = (int)($lr['id'] ?? 0);
  if ($legacyId <= 0) continue;
  $vt = trim((string)($lr['vehicle_type'] ?? ''));
  if ($vt === '') continue;

  $legacyCode = trim((string)($lr['route_code'] ?? $lr['route_id'] ?? ''));
  $corrCode = $stripPrefix($legacyCode);
  $corrName = $makeName($lr);
  $origin = (string)($lr['origin'] ?? '');
  $destination = (string)($lr['destination'] ?? '');
  $via = (string)($lr['via'] ?? '');
  $structure = (string)($lr['structure'] ?? '');
  $distanceKm = isset($lr['distance_km']) ? (float)$lr['distance_km'] : null;
  $status = (string)($lr['status'] ?? 'Active');

  $fareMin = null;
  $fareMax = null;
  if ($hasFareMin) $fareMin = $lr['fare_min'] !== null ? (float)$lr['fare_min'] : null;
  if ($hasFareMax) $fareMax = $lr['fare_max'] !== null ? (float)$lr['fare_max'] : null;
  if ($fareMin === null && isset($lr['fare']) && $lr['fare'] !== null) $fareMin = (float)$lr['fare'];
  if ($fareMax === null && isset($lr['fare']) && $lr['fare'] !== null) $fareMax = (float)$lr['fare'];
  if ($fareMin !== null && $fareMax === null) $fareMax = $fareMin;
  if ($fareMax !== null && $fareMin === null) $fareMin = $fareMax;

  $authorizedUnits = isset($lr['authorized_units']) && $lr['authorized_units'] !== null ? (int)$lr['authorized_units'] : null;
  if ($authorizedUnits !== null && $authorizedUnits <= 0) $authorizedUnits = null;

  $corrId = 0;
  $stmtFind = $db->prepare("SELECT id FROM routes WHERE (route_id=? OR route_code=?) AND (vehicle_type IS NULL OR vehicle_type='') LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('ss', $corrCode, $corrCode);
    $stmtFind->execute();
    $row = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    $corrId = (int)($row['id'] ?? 0);
  }
  if ($corrId <= 0) {
    $maxLimit = 50;
    $stmtIns = $db->prepare("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, fare, authorized_units, max_vehicle_limit, status)
                             VALUES(?, ?, ?, NULL, ?, ?, ?, ?, ?, NULL, NULL, ?, ?)");
    if (!$stmtIns) {
      echo "failed_insert_corridor:$legacyId\n";
      continue;
    }
    $viaBind = trim($via) !== '' ? $via : null;
    $structBind = trim($structure) !== '' ? $structure : null;
    $distBind = $distanceKm;
    $stmtIns->bind_param('sssssssdis', $corrCode, $corrCode, $corrName, $origin, $destination, $viaBind, $structBind, $distBind, $maxLimit, $status);
    if (!$stmtIns->execute()) {
      $stmtIns->close();
      echo "failed_insert_corridor:$legacyId\n";
      continue;
    }
    $corrId = (int)$db->insert_id;
    $stmtIns->close();
    $createdCorridors++;
  }

  $stmtAlloc = $db->prepare("INSERT INTO route_vehicle_types(route_id, vehicle_type, authorized_units, fare_min, fare_max, status)
                             VALUES(?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE authorized_units=VALUES(authorized_units), fare_min=VALUES(fare_min), fare_max=VALUES(fare_max), status=VALUES(status)");
  if ($stmtAlloc) {
    $au = $authorizedUnits;
    $fmin = $fareMin;
    $fmax = $fareMax;
    $st = in_array($status, ['Active','Inactive'], true) ? $status : 'Active';
    $stmtAlloc->bind_param('isidds', $corrId, $vt, $au, $fmin, $fmax, $st);
    if ($stmtAlloc->execute()) $createdAlloc++;
    $stmtAlloc->close();
  }

  $stmtMap = $db->prepare("INSERT INTO route_legacy_map(legacy_route_pk, route_id, vehicle_type) VALUES (?, ?, ?)
                           ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), vehicle_type=VALUES(vehicle_type)");
  if ($stmtMap) {
    $stmtMap->bind_param('iis', $legacyId, $corrId, $vt);
    if ($stmtMap->execute()) $mapped++;
    $stmtMap->close();
  }
}

$db->query("UPDATE routes r JOIN route_legacy_map m ON m.legacy_route_pk=r.id SET r.status='Inactive' WHERE COALESCE(r.status,'')<>'Inactive'");

$db->query("UPDATE franchise_applications fa
            JOIN route_legacy_map m ON m.legacy_route_pk=fa.route_id
            SET fa.route_id=m.route_id, fa.vehicle_type=m.vehicle_type
            WHERE fa.route_id IS NOT NULL AND COALESCE(fa.vehicle_type,'')=''");

$db->query("UPDATE franchise_vehicles fv
            JOIN route_legacy_map m ON m.legacy_route_pk=fv.route_id
            SET fv.route_id=m.route_id, fv.vehicle_type=m.vehicle_type
            WHERE fv.route_id IS NOT NULL AND COALESCE(fv.vehicle_type,'')=''");

$db->query("UPDATE terminal_routes tr
            JOIN routes lr ON lr.route_id=tr.route_id AND COALESCE(lr.vehicle_type,'')<>''
            JOIN route_legacy_map m ON m.legacy_route_pk=lr.id
            JOIN routes r2 ON r2.id=m.route_id
            SET tr.route_id=r2.route_id");

echo "created_corridors=$createdCorridors\n";
echo "upserted_allocations=$createdAlloc\n";
echo "mapped_legacy_routes=$mapped\n";
?>

