<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_permission('module1.routes.write');

$db = db();

function round_to_quarter($amount) {
  $v = (float)$amount;
  return round($v * 4.0) / 4.0;
}

function compute_fare($vehicleType, $distanceKm) {
  $vt = strtoupper(trim((string)$vehicleType));
  $d = (float)$distanceKm;
  if ($d < 0) $d = 0;

  if ($vt === 'JEEPNEY') {
    $baseKm = 4.0;
    $base = 13.00;
    $perKm = 1.80;
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'UV' || $vt === 'UV EXPRESS' || $vt === 'MODERN JEEPNEY') {
    $baseKm = 4.0;
    $base = 15.00;
    $perKm = 2.20;
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'BUS') {
    $baseKm = 4.0;
    $base = 15.00;
    $perKm = 2.20;
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  if ($vt === 'TRICYCLE') {
    $baseKm = 1.0;
    $base = 20.00;
    $perKm = 5.00;
    $fare = $base + max(0.0, $d - $baseKm) * $perKm;
    return round_to_quarter($fare);
  }

  $baseKm = 4.0;
  $base = 13.00;
  $perKm = 1.80;
  $fare = $base + max(0.0, $d - $baseKm) * $perKm;
  return round_to_quarter($fare);
}

$overwrite = isset($_POST['overwrite']) && (string)$_POST['overwrite'] === '1';
$onlyMissing = !isset($_POST['only_missing']) || (string)$_POST['only_missing'] !== '0';

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
    $newFare = compute_fare($vt, $distNum);
    $stmt->bind_param('di', $newFare, $id);
    if ($stmt->execute()) $updated++;
  }
  $stmt->close();
  $db->commit();
  echo json_encode(['ok' => true, 'updated' => $updated]);
} catch (Throwable $e) {
  $db->rollback();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_error']);
}

