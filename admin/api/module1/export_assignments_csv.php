<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="terminal_assignments.csv"');
$sql = "SELECT id, plate_number, route_id, terminal_name, status, assigned_at FROM terminal_assignments ORDER BY assigned_at DESC";
$res = $db->query($sql);
$out = fopen('php://output', 'w');
fputcsv($out, ['id','plate_number','route_id','terminal_name','status','assigned_at']);
while ($row = $res->fetch_assoc()) {
  fputcsv($out, [$row['id'],$row['plate_number'],$row['route_id'],$row['terminal_name'],$row['status'],$row['assigned_at']]);
}
fclose($out);
