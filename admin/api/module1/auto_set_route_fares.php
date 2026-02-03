<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';

header('Content-Type: application/json');
require_permission('module1.routes.write');

$disabled = true;
if ($disabled) {
  http_response_code(410);
  echo json_encode(['ok' => false, 'error' => 'disabled']);
  exit;
}

$db = db();

function round_to_quarter($amount) {
  $v = (float)$amount;
  return round($v * 4.0) / 4.0;
}

function tmm_num($value, $default) {
  if ($value === null) return $default;
  $s = trim((string)$value);
  if ($s === '') return $default;
  return (float)$s;
}

function compute_fare($vehicleType, $distanceKm, $rules) {
  $vt = strtoupper(trim((string)$vehicleType));
  $d = (float)$distanceKm;
  if ($d < 0) $d = 0;

  if ($vt === 'JEEPNEY') {
    $baseKm = (float)($rules['jeepney_base_km'] ?? 4.0);
    $base = (float)($rules['jeepney_base'] ?? 13.0);
    $perKm = (float)($rules['jeepney_per_km'] ?? 1.8);
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'UV' || $vt === 'UV EXPRESS' || $vt === 'MODERN JEEPNEY') {
    $baseKm = (float)($rules['uv_base_km'] ?? 4.0);
    $base = (float)($rules['uv_base'] ?? 15.0);
    $perKm = (float)($rules['uv_per_km'] ?? 2.2);
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'BUS') {
    $baseKm = (float)($rules['bus_base_km'] ?? 4.0);
    $base = (float)($rules['bus_base'] ?? 15.0);
    $perKm = (float)($rules['bus_per_km'] ?? 2.2);
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'TRICYCLE') {
    $baseKm = (float)($rules['tricycle_base_km'] ?? 1.0);
    $base = (float)($rules['tricycle_base'] ?? 20.0);
    $perKm = (float)($rules['tricycle_per_km'] ?? 5.0);
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  $baseKm = (float)($rules['jeepney_base_km'] ?? 4.0);
  $base = (float)($rules['jeepney_base'] ?? 13.0);
  $perKm = (float)($rules['jeepney_per_km'] ?? 1.8);
  $fare = $base + max(0.0, $d - $baseKm) * $perKm;
  return round_to_quarter($fare);
}

$overwrite = isset($_POST['overwrite']) && (string)$_POST['overwrite'] === '1';
$onlyMissing = !isset($_POST['only_missing']) || (string)$_POST['only_missing'] !== '0';

$rules = [
  'jeepney_base_km' => tmm_num($_POST['rate_jeepney_base_km'] ?? null, 4.0),
  'jeepney_base' => tmm_num($_POST['rate_jeepney_base'] ?? null, 13.0),
  'jeepney_per_km' => tmm_num($_POST['rate_jeepney_per_km'] ?? null, 1.8),
  'uv_base_km' => tmm_num($_POST['rate_uv_base_km'] ?? null, 4.0),
  'uv_base' => tmm_num($_POST['rate_uv_base'] ?? null, 15.0),
  'uv_per_km' => tmm_num($_POST['rate_uv_per_km'] ?? null, 2.2),
  'bus_base_km' => tmm_num($_POST['rate_bus_base_km'] ?? null, 4.0),
  'bus_base' => tmm_num($_POST['rate_bus_base'] ?? null, 15.0),
  'bus_per_km' => tmm_num($_POST['rate_bus_per_km'] ?? null, 2.2),
  'tricycle_base_km' => tmm_num($_POST['rate_tricycle_base_km'] ?? null, 1.0),
  'tricycle_base' => tmm_num($_POST['rate_tricycle_base'] ?? null, 20.0),
  'tricycle_per_km' => tmm_num($_POST['rate_tricycle_per_km'] ?? null, 5.0),
];
foreach ($rules as $k => $v) {
  if (!is_finite($v) || $v < 0) $rules[$k] = 0.0;
}

$res = $db->query("SELECT id, vehicle_type, distance_km, fare FROM routes LIMIT 5000");
if (!$res) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_query_failed']);
  exit;
}

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$db->begin_transaction();
try {
  $stmt = $db->prepare("UPDATE routes SET fare=? WHERE id=?");
  if (!$stmt) throw new Exception('db_prepare_failed');

  $updated = 0;
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    $curFare = $r['fare'];
    $curFareNum = ($curFare === null || $curFare === '') ? null : (float)$curFare;
    $isMissing = $curFareNum === null || $curFareNum <= 0;
    if ($onlyMissing && !$isMissing) continue;
    if (!$overwrite && !$isMissing) continue;

    $vt = (string)($r['vehicle_type'] ?? '');
    $dist = $r['distance_km'];
    $distNum = ($dist === null || $dist === '') ? 0.0 : (float)$dist;
    $newFare = compute_fare($vt, $distNum, $rules);
    $stmt->bind_param('di', $newFare, $id);
    if ($stmt->execute()) $updated++;
  }
  $stmt->close();
  $db->commit();
  tmm_audit_event($db, 'route.auto_set_fares', 'routes', '', ['updated' => $updated, 'overwrite' => $overwrite, 'only_missing' => $onlyMissing]);
  echo json_encode(['ok' => true, 'updated' => $updated]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}
