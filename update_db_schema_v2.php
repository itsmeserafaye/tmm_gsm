<?php
// Mock $_SERVER for CLI
$_SERVER['SCRIPT_NAME'] = '/admin/api/module5/schema_check.php';

require_once __DIR__ . '/admin/includes/db.php';

// Force credentials if needed
putenv('TMM_DB_HOST=localhost');
putenv('TMM_DB_USER=root');
putenv('TMM_DB_PASS=');
putenv('TMM_DB_NAME=tmm'); // Try 'tmm' first

$db = db();

if ($db->connect_error) {
    // Try alternate DB name
    putenv('TMM_DB_NAME=tmm_tmmgosergfvx');
    $db = db();
}

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error . "\n");
}

echo "Connected successfully to " . getenv('TMM_DB_NAME') . "\n";

$sql = "ALTER TABLE terminals 
        ADD COLUMN IF NOT EXISTS owner VARCHAR(100) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS operator VARCHAR(100) DEFAULT NULL";

if ($db->query($sql)) {
    echo "Columns added to terminals table.\n";
} else {
    echo "Error adding columns: " . $db->error . "\n";
}

// Check if terminal_permits exists, if not create it (just in case)
$sql = "CREATE TABLE IF NOT EXISTS terminal_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    file_path VARCHAR(255),
    doc_type VARCHAR(50),
    status VARCHAR(50),
    issue_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (terminal_id)
)";
if ($db->query($sql)) {
    echo "terminal_permits table checked/created.\n";
} else {
    echo "Error checking terminal_permits: " . $db->error . "\n";
}
?>
