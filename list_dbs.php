<?php
$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$res = $conn->query("SHOW DATABASES");
while ($row = $res->fetch_assoc()) {
    echo $row['Database'] . "\n";
}
