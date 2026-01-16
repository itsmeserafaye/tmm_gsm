<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/util.php';
$db = db();
if (php_sapi_name() !== 'cli') require_role(['SuperAdmin']);

log_msg("Initializing Module 1 Database Tables...\n");

// 1. Coops
$sql = "CREATE TABLE IF NOT EXISTS coops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coop_name VARCHAR(255) NOT NULL,
    address TEXT,
    chairperson_name VARCHAR(255),
    lgu_approval_number VARCHAR(100),
    accreditation_date DATE,
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'coops' checked/created.\n");
else log_msg("Error 'coops': " . $db->error . "\n");

$idxCoopName = $db->query("SHOW INDEX FROM coops WHERE Key_name='uniq_coop_name'");
if ($idxCoopName && $idxCoopName->num_rows === 0) {
    if ($db->query("ALTER TABLE coops ADD UNIQUE KEY uniq_coop_name (coop_name)")) {
        log_msg("Unique index 'uniq_coop_name' ensured on 'coops'.\n");
    }
}

$idxLguApproval = $db->query("SHOW INDEX FROM coops WHERE Key_name='uniq_lgu_approval'");
if ($idxLguApproval && $idxLguApproval->num_rows === 0) {
    if ($db->query("ALTER TABLE coops ADD UNIQUE KEY uniq_lgu_approval (lgu_approval_number)")) {
        log_msg("Unique index 'uniq_lgu_approval' ensured on 'coops'.\n");
    }
}

// Update coops if columns missing
$columns_to_add_coops = [
    "accreditation_date" => "DATE",
    "status" => "VARCHAR(50)"
];
foreach ($columns_to_add_coops as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM coops LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE coops ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'coops'.\n");
    }
}

// 1.1 Operators (Ensure table exists)
$sql = "CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    address TEXT,
    contact_number VARCHAR(50),
    email VARCHAR(100),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'operators' checked/created.\n");

// Update operators if columns missing
$columns_to_add_ops = [
    "address" => "TEXT",
    "contact_number" => "VARCHAR(50)",
    "email" => "VARCHAR(100)",
    "status" => "VARCHAR(50)",
    "contact_info" => "VARCHAR(255)",
    "coop_id" => "INT",
    "coop_name" => "VARCHAR(255)"
];
foreach ($columns_to_add_ops as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM operators LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE operators ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'operators'.\n");
    }
}

// 2. Vehicles
$sql = "CREATE TABLE IF NOT EXISTS vehicles (
    plate_number VARCHAR(20) PRIMARY KEY,
    vehicle_type VARCHAR(50) DEFAULT 'Jeepney',
    operator_id INT,
    operator_name VARCHAR(255),
    coop_id INT,
    coop_name VARCHAR(255),
    make VARCHAR(100),
    model VARCHAR(100),
    year_model VARCHAR(4),
    chassis_number VARCHAR(100),
    engine_number VARCHAR(100),
    franchise_id VARCHAR(100),
    route_id VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'vehicles' checked/created.\n");
else log_msg("Error 'vehicles': " . $db->error . "\n");

// Update vehicles if columns missing
$columns_to_add_veh = [
    "operator_id" => "INT",
    "operator_name" => "VARCHAR(255)",
    "coop_id" => "INT",
    "coop_name" => "VARCHAR(255)",
    "inspection_status" => "VARCHAR(20)",
    "inspection_cert_ref" => "VARCHAR(50)",
    "make" => "VARCHAR(100)",
    "model" => "VARCHAR(100)",
    "year_model" => "VARCHAR(4)",
    "chassis_number" => "VARCHAR(100)",
    "engine_number" => "VARCHAR(100)",
    "franchise_id" => "VARCHAR(100)",
    "route_id" => "VARCHAR(50)"
];
foreach ($columns_to_add_veh as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM vehicles LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE vehicles ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'vehicles'.\n");
    }
}

// 3. Franchise Applications
$sql = "CREATE TABLE IF NOT EXISTS franchise_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(100),
    operator_id INT,
    coop_id INT,
    vehicle_count INT DEFAULT 1,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) DEFAULT 'Pending',
    FOREIGN KEY (coop_id) REFERENCES coops(id) ON DELETE SET NULL
)";
if ($db->query($sql)) log_msg("Table 'franchise_applications' checked/created.\n");
else log_msg("Error 'franchise_applications': " . $db->error . "\n");

