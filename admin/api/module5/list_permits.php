<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$sql = "SELECT p.*, t.name as terminal_name, t.type, t.address 
        FROM terminal_permits p 
        JOIN terminals t ON p.terminal_id = t.id 
        ORDER BY p.created_at DESC";
$res = $db->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
