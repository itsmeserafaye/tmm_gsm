<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
$db = db();
if (php_sapi_name() !== 'cli') require_role(['SuperAdmin']);

log_msg("Initializing Module 2 Database Tables...\n");

// 1. LPTRP Routes (Local Public Transport Route Plan)
$sql = "CREATE TABLE IF NOT EXISTS lptrp_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(50) NOT NULL,
    route_name VARCHAR(255),
    start_point VARCHAR(255),
    end_point VARCHAR(255),
    max_vehicle_capacity INT DEFAULT 0,
    current_vehicle_count INT DEFAULT 0,
    approval_status VARCHAR(50) DEFAULT 'Approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'lptrp_routes' checked/created.\n");
else log_msg("Error 'lptrp_routes': " . $db->error . "\n");

// 2. Endorsement Records (Permits/Endorsements issued)
$sql = "CREATE TABLE IF NOT EXISTS endorsement_records (
    endorsement_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT,
    issued_by VARCHAR(100) DEFAULT 'System',
    issued_date DATE,
    expiry_date DATE,
    document_ref VARCHAR(100),
    local_permit_no VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES franchise_applications(application_id) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'endorsement_records' checked/created.\n");
else log_msg("Error 'endorsement_records': " . $db->error . "\n");

// 3. Compliance Cases (Violations)
$sql = "CREATE TABLE IF NOT EXISTS compliance_cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(100),
    violation_type VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Open',
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    entity_name VARCHAR(255),
    violation_details TEXT,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actions_taken TEXT,
    resolved_at TIMESTAMP NULL
)";
if ($db->query($sql)) log_msg("Table 'compliance_cases' checked/created.\n");
else log_msg("Error 'compliance_cases': " . $db->error . "\n");

// 4. Update Franchise Applications Table (Ensure columns exist)
$columns_to_add = [
    "route_ids" => "VARCHAR(255)", // Store as comma-separated or JSON if simple, though normalization is better. For now matching usage.
    "fee_receipt_id" => "VARCHAR(100)",
    "validation_notes" => "TEXT",
    "lptrp_status" => "VARCHAR(50) DEFAULT 'Pending'",
    "coop_status" => "VARCHAR(50) DEFAULT 'Pending'",
    "assigned_officer_id" => "INT"
];

foreach ($columns_to_add as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM franchise_applications LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE franchise_applications ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'franchise_applications'.\n");
    }
}

// 5. Ensure 'documents' table exists (Generic or specific)
// Module 1 created 'vehicle_documents'. Module 2 uses 'documents' in submodule1.php code.
// Let's create a generic 'documents' table if it doesn't exist, or alias it.
// Based on submodule1.php: INSERT INTO documents (application_id, type, file_path)
$sql = "CREATE TABLE IF NOT EXISTS documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT,
    type VARCHAR(50),
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_status VARCHAR(50) DEFAULT 'Pending'
)";
if ($db->query($sql)) log_msg("Table 'documents' checked/created.\n");
else log_msg("Error 'documents': " . $db->error . "\n");

// Update documents table to support Module 4 verification flow
$columns_to_add_docs = [
    "plate_number" => "VARCHAR(20)",
    "verified" => "TINYINT(1) DEFAULT 0"
];
foreach ($columns_to_add_docs as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM documents LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE documents ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'documents'.\n");
    }
}

 $seedDemo = (string)($_GET['seed_demo'] ?? '') === '1';
// Seed some LPTRP Data if empty
$check_lptrp = $db->query("SELECT COUNT(*) as c FROM lptrp_routes");
if ($seedDemo && $check_lptrp && $check_lptrp->fetch_assoc()['c'] == 0) {
    $db->query("INSERT INTO lptrp_routes (route_code, route_name, start_point, end_point, max_vehicle_capacity, current_vehicle_count) VALUES 
    ('ROUTE-01', 'Downtown Loop', 'Central Terminal', 'Public Market', 50, 45),
    ('ROUTE-02', 'Uptown Express', 'Central Terminal', 'University Belt', 30, 10),
    ('ROUTE-03', 'Coastal Road', 'Port Area', 'Resort Strip', 40, 38)");
    log_msg("Seeded LPTRP Routes.\n");
}

log_msg("Module 2 Database setup completed.\n");
?>
