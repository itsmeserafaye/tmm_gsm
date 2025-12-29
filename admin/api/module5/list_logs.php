<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$sql = "SELECT l.*, t.name as terminal_name 
        FROM terminal_logs l 
        JOIN terminals t ON l.terminal_id = t.id 
        ORDER BY l.log_time DESC LIMIT 50";
$res = $db->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>