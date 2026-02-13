<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 25);
if ($limit <= 0) $limit = 25;
if ($limit > 50) $limit = 50;

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$vehHasEngine = $hasCol('vehicles', 'engine_no');
$vehHasChassis = $hasCol('vehicles', 'chassis_no');
$vehHasOperatorName = $hasCol('vehicles', 'operator_name');
$vehHasCurrentOp = $hasCol('vehicles', 'current_operator_id');
$vehHasRecordStatus = $hasCol('vehicles', 'record_status');

$hasSchedulesTable = false;
$tSch = $db->query("SHOW TABLES LIKE 'inspection_schedules'");
if ($tSch && $tSch->num_rows > 0) $hasSchedulesTable = true;
$schHasVehicleId = $hasSchedulesTable ? $hasCol('inspection_schedules', 'vehicle_id') : false;
$schHasPlate = $hasSchedulesTable ? $hasCol('inspection_schedules', 'plate_number') : false;
$schHasStatus = $hasSchedulesTable ? $hasCol('inspection_schedules', 'status') : false;

$opIdExpr = $vehHasCurrentOp ? "COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0))" : "NULLIF(v.operator_id,0)";
$opNameExpr = "COALESCE(NULLIF(o.name,''), NULLIF(o.full_name,'')," . ($vehHasOperatorName ? " NULLIF(v.operator_name,'')," : "") . " '-')";

$sql = "SELECT v.id, v.plate_number" .
       ($vehHasEngine ? ", v.engine_no" : ", '' AS engine_no") .
       ($vehHasChassis ? ", v.chassis_no" : ", '' AS chassis_no") .
       ",
       {$opNameExpr} AS operator_name
        FROM vehicles v
        LEFT JOIN operators o ON o.id={$opIdExpr}
        WHERE " . ($vehHasRecordStatus ? "COALESCE(v.record_status,'') <> 'Archived' AND " : "") . "
          COALESCE({$opIdExpr}, 0) > 0";

if ($hasSchedulesTable && $schHasStatus && ($schHasVehicleId || $schHasPlate)) {
  $match = $schHasVehicleId ? "s.vehicle_id=v.id" : "s.plate_number=v.plate_number";
  $sql .= " AND EXISTS (SELECT 1 FROM inspection_schedules s WHERE $match AND s.status='Completed')";
}

$params = [];
$types = '';
if ($q !== '') {
  $qq = '%' . $q . '%';
  $conds = ["v.plate_number LIKE ?"];
  $params = [$qq];
  $types = 's';
  if ($vehHasEngine) { $conds[] = "v.engine_no LIKE ?"; $params[] = $qq; $types .= 's'; }
  if ($vehHasChassis) { $conds[] = "v.chassis_no LIKE ?"; $params[] = $qq; $types .= 's'; }
  $conds[] = "o.name LIKE ?"; $params[] = $qq; $types .= 's';
  $conds[] = "o.full_name LIKE ?"; $params[] = $qq; $types .= 's';
  if ($vehHasOperatorName) { $conds[] = "v.operator_name LIKE ?"; $params[] = $qq; $types .= 's'; }
  $sql .= " AND (" . implode(" OR ", $conds) . ")";
}
$sql .= " ORDER BY v.plate_number ASC LIMIT " . (int)$limit;

if ($types !== '') {
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
  $stmt->close();
  echo json_encode(['ok' => true, 'data' => $rows]);
  exit;
}

$res = $db->query($sql);
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
echo json_encode(['ok' => true, 'data' => $rows]);
