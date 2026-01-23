<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/export.php';
$db = db();
require_permission('reports.export');

$format = tmm_export_format();
tmm_send_export_headers($format, 'terminals');

$sql = "SELECT id AS terminal_id, name, location, address, capacity, type FROM terminals WHERE type <> 'Parking' ORDER BY name ASC";
$res = $db->query($sql);

$headers = ['terminal_id','name','location','address','capacity','type'];
tmm_export_from_result($format, $headers, $res, function ($r) {
  return [
    'terminal_id' => $r['terminal_id'] ?? '',
    'name' => $r['name'] ?? '',
    'location' => $r['location'] ?? '',
    'address' => $r['address'] ?? '',
    'capacity' => $r['capacity'] ?? '',
    'type' => $r['type'] ?? '',
  ];
});
