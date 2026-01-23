<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');
$format = tmm_export_format();
tmm_send_export_headers($format, 'vehicles');
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$sql = "SELECT id AS vehicle_id, plate_number, vehicle_type, operator_id, operator_name, engine_no, chassis_no, make, model, year_model, fuel_type, color, route_id, status, created_at FROM vehicles";
$conds = []; $params = []; $types = '';
if ($q !== '') { $conds[] = "(plate_number LIKE ? OR operator_name LIKE ?)"; $params[]="%$q%"; $params[]="%$q%"; $types.='ss'; }
if ($status !== '') { $conds[] = "status=?"; $params[]=$status; $types.='s'; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY created_at DESC";
if ($params) { $stmt = $db->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $res = $stmt->get_result(); }
else { $res = $db->query($sql); }
$headers = ['vehicle_id','plate_number','vehicle_type','operator_id','operator_name','engine_no','chassis_no','make','model','year_model','fuel_type','color','route_id','status','created_at'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'vehicle_id' => $r['vehicle_id'] ?? '',
    'plate_number' => $r['plate_number'] ?? '',
    'vehicle_type' => $r['vehicle_type'] ?? '',
    'operator_id' => $r['operator_id'] ?? '',
    'operator_name' => $r['operator_name'] ?? '',
    'engine_no' => $r['engine_no'] ?? '',
    'chassis_no' => $r['chassis_no'] ?? '',
    'make' => $r['make'] ?? '',
    'model' => $r['model'] ?? '',
    'year_model' => $r['year_model'] ?? '',
    'fuel_type' => $r['fuel_type'] ?? '',
    'color' => $r['color'] ?? '',
    'route_id' => $r['route_id'] ?? '',
    'status' => $r['status'] ?? '',
    'created_at' => $r['created_at'] ?? '',
  ];
});
