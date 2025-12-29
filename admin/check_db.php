<?php
require_once __DIR__ . '/../includes/db.php';
$db = db();

$tables = ['terminals', 'operators', 'drivers', 'terminal_operators'];
foreach ($tables as $table) {
    $res = $db->query("SHOW TABLES LIKE '$table'");
    if ($res->num_rows > 0) {
        echo "Table $table exists.\n";
        $cols = $db->query("SHOW COLUMNS FROM $table");
        while ($row = $cols->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Table $table does not exist.\n";
    }
}
?>