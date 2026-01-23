<?php
require_once __DIR__ . '/../admin/includes/db.php';

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
    return round_to_quarter($base + max(0.0, $d - $baseKm) * $perKm);
  }

  if ($vt === 'UV' || $vt === 'UV EXPRESS' || $vt === 'MODERN JEEPNEY') {
    $baseKm = 4.0;
    $base = 15.00;
    $perKm = 2.20;
    return round_to_quarter($base + max(0.0, $d - $baseKm) * $perKm);
  }

  if ($vt === 'BUS') {
    $baseKm = 4.0;
    $base = 15.00;
    $perKm = 2.20;
    return round_to_quarter($base + max(0.0, $d - $baseKm) * $perKm);
  }

  if ($vt === 'TRICYCLE') {
    $baseKm = 1.0;
    $base = 20.00;
    $perKm = 5.00;
    return round_to_quarter($base + max(0.0, $d - $baseKm) * $perKm);
  }

  $baseKm = 4.0;
  $base = 13.00;
  $perKm = 1.80;
  return round_to_quarter($base + max(0.0, $d - $baseKm) * $perKm);
}

$res = $db->query("SELECT id, vehicle_type, distance_km, fare FROM routes LIMIT 5000");
if (!$res) {
  fwrite(STDERR, "DB query failed\n");
  exit(1);
}

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$db->begin_transaction();
try {
  $stmt = $db->prepare("UPDATE routes SET fare=? WHERE id=? AND (fare IS NULL OR fare<=0)");
  if (!$stmt) throw new Exception('db_prepare_failed');
  $updated = 0;
  foreach ($rows as $r) {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) continue;
    $curFare = $r['fare'];
    $curFareNum = ($curFare === null || $curFare === '') ? null : (float)$curFare;
    if ($curFareNum !== null && $curFareNum > 0) continue;
    $vt = (string)($r['vehicle_type'] ?? '');
    $dist = $r['distance_km'];
    $distNum = ($dist === null || $dist === '') ? 0.0 : (float)$dist;
    $newFare = compute_fare($vt, $distNum);
    $stmt->bind_param('di', $newFare, $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) $updated++;
  }
  $stmt->close();
  $db->commit();
  echo "Updated fares: $updated\n";
} catch (Throwable $e) {
  $db->rollback();
  fwrite(STDERR, "DB error\n");
  exit(1);
}

