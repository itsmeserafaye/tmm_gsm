<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
if (php_sapi_name() !== 'cli') require_role(['SuperAdmin']);

// 1. Add application_id to documents if missing
$check = $db->query("SHOW COLUMNS FROM documents LIKE 'application_id'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE documents ADD COLUMN application_id INT NULL AFTER plate_number");
    $db->query("ALTER TABLE documents ADD INDEX (application_id)");
    echo "Added application_id to documents.\n";
}

// 2. Add route_ids and fee_receipt_id to franchise_applications if missing
$check = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'route_ids'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE franchise_applications ADD COLUMN route_ids VARCHAR(255) NULL");
    echo "Added route_ids to franchise_applications.\n";
}

$check = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'fee_receipt_id'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE franchise_applications ADD COLUMN fee_receipt_id VARCHAR(64) NULL");
    echo "Added fee_receipt_id to franchise_applications.\n";
}

// 3. Update compliance_cases schema
$check = $db->query("SHOW COLUMNS FROM compliance_cases LIKE 'penalty_amount'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE compliance_cases ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0.00");
    $db->query("ALTER TABLE compliance_cases ADD COLUMN entity_name VARCHAR(128) NULL");
    $db->query("ALTER TABLE compliance_cases ADD COLUMN violation_details TEXT NULL");
    // Make franchise_ref_number optional if we are just tracking entity name
    $db->query("ALTER TABLE compliance_cases MODIFY COLUMN franchise_ref_number VARCHAR(50) NULL");
    echo "Updated compliance_cases schema.\n";
}

// 4. Create LPTRP Routes Table (Real World Process)
$sql = "CREATE TABLE IF NOT EXISTS lptrp_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    start_point VARCHAR(100),
    end_point VARCHAR(100),
    max_vehicle_capacity INT DEFAULT 0,
    current_vehicle_count INT DEFAULT 0,
    status ENUM('Active', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$db->query($sql);

// 5. Enhance Cooperatives Table
$check = $db->query("SHOW TABLES LIKE 'coops'");
if ($check->num_rows > 0) {
    // Alter existing table
    $cols = $db->query("SHOW COLUMNS FROM coops");
    $has_status = false;
    while($r = $cols->fetch_assoc()) {
        if ($r['Field'] === 'consolidation_status') $has_status = true;
    }
    
    if (!$has_status) {
        $db->query("ALTER TABLE coops ADD COLUMN consolidation_status ENUM('Consolidated', 'In Progress', 'Not Consolidated') DEFAULT 'Not Consolidated'");
        $db->query("ALTER TABLE coops ADD COLUMN registration_no VARCHAR(50) NULL");
        $db->query("ALTER TABLE coops ADD COLUMN contact_person VARCHAR(100) NULL");
        $db->query("ALTER TABLE coops ADD COLUMN contact_number VARCHAR(50) NULL");
    }
} else {
    // Create new if missing
    $sql = "CREATE TABLE coops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coop_name VARCHAR(100) NOT NULL,
        registration_no VARCHAR(50),
        lgu_approval_number VARCHAR(50),
        consolidation_status ENUM('Consolidated', 'In Progress', 'Not Consolidated') DEFAULT 'Not Consolidated',
        contact_person VARCHAR(100),
        contact_number VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->query($sql);
}

// 6. Add Validation Flags to Franchise Applications
$cols = $db->query("SHOW COLUMNS FROM franchise_applications");
$has_notes = false;
while($r = $cols->fetch_assoc()) {
    if ($r['Field'] === 'validation_notes') $has_notes = true;
}

if (!$has_notes) {
    $db->query("ALTER TABLE franchise_applications ADD COLUMN validation_notes TEXT NULL COMMENT 'Automated check results'");
    $db->query("ALTER TABLE franchise_applications ADD COLUMN lptrp_status ENUM('Passed', 'Failed', 'Warning') DEFAULT 'Passed'");
    $db->query("ALTER TABLE franchise_applications ADD COLUMN coop_status ENUM('Passed', 'Failed', 'Warning') DEFAULT 'Passed'");
}

// 6b. Add updated_at if missing
$check = $db->query("SHOW COLUMNS FROM franchise_applications LIKE 'updated_at'");
if ($check->num_rows == 0) {
    $db->query("ALTER TABLE franchise_applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// 7. Seed Data (Idempotent)
$res = $db->query("SELECT COUNT(*) as c FROM lptrp_routes");
if ($res && $res->fetch_assoc()['c'] == 0) {
    $db->query("INSERT INTO lptrp_routes (route_code, description, start_point, end_point, max_vehicle_capacity, current_vehicle_count) VALUES 
    ('ROUTE-01', 'Central Loop - Public Market', 'Central Terminal', 'Public Market', 50, 45),
    ('ROUTE-02', 'Northbound - University Belt', 'Plaza', 'State University', 30, 10),
    ('ROUTE-03', 'Coastal Road Connector', 'Wharf', 'Highway Junction', 20, 20)");
}

$res = $db->query("SELECT COUNT(*) as c FROM coops");
if ($res && $res->fetch_assoc()['c'] == 0) {
    $db->query("INSERT INTO coops (coop_name, registration_no, consolidation_status) VALUES 
    ('City Transport Cooperative', 'CDA-12345', 'Consolidated'),
    ('North Drivers Association', 'SEC-67890', 'In Progress'),
    ('Independent Operators Group', 'N/A', 'Not Consolidated')");
}

echo "Franchise App Schema Updated.";
