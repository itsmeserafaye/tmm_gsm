<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM terminal_designated_areas WHERE terminal_id = t.id) as area_count,
        (SELECT COUNT(*) FROM terminal_operators WHERE terminal_id = t.id) as operator_count
        FROM terminals t 
        WHERE t.status = 'Active' 
        ORDER BY t.name";

$res = $db->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>