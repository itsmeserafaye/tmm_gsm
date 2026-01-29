<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();

echo "<h2>Tables in DB: " . $db->host_info . "</h2>";
$res = $db->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $tbl = $row[0];
    $c = $db->query("SELECT COUNT(*) FROM `$tbl`")->fetch_row()[0];
    echo "$tbl: $c rows<br>";
}

echo "<h2>Operator Portal Users Content</h2>";
$res = $db->query("SELECT id, email, full_name FROM operator_portal_users");
if ($res) {
    if ($res->num_rows > 0) {
        echo "<table border=1><tr><th>ID</th><th>Email</th><th>Name</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td><td>{$row['full_name']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "Table is empty.";
    }
} else {
    echo "Error: " . $db->error;
}