// Update franchise_applications if columns missing
$columns_to_add_fa = [
    "operator_name" => "VARCHAR(255)",
    "application_type" => "VARCHAR(50)",
    "submission_date" => "DATE",
    "notes" => "TEXT"
];
foreach ($columns_to_add_fa as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM franchise_applications LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE franchise_applications ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'franchise_applications'.\n");
    }
}

// 4. Routes
$sql = "CREATE TABLE IF NOT EXISTS routes (
    route_id VARCHAR(50) PRIMARY KEY,
    route_name VARCHAR(255) NOT NULL,
    max_vehicle_limit INT DEFAULT 50,
    origin VARCHAR(100),
    destination VARCHAR(100),
    distance_km DECIMAL(10,2),
    fare DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'routes' checked/created.\n");
else log_msg("Error 'routes': " . $db->error . "\n");

// Update routes if columns missing
$columns_to_add_routes = [
    "origin" => "VARCHAR(100)",
    "destination" => "VARCHAR(100)",
    "distance_km" => "DECIMAL(10,2)",
    "fare" => "DECIMAL(10,2)"
];
foreach ($columns_to_add_routes as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM routes LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE routes ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'routes'.\n");
    }
}

// 5. Terminal Assignments
$sql = "CREATE TABLE IF NOT EXISTS terminal_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20),
    route_id VARCHAR(50),
    terminal_name VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Authorized',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(route_id) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'terminal_assignments' checked/created.\n");
else log_msg("Error 'terminal_assignments': " . $db->error . "\n");

// 6. Vehicle Documents
$sql = "CREATE TABLE IF NOT EXISTS vehicle_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20),
    document_type VARCHAR(50),
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'vehicle_documents' checked/created.\n");
else log_msg("Error 'vehicle_documents': " . $db->error . "\n");

// 7. Ownership Transfers
$sql = "CREATE TABLE IF NOT EXISTS ownership_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20),
    new_operator_name VARCHAR(255),
    deed_ref VARCHAR(100),
    transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'ownership_transfers' checked/created.\n");
else log_msg("Error 'ownership_transfers': " . $db->error . "\n");

// 8. Officers (Module 4 Inspectors & Approvers)
$sql = "CREATE TABLE IF NOT EXISTS officers (
    officer_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    active_status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) log_msg("Table 'officers' checked/created.\n");
else log_msg("Error 'officers': " . $db->error . "\n");

// Update officers if columns missing
$columns_to_add_officers = [
    "full_name" => "VARCHAR(100)",
    "active_status" => "TINYINT(1) DEFAULT 1",
    "badge_no" => "VARCHAR(50)"
];
foreach ($columns_to_add_officers as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM officers LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $db->query("ALTER TABLE officers ADD COLUMN $col $def");
        log_msg("Added column '$col' to 'officers'.\n");
    }
}

// 9. Inspection Schedules (Module 4)
$sql = "CREATE TABLE IF NOT EXISTS inspection_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    location VARCHAR(100),
    inspector_id INT,
    status VARCHAR(50) DEFAULT 'Scheduled',
    cr_verified TINYINT(1) DEFAULT 0,
    or_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'inspection_schedules' checked/created.\n");
else log_msg("Error 'inspection_schedules': " . $db->error . "\n");

// 10. Inspection Results (Module 4)
$sql = "CREATE TABLE IF NOT EXISTS inspection_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    overall_status VARCHAR(50) NOT NULL,
    remarks TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'inspection_results' checked/created.\n");
else log_msg("Error 'inspection_results': " . $db->error . "\n");

// 11. Inspection Checklist Items (Module 4)
$sql = "CREATE TABLE IF NOT EXISTS inspection_checklist_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    item_code VARCHAR(50),
    item_label VARCHAR(255),
    status VARCHAR(20),
    FOREIGN KEY (result_id) REFERENCES inspection_results(result_id) ON DELETE CASCADE
)";
if ($db->query($sql)) log_msg("Table 'inspection_checklist_items' checked/created.\n");
else log_msg("Error 'inspection_checklist_items': " . $db->error . "\n");

// 12. Inspection Certificates (Module 4)
$sql = "CREATE TABLE IF NOT EXISTS inspection_certificates (
    cert_id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_number VARCHAR(50) UNIQUE,
    schedule_id INT NOT NULL,
    approved_by INT NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES officers(officer_id) ON DELETE SET NULL
)";
if ($db->query($sql)) log_msg("Table 'inspection_certificates' checked/created.\n");
else log_msg("Error 'inspection_certificates': " . $db->error . "\n");

log_msg("Module 1 Database setup completed.\n");
?>
