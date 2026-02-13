<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
header('Content-Type: application/json');
require_permission('module4.schedule');

$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 50);
if ($limit <= 0) $limit = 50;
if ($limit > 200) $limit = 200;

$hasCol = function (string $table, string $col) use ($db): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
};

$vehHasRecordStatus = $hasCol('vehicles', 'record_status');
$vehHasInspectionStatus = $hasCol('vehicles', 'inspection_status');
$vehHasOperatorId = $hasCol('vehicles', 'operator_id');

$sql = "SELECT v.id, v.plate_number, v.vehicle_type
        FROM vehicles v
        WHERE 1=1";
if ($vehHasRecordStatus) $sql .= " AND COALESCE(v.record_status,'') <> 'Archived'";
if ($vehHasRecordStatus) $sql .= " AND COALESCE(v.record_status,'')='Linked'";
if ($vehHasOperatorId) $sql .= " AND COALESCE(v.operator_id,0) > 0";
if ($vehHasInspectionStatus) {
  $sql .= " AND COALESCE(v.inspection_status,'') <> 'Passed'";
} else {
  $sql .= " AND NOT EXISTS (
    SELECT 1 FROM inspection_schedules s
    WHERE (s.vehicle_id=v.id OR ((s.vehicle_id IS NULL OR s.vehicle_id=0) AND s.plate_number=v.plate_number))
      AND s.status='Completed'
  )";
}

$sql .= " AND NOT EXISTS (
  SELECT 1 FROM inspection_schedules s2
  WHERE (s2.vehicle_id=v.id OR ((s2.vehicle_id IS NULL OR s2.vehicle_id=0) AND s2.plate_number=v.plate_number))
    AND s2.status IN ('Scheduled','Rescheduled','Pending Verification','Pending Assignment','Overdue / No-Show','Overdue')
)";

if ($q !== '') {
  $sql .= " AND (v.plate_number LIKE ? OR COALESCE(v.vehicle_type,'') LIKE ?)";
}
$sql .= " ORDER BY v.plate_number ASC LIMIT " . (int)$limit;

if ($q !== '') {
  $qq = '%' . $q . '%';
  $stmt = $db->prepare($sql);
  if (!$stmt) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'db_prepare_failed']); exit; }
  $stmt->bind_param('ss', $qq, $qq);
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
?>
