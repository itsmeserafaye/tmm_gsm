<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['Admin']);
header('Content-Type: application/json');

$horizon = isset($_GET['horizon_min']) ? (int)$_GET['horizon_min'] : 120;
$routeId = trim($_GET['route_id'] ?? '');
$sql = "SELECT COUNT(*) AS c FROM demand_forecasts WHERE ts BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? MINUTE)";
if ($routeId !== '') { $sql .= " AND route_id=?"; }
$stmt = $routeId !== '' ? $db->prepare($sql) : $db->prepare($sql);
if ($routeId !== '') {
  $stmt->bind_param('is', $horizon, $routeId);
} else {
  $stmt->bind_param('i', $horizon);
}
$stmt->execute();
$res = $stmt->get_result();
$c = $res && ($row=$res->fetch_assoc()) ? (int)$row['c'] : 0;
echo json_encode(['ok'=>true,'count'=>$c,'horizon_min'=>$horizon,'route_id'=>$routeId]);
?> 
