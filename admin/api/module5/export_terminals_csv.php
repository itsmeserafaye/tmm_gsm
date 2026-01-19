<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_permission('reports.export');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="terminals.csv"');

$sql = "SELECT id AS terminal_id, name, location, address, capacity, type FROM terminals WHERE type <> 'Parking' ORDER BY name ASC";
$res = $db->query($sql);

$out = fopen('php://output', 'w');
fputcsv($out, ['terminal_id','name','location','address','capacity','type']);
while ($res && ($row = $res->fetch_assoc())) {
  fputcsv($out, [$row['terminal_id'],$row['name'],$row['location'],$row['address'],$row['capacity'],$row['type']]);
}
fclose($out);

