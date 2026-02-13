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

if (!$hasTable('tricycle_service_areas') || !$hasTable('tricycle_service_area_points') || !$hasTable('tricycle_legacy_map')) {
  echo "missing_required_tables\n";
  exit(1);
}

$slug = function (string $s): string {
  $s = strtoupper(trim($s));
  $s = preg_replace('/[^A-Z0-9]+/', '_', $s);
  $s = trim($s, '_');
  return $s !== '' ? $s : 'AREA';
};

$triRows = [];
$res = $db->query("SELECT r.*
                   FROM routes r
                   LEFT JOIN tricycle_legacy_map m ON m.legacy_route_pk=r.id
                   WHERE COALESCE(r.vehicle_type,'')='Tricycle' AND m.legacy_route_pk IS NULL
                   ORDER BY r.id ASC");
if ($res) while ($r = $res->fetch_assoc()) $triRows[] = $r;

$byOrigin = [];
foreach ($triRows as $r) {
  $origin = trim((string)($r['origin'] ?? ''));
  if ($origin === '') $origin = 'Unknown';
  $byOrigin[$origin][] = $r;
}

$createdAreas = 0;
$mapped = 0;
$createdPoints = 0;

foreach ($byOrigin as $origin => $rows) {
  $code = 'TODA-' . $slug($origin);
  $name = trim($origin) . ' TODA Zone';
  $fareMin = null;
  $fareMax = null;
  $auth = null;
  $points = [];

  foreach ($rows as $r) {
    $au = isset($r['authorized_units']) && $r['authorized_units'] !== null ? (int)$r['authorized_units'] : null;
    if ($au !== null && $au > 0) $auth = $auth === null ? $au : max($auth, $au);

    $fm = $r['fare_min'] !== null ? (float)$r['fare_min'] : ($r['fare'] !== null ? (float)$r['fare'] : null);
    $fx = $r['fare_max'] !== null ? (float)$r['fare_max'] : ($r['fare'] !== null ? (float)$r['fare'] : null);
    if ($fm !== null) $fareMin = $fareMin === null ? $fm : min($fareMin, $fm);
    if ($fx !== null) $fareMax = $fareMax === null ? $fx : max($fareMax, $fx);

    $o = trim((string)($r['origin'] ?? ''));
    $d = trim((string)($r['destination'] ?? ''));
    if ($o !== '') $points[] = $o;
    if ($d !== '') $points[] = $d;
  }
  $points = array_values(array_unique(array_filter(array_map('trim', $points))));
  if ($fareMin !== null && $fareMax === null) $fareMax = $fareMin;
  if ($fareMax !== null && $fareMin === null) $fareMin = $fareMax;

  $areaId = 0;
  $stmtFind = $db->prepare("SELECT id FROM tricycle_service_areas WHERE area_code=? LIMIT 1");
  if ($stmtFind) {
    $stmtFind->bind_param('s', $code);
    $stmtFind->execute();
    $row = $stmtFind->get_result()->fetch_assoc();
    $stmtFind->close();
    $areaId = (int)($row['id'] ?? 0);
  }
  if ($areaId <= 0) {
    $stmtIns = $db->prepare("INSERT INTO tricycle_service_areas(area_code, area_name, authorized_units, fare_min, fare_max, coverage_notes, status)
                             VALUES(?, ?, ?, ?, ?, ?, 'Active')");
    if (!$stmtIns) continue;
    $notes = "Auto-migrated from legacy tricycle routes. Edit coverage points to match TODA boundaries.";
    $stmtIns->bind_param('ssidds', $code, $name, $auth, $fareMin, $fareMax, $notes);
    if ($stmtIns->execute()) {
      $areaId = (int)$db->insert_id;
      $createdAreas++;
    }
    $stmtIns->close();
  }
  if ($areaId <= 0) continue;

  $db->query("DELETE FROM tricycle_service_area_points WHERE area_id=" . (int)$areaId);
  if ($points) {
    $stmtP = $db->prepare("INSERT INTO tricycle_service_area_points(area_id, point_name, point_type, sort_order) VALUES (?, ?, 'Landmark', ?)");
    if ($stmtP) {
      $i = 0;
      foreach ($points as $p) {
        $i++;
        $stmtP->bind_param('isi', $areaId, $p, $i);
        if ($stmtP->execute()) $createdPoints++;
      }
      $stmtP->close();
    }
  }

  foreach ($rows as $r) {
    $legacyId = (int)($r['id'] ?? 0);
    if ($legacyId <= 0) continue;
    $stmtMap = $db->prepare("INSERT INTO tricycle_legacy_map(legacy_route_pk, service_area_id) VALUES (?, ?)
                             ON DUPLICATE KEY UPDATE service_area_id=VALUES(service_area_id)");
    if ($stmtMap) {
      $stmtMap->bind_param('ii', $legacyId, $areaId);
      if ($stmtMap->execute()) $mapped++;
      $stmtMap->close();
    }
  }
}

$db->query("UPDATE routes r JOIN tricycle_legacy_map m ON m.legacy_route_pk=r.id SET r.status='Inactive' WHERE COALESCE(r.status,'')<>'Inactive'");

$db->query("UPDATE franchise_applications fa
            JOIN tricycle_legacy_map m ON m.legacy_route_pk=fa.route_id
            SET fa.service_area_id=m.service_area_id, fa.vehicle_type='Tricycle', fa.route_id=NULL
            WHERE fa.route_id IS NOT NULL AND (fa.vehicle_type='Tricycle' OR COALESCE(fa.vehicle_type,'')='')");

$db->query("UPDATE franchise_vehicles fv
            JOIN tricycle_legacy_map m ON m.legacy_route_pk=fv.route_id
            SET fv.service_area_id=m.service_area_id, fv.vehicle_type='Tricycle', fv.route_id=NULL
            WHERE fv.route_id IS NOT NULL AND (fv.vehicle_type='Tricycle' OR COALESCE(fv.vehicle_type,'')='')");

echo "created_service_areas=$createdAreas\n";
echo "created_points=$createdPoints\n";
echo "mapped_legacy_tricycle_routes=$mapped\n";
?>

