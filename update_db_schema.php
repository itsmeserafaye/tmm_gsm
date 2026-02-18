<?php
require_once __DIR__ . '/admin/includes/db.php';
$db = db();

// Add columns to terminals table if they don't exist
$sql = "ALTER TABLE terminals 
        ADD COLUMN IF NOT EXISTS owner VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS operator VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS permit_status VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS agreement_type VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS valid_from DATE DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS valid_to DATE DEFAULT NULL";

if ($db->query($sql)) {
    echo "Columns added successfully to terminals table.\n";
} else {
    echo "Error adding columns: " . $db->error . "\n";
}
?>
