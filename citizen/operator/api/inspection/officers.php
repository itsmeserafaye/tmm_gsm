<?php
require_once __DIR__ . '/../common.php';
$res = $db->query("SELECT officer_id AS id, name, role FROM officers WHERE active_status=1 ORDER BY name ASC");
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
json_ok(['items' => $rows]);
