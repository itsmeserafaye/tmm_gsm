<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_any_permission(['module1.view','module1.vehicles.write']);
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$recordStatus = trim($_GET['record_status'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) $limit = 200;
if ($limit > 1200) $limit = 1200;
$sql = "SELECT id AS vehicle_id, plate_number, vehicle_type, operator_id, operator_name, coop_name, franchise_id, route_id, engine_no, chassis_no, make, model, year_model, fuel_type, color, record_status, status, created_at FROM vehicles";
$conds = [];
$params = [];
$types = '';
if ($q !== '') {
  $qNoDash = preg_replace('/[^A-Za-z0-9]/', '', $q);
  $conds[] = "(plate_number LIKE ? OR REPLACE(plate_number,'-','') LIKE ? OR operator_name LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$qNoDash%";
  $params[] = "%$q%";
  $types .= 'sss';
}
if ($recordStatus !== '') { $conds[] = "record_status=?"; $params[] = $recordStatus; $types .= 's'; }
if ($status !== '') { $conds[] = "status=?"; $params[] = $status; $types .= 's'; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
if ($params) {
  $stmt = $db->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
} else {
  $res = $db->query($sql);
}
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'data'=>$rows]);
?> 
