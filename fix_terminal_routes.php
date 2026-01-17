<?php
require 'admin/includes/db.php';
$db = db();

echo "Fixing terminal_areas route_name...\n";

$routes = $db->query("SELECT route_id, route_name FROM routes")->fetch_all(MYSQLI_ASSOC);
foreach ($routes as $r) {
    $id = $r['route_id'];
    $name = $db->real_escape_string($r['route_name']);
    
    echo "Checking '$name' -> '$id'...\n";
    
    // Update terminal_areas where route_name matches the route's name
    $sql = "UPDATE terminal_areas SET route_name='$id' WHERE route_name='$name'";
    $db->query($sql);
    echo "  Affected: " . $db->affected_rows . "\n";
}

echo "Done.\n";
?>
