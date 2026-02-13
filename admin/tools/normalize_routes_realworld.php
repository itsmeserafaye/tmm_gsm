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

$isPrefixed = function (string $code): bool {
  $c = strtoupper(trim($code));
  return (bool)preg_match('/^(BUS|UV|JEEP|JEEPNEY)\-+/', $c);
};

$legacy = [];
$res = $db->query("SELECT id, route_id, route_code, route_name, origin, destination, via, structure, distance_km,
                          vehicle_type, authorized_units, fare_min, fare_max, fare, status
                   FROM routes
                   WHERE COALESCE(vehicle_type,'') IN ('Jeepney','UV','Bus')
                   ORDER BY id ASC");
if ($res) while ($r = $res->fetch_assoc()) $legacy[] = $r;

$groups = [];
foreach ($legacy as $r) {
  $code = trim((string)($r['route_code'] ?? ''));
  if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
  $base = $stripPrefix($code);
  $key = $base !== '' ? $base : $code;
  if ($key === '') continue;
  if (!isset($groups[$key])) $groups[$key] = [];
  $groups[$key][] = $r;
}

$migratedGroups = 0;
$migratedLegacyRows = 0;
$createdAllocs = 0;
$deactivatedLegacyRows = 0;
$updatedCorridors = 0;
$deactivatedEmptyCorridors = 0;
$deactivatedFarelessAllocs = 0;

$db->begin_transaction();
try {
  foreach ($groups as $key => $rows) {
    if (!$rows) continue;

    $master = null;
    foreach ($rows as $r) {
      $code = trim((string)($r['route_code'] ?? ''));
      if ($code === '') $code = trim((string)($r['route_id'] ?? ''));
      if (!$isPrefixed($code)) { $master = $r; break; }
    }
    if ($master === null) $master = $rows[0];
    $masterId = (int)($master['id'] ?? 0);
    if ($masterId <= 0) continue;

    $stmtC = $db->prepare("UPDATE routes SET vehicle_type=NULL WHERE id=?");
    if ($stmtC) {
      $stmtC->bind_param('i', $masterId);
      $stmtC->execute();
      $stmtC->close();
      $updatedCorridors++;
    }

    $seenVt = [];
    foreach ($rows as $r) {
      $legacyId = (int)($r['id'] ?? 0);
      $vt = trim((string)($r['vehicle_type'] ?? ''));
      if ($legacyId <= 0 || $vt === '') continue;
      if (isset($seenVt[$vt])) {
        $stmtIn = $db->prepare("UPDATE routes SET status='Inactive' WHERE id=?");
        if ($stmtIn) { $stmtIn->bind_param('i', $legacyId); $stmtIn->execute(); $stmtIn->close(); $deactivatedLegacyRows++; }
        continue;
      }
      $seenVt[$vt] = true;

      $au = $r['authorized_units'] !== null ? (int)$r['authorized_units'] : null;
      $fareMin = $r['fare_min'] !== null ? (float)$r['fare_min'] : null;
      $fareMax = $r['fare_max'] !== null ? (float)$r['fare_max'] : null;
      $fare = $r['fare'] !== null ? (float)$r['fare'] : null;
      if ($fareMin === null && $fare !== null) $fareMin = $fare;
      if ($fareMax === null && $fare !== null) $fareMax = $fare;
      if ($fareMax === null && $fareMin !== null) $fareMax = $fareMin;
      $allocStatus = ((string)($r['status'] ?? 'Active')) === 'Active' ? 'Active' : 'Inactive';

      $stmtA = $db->prepare("INSERT INTO route_vehicle_types(route_id, vehicle_type, authorized_units, fare_min, fare_max, status)
                             VALUES (?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE authorized_units=VALUES(authorized_units), fare_min=VALUES(fare_min), fare_max=VALUES(fare_max), status=VALUES(status)");
      if ($stmtA) {
        $stmtA->bind_param('isidds', $masterId, $vt, $au, $fareMin, $fareMax, $allocStatus);
        if ($stmtA->execute()) $createdAllocs++;
        $stmtA->close();
      }

      $stmtMap = $db->prepare("INSERT INTO route_legacy_map(legacy_route_pk, route_id, vehicle_type)
                               VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), vehicle_type=VALUES(vehicle_type)");
      if ($stmtMap) {
        $stmtMap->bind_param('iis', $legacyId, $masterId, $vt);
        if ($stmtMap->execute()) $migratedLegacyRows++;
        $stmtMap->close();
      }

      if ($legacyId !== $masterId) {
        $stmtIn = $db->prepare("UPDATE routes SET status='Inactive' WHERE id=?");
        if ($stmtIn) { $stmtIn->bind_param('i', $legacyId); $stmtIn->execute(); $stmtIn->close(); $deactivatedLegacyRows++; }
      }
    }
    $migratedGroups++;
  }

  $db->query("UPDATE route_vehicle_types
              SET status='Inactive'
              WHERE status='Active' AND fare_min IS NULL AND fare_max IS NULL");
  $deactivatedFarelessAllocs = (int)($db->affected_rows ?? 0);

  $db->query("UPDATE routes r
              LEFT JOIN (
                SELECT route_id, SUM(status='Active') AS active_allocs
                FROM route_vehicle_types
                WHERE vehicle_type<>'Tricycle'
                GROUP BY route_id
              ) a ON a.route_id=r.id
              SET r.status='Inactive'
              WHERE COALESCE(r.status,'')='Active'
                AND COALESCE(r.vehicle_type,'')<>'Tricycle'
                AND COALESCE(a.active_allocs,0)=0");
  $deactivatedEmptyCorridors = (int)($db->affected_rows ?? 0);

  $db->commit();
} catch (Throwable $e) {
  $db->rollback();
  echo "failed:" . ($e->getMessage() ?: 'error') . "\n";
  exit(1);
}

echo "migrated_groups=$migratedGroups\n";
echo "mapped_legacy_routes=$migratedLegacyRows\n";
echo "upserted_allocations=$createdAllocs\n";
echo "deactivated_legacy_routes=$deactivatedLegacyRows\n";
echo "corridors_set_vehicle_type_null=$updatedCorridors\n";
echo "allocations_deactivated_missing_fare=$deactivatedFarelessAllocs\n";
echo "corridors_deactivated_missing_active_allocations=$deactivatedEmptyCorridors\n";
?>

