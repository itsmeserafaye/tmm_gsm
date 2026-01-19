<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="vehicles.csv"');
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$sql = "SELECT id AS vehicle_id, plate_number, vehicle_type, operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, route_id, status, created_at FROM vehicles";
$conds = []; $params = []; $types = '';
if ($q !== '') { $conds[] = "(plate_number LIKE ? OR operator_name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $types.='ss'; }
if ($status !== '') { $conds[] = "status=?"; $params[]=$status; $types.='s'; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY created_at DESC";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
else { $res = $db->query($sql); }
$out = fopen('php://output', 'w');
fputcsv($out, ['vehicle_id','plate_number','vehicle_type','operator_id','operator_name','engine_no','chassis_no','make','model','year_model','fuel_type','route_id','status','created_at']);
while ($row = $res->fetch_assoc()) {
  fputcsv($out, [$row['vehicle_id'],$row['plate_number'],$row['vehicle_type'],$row['operator_id'],$row['operator_name'],$row['engine_no'],$row['chassis_no'],$row['make'],$row['model'],$row['year_model'],$row['fuel_type'],$row['route_id'],$row['status'],$row['created_at']]);
}
fclose($out);
