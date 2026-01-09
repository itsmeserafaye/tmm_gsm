<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

$routeId = trim($_GET['route_id'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$sql = "SELECT terminal_id, route_id, ts, horizon_min, forecast_trips, model_version FROM demand_forecasts";
$conds = [];
if ($routeId !== '') { $conds[] = "route_id='" . $db->real_escape_string($routeId) . "'"; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY ts DESC LIMIT " . max(1, $limit);
$rows = [];
$res = $db->query($sql);
if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
echo json_encode(['ok'=>true,'data'=>$rows]);
?> 
