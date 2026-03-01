<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();
$vehId = 0;
if (php_sapi_name() === 'cli') {
  $vehId = isset($argv[1]) ? (int)$argv[1] : 0;
} else {
  header('Content-Type: application/json');
  $vehId = (int)($_GET['vehicle_id'] ?? 0);
}
if ($vehId <= 0) {
  echo json_encode(['ok' => false, 'error' => 'missing_vehicle_id']);
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
$sql = "SELECT id, plate_number, operator_id" .
       ($vehHasCur ? ", current_operator_id" : ", 0 AS current_operator_id") .
       ($vehHasFr ? ", franchise_id" : ", '' AS franchise_id") .
       " FROM vehicles WHERE id=? LIMIT 1";
$stmt = $db->prepare($sql);
if (!$stmt) { echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
$stmt->bind_param('i', $vehId);
$stmt->execute();
$veh = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$veh) { echo json_encode(['ok' => false, 'error' => 'vehicle_not_found']); exit; }
$opId = (int)($veh['current_operator_id'] ?? 0);
if ($opId <= 0) $opId = (int)($veh['operator_id'] ?? 0);
$op = null;
if ($opId > 0) {
  $stmtO = $db->prepare("SELECT id, name, full_name, status, workflow_status, verification_status FROM operators WHERE id=? LIMIT 1");
  if ($stmtO) {
    $stmtO->bind_param('i', $opId);
    $stmtO->execute();
    $op = $stmtO->get_result()->fetch_assoc();
    $stmtO->close();
  }
}
$apps = [];
if ($opId > 0) {
  $stmtA = $db->prepare("SELECT application_id, franchise_ref_number, status, submitted_channel, submitted_at FROM franchise_applications WHERE operator_id=? ORDER BY application_id DESC LIMIT 20");
  if ($stmtA) {
    $stmtA->bind_param('i', $opId);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($resA && ($r = $resA->fetch_assoc())) $apps[] = $r;
    $stmtA->close();
  }
}
$ref = trim((string)($veh['franchise_id'] ?? ''));
$appsByRef = [];
if ($ref !== '' && $hasCol('franchise_applications', 'franchise_ref_number')) {
  $stmtR = $db->prepare("SELECT application_id, operator_id, franchise_ref_number, status, submitted_channel, submitted_at FROM franchise_applications WHERE franchise_ref_number=? ORDER BY application_id DESC LIMIT 20");
  if ($stmtR) {
    $stmtR->bind_param('s', $ref);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    while ($resR && ($r = $resR->fetch_assoc())) $appsByRef[] = $r;
    $stmtR->close();
  }
}
$out = [
  'ok' => true,
  'vehicle' => $veh,
  'operator_id_used' => $opId,
  'operator' => $op,
  'apps_by_operator' => $apps,
  'apps_by_franchise_ref' => $appsByRef,
];
echo json_encode($out, JSON_PRETTY_PRINT);
