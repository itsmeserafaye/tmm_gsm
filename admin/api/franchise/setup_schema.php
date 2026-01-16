<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
if (php_sapi_name() !== 'cli') require_role(['SuperAdmin']);

// Add expiry_date to endorsement_records if not exists
$check = $db->query("SHOW COLUMNS FROM endorsement_records LIKE 'expiry_date'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE endorsement_records ADD COLUMN expiry_date DATE NULL AFTER issued_date");
    // Seed some data for demo
    $db->query("UPDATE endorsement_records SET expiry_date = DATE_ADD(issued_date, INTERVAL 1 YEAR) WHERE expiry_date IS NULL");
    echo "Added expiry_date to endorsement_records.\n";
}

// Add last_notified_at to endorsement_records if not exists
$check = $db->query("SHOW COLUMNS FROM endorsement_records LIKE 'last_notified_at'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE endorsement_records ADD COLUMN last_notified_at TIMESTAMP NULL");
    echo "Added last_notified_at to endorsement_records.\n";
}

echo "Franchise DB Schema Updated.";
