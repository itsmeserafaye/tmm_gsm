<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_any_permission(['tickets.issue','tickets.validate','tickets.settle']);
$res = $db->query("SELECT violation_code, description, fine_amount, category, sts_equivalent_code FROM violation_types ORDER BY violation_code ASC");
$items = [];
while ($r = $res->fetch_assoc()) { $items[] = $r; }
echo json_encode(['items' => $items]);
?> 
