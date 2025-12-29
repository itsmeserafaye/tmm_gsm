<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();
header('Content-Type: application/json');

$city = $_GET['city'] ?? 'Caloocan City'; // Default to Caloocan as per prompt
$sql = "SELECT p.*, t.name as terminal_name 
        FROM parking_areas p 
        LEFT JOIN terminals t ON p.terminal_id = t.id 
        WHERE p.city = '$city'
        ORDER BY p.name";

$res = $db->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>