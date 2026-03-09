<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_any_permission(['module2.read','module2.endorse','module2.approve','module2.history']);

$routeId = (int)($_GET['route_id'] ?? 0);
if ($routeId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_route_id']);
  exit;
}

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};
$hasTable = function (string $table) use ($db): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return $r && (bool)$r->fetch_row();
};

$faHasApprovedRoutes = $hasCol('franchise_applications', 'approved_route_ids');
$faHasRouteIds = $hasCol('franchise_applications', 'route_ids');
$faHasAppId = $hasCol('franchise_applications', 'application_id');
$faHasId = $hasCol('franchise_applications', 'id');
$faHasRefNo = $hasCol('franchise_applications', 'franchise_ref_number');
$opHasWorkflow = $hasCol('operators', 'workflow_status');
$opHasVerify = $hasCol('operators', 'verification_status');
$opHasRegName = $hasCol('operators', 'registered_name');
$opHasName = $hasCol('operators', 'name');
$opHasFullName = $hasCol('operators', 'full_name');
$opHasType = $hasCol('operators', 'operator_type');
$opHasStatus = $hasCol('operators', 'status');
$hasVehicleRegs = $hasTable('vehicle_registrations') && $hasCol('vehicle_registrations', 'registration_status') && $hasCol('vehicles', 'id');
$vehHasCurOp = $hasCol('vehicles', 'current_operator_id');
$vehHasOpName = $hasCol('vehicles', 'operator_name');
$vehHasCoopName = $hasCol('vehicles', 'coop_name');
$vehHasRecordStatus = $hasCol('vehicles', 'record_status');
$vehHasVehicleType = $hasCol('vehicles', 'vehicle_type');
$vehHasStatus = $hasCol('vehicles', 'status');

$routeMatchParts = ["fa1.route_id=?"];
if ($faHasApprovedRoutes) $routeMatchParts[] = "FIND_IN_SET(?, REPLACE(COALESCE(NULLIF(fa1.approved_route_ids,''), ''), 'ROUTE:', ''))";
if ($faHasRouteIds) $routeMatchParts[] = "FIND_IN_SET(?, REPLACE(COALESCE(NULLIF(fa1.route_ids,''), ''), 'ROUTE:', ''))";
$routeMatchSql = '(' . implode(' OR ', $routeMatchParts) . ')';

$opFilterParts = [];
if ($opHasWorkflow) $opFilterParts[] = "COALESCE(NULLIF(o.workflow_status,''),'')='Active'";
if ($opHasVerify) $opFilterParts[] = "COALESCE(NULLIF(o.verification_status,''),'')='Verified'";
if (!$opFilterParts && $opHasStatus) $opFilterParts[] = "COALESCE(NULLIF(o.status,''),'')='Approved'";
$opFilterSql = $opFilterParts ? ('(' . implode(' AND ', $opFilterParts) . ')') : '1=1';

$regFilterSql =
  ($hasVehicleRegs && $vehHasStatus)
    ? "(COALESCE(NULLIF(v.status,''),'') IN ('Registered','Active') OR COALESCE(NULLIF(vr.registration_status,''),'') IN ('Registered','Recorded'))"
    : ($hasVehicleRegs
        ? "(COALESCE(NULLIF(vr.registration_status,''),'') IN ('Registered','Recorded'))"
        : ($vehHasStatus
            ? "(COALESCE(NULLIF(v.status,''),'') IN ('Registered','Active'))"
            : "1=1"));

$opNameParts = [];
if ($opHasName) $opNameParts[] = "NULLIF(o.name,'')";
if ($opHasRegName) $opNameParts[] = "NULLIF(o.registered_name,'')";
if ($opHasFullName) $opNameParts[] = "NULLIF(o.full_name,'')";
if ($vehHasOpName) $opNameParts[] = "NULLIF(v.operator_name,'')";
$opNameExpr = "COALESCE(" . ($opNameParts ? implode(", ", $opNameParts) : "'-'") . ", '-')";

$opTypeExpr = $opHasType
  ? "COALESCE(NULLIF(o.operator_type,''), 'Individual')"
  : (($vehHasCoopName)
      ? "CASE WHEN COALESCE(NULLIF(v.coop_name,''), '') <> '' THEN 'Cooperative' ELSE 'Individual' END"
      : "'Individual'");

$vehJoinKey = $vehHasCurOp
  ? "COALESCE(NULLIF(v.current_operator_id,0), NULLIF(v.operator_id,0), 0)"
  : "COALESCE(NULLIF(v.operator_id,0), 0)";

$faPk = $faHasAppId ? 'application_id' : ($faHasId ? 'id' : 'application_id');
$faRefExpr = $faHasRefNo ? 'fa1.franchise_ref_number' : "''";
$vehTypeExpr = $vehHasVehicleType ? 'v.vehicle_type' : "''";
$vehStatusExpr = $vehHasStatus ? 'v.status' : "''";
$vehRecordStatusFilter = $vehHasRecordStatus ? "COALESCE(v.record_status,'') <> 'Archived'" : "1=1";

$sql = "SELECT
  v.id AS vehicle_id,
  v.plate_number,
  {$vehTypeExpr} AS vehicle_type,
  {$vehStatusExpr} AS vehicle_status,
  {$opNameExpr} AS operator_name,
  {$opTypeExpr} AS operator_type,
  fa.franchise_ref_number,
  fa.status AS franchise_status
FROM (
  SELECT fa1.operator_id, {$faRefExpr} AS franchise_ref_number, fa1.status
  FROM franchise_applications fa1
  JOIN (
    SELECT operator_id, MAX($faPk) AS max_id
    FROM franchise_applications fa1
    WHERE {$routeMatchSql}
      AND status IN ('Active','LGU-Endorsed','Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued')
    GROUP BY operator_id
  ) x ON x.max_id=fa1.$faPk
) fa
JOIN vehicles v ON {$vehJoinKey}=fa.operator_id
LEFT JOIN operators o ON o.id=fa.operator_id " . ($hasVehicleRegs ? "LEFT JOIN vehicle_registrations vr ON vr.vehicle_id=v.id " : "") . "
WHERE {$vehRecordStatusFilter}
  AND 1=1
  AND {$regFilterSql}
ORDER BY operator_name ASC, v.plate_number ASC";

$stmt = $db->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_prepare_failed', 'db_error' => (string)$db->error]);
  exit;
}

$rid2 = $routeId;
$rid3 = $routeId;
if ($faHasApprovedRoutes && $faHasRouteIds) {
  $stmt->bind_param('iii', $routeId, $rid2, $rid3);
} elseif ($faHasApprovedRoutes || $faHasRouteIds) {
  $stmt->bind_param('ii', $routeId, $rid2);
} else {
  $stmt->bind_param('i', $routeId);
}
$ok = $stmt->execute();
if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_execute_failed', 'db_error' => (string)$stmt->error]);
  exit;
}
$res = $stmt->get_result();
$rows = [];
while ($res && ($r = $res->fetch_assoc())) $rows[] = $r;
$stmt->close();

echo json_encode(['ok' => true, 'data' => $rows]);
