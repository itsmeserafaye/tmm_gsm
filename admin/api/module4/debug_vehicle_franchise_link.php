<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
$plateRaw = (string)($_GET['plate'] ?? '');
$plate = strtoupper(trim($plateRaw));
$plate = preg_replace('/[^A-Z0-9\-]/', '', $plate);
if ($vehicleId <= 0 && $plate !== '') {
  $stmtP = $db->prepare("SELECT id FROM vehicles WHERE plate_number=? LIMIT 1");
  if ($stmtP) {
    $stmtP->bind_param('s', $plate);
    $stmtP->execute();
    $r = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();
    $vehicleId = (int)($r['id'] ?? 0);
  }
}
if ($vehicleId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_vehicle_id', 'hint' => 'Provide ?vehicle_id= or ?plate=', 'plate_in' => $plateRaw, 'plate_clean' => $plate]);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$vehHasCur = $hasCol('vehicles', 'current_operator_id');
$vehHasFr = $hasCol('vehicles', 'franchise_id');

$stmtV = $db->prepare("SELECT id, plate_number, operator_id" .
  ($vehHasCur ? ", current_operator_id" : ", 0 AS current_operator_id") .
  ($vehHasFr ? ", franchise_id" : ", '' AS franchise_id") .
  " FROM vehicles WHERE id=? LIMIT 1");
if (!$stmtV) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']);
  exit;
}
$stmtV->bind_param('i', $vehicleId);
$stmtV->execute();
$veh = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
if (!$veh) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']);
  exit;
}

$operatorIdUsed = $vehHasCur ? (int)($veh['current_operator_id'] ?? 0) : 0;
if ($operatorIdUsed <= 0) $operatorIdUsed = (int)($veh['operator_id'] ?? 0);

$op = null;
if ($operatorIdUsed > 0) {
  $stmtO = $db->prepare("SELECT id, name, full_name, status, workflow_status, verification_status FROM operators WHERE id=? LIMIT 1");
  if ($stmtO) {
    $stmtO->bind_param('i', $operatorIdUsed);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
  }
}

$appsByOperator = [];
if ($operatorIdUsed > 0) {
  $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, status, submitted_channel, submitted_at
                         FROM franchise_applications
                         WHERE operator_id=?
                         ORDER BY application_id DESC
                         LIMIT 50");
  if ($stmtA) {
    $stmtA->bind_param('i', $operatorIdUsed);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($resA && ($r = $resA->fetch_assoc())) $appsByOperator[] = $r;
    $stmtA->close();
  }
}

$appsByRef = [];
$frRef = trim((string)($veh['franchise_id'] ?? ''));
if ($frRef !== '') {
  $stmtR = $db->prepare("SELECT application_id, operator_id, franchise_ref_number, status, submitted_channel, submitted_at
                         FROM franchise_applications
                         WHERE franchise_ref_number=?
                         ORDER BY application_id DESC
                         LIMIT 50");
  if ($stmtR) {
    $stmtR->bind_param('s', $frRef);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    while ($resR && ($r = $resR->fetch_assoc())) $appsByRef[] = $r;
    $stmtR->close();
  }
}

echo json_encode([
  'ok' => true,
  'vehicle' => $veh,
  'operator_id_used' => $operatorIdUsed,
  'operator' => $op,
  'apps_by_operator' => $appsByOperator,
  'apps_by_franchise_ref' => $appsByRef,
]);
