<?php
require_once __DIR__ . '/includes/db.php';
$db = db();

function describe($table) {
    global $db;
    echo "Table: $table\n";
    $res = $db->query("DESCRIBE $table");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "  Table not found or error: " . $db->error . "\n";
    }
    echo "\n";
}

describe('documents');
describe('vehicle_documents');
describe('vehicles');

echo "Sample vehicle_documents:\n";
$res = $db->query("SELECT * FROM vehicle_documents LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
