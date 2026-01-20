<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: text/plain');
$db = db();
$res = $db->query("SELECT * FROM rbac_roles");
echo "ID | Name | Description\n";
echo "---|------|------------\n";
while ($row = $res->fetch_assoc()) {
    echo "{$row['id']} | {$row['name']} | {$row['description']}\n";
}
