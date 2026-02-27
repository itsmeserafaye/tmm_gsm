<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
header('Content-Type: application/json');
require_permission('parking.manage');

// Ensure table exists
$db->query("CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255),
    city VARCHAR(100),
    address TEXT,
    type VARCHAR(50) DEFAULT 'Terminal',
    capacity INT DEFAULT 0,
    category VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$sql = "SELECT * FROM terminals ORDER BY name ASC";
$res = $db->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
