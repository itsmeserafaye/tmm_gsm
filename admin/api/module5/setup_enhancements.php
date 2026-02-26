<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['SuperAdmin']);

$queries = [
    // 1. Facility Owners
    "CREATE TABLE IF NOT EXISTS facility_owners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type ENUM('Person', 'Cooperative', 'Company', 'Government', 'Other') DEFAULT 'Other',
        contact_info VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. Facility Agreements
    "CREATE TABLE IF NOT EXISTS facility_agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        terminal_id INT NOT NULL,
        owner_id INT NOT NULL,
        agreement_type ENUM('MOA', 'Lease Contract', 'Rental Agreement', 'Other') DEFAULT 'MOA',
        reference_no VARCHAR(100),
        rent_amount DECIMAL(15, 2) DEFAULT 0.00,
        rent_frequency ENUM('Monthly', 'Weekly', 'Annual', 'One-time') DEFAULT 'Monthly',
        terms_summary TEXT,
        start_date DATE,
        end_date DATE,
        status ENUM('Active', 'Expired', 'Expiring Soon', 'Terminated') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
        FOREIGN KEY (owner_id) REFERENCES facility_owners(id) ON DELETE CASCADE
    )",

    // 3. Facility Documents (Enhanced Permits/Contracts)
    "CREATE TABLE IF NOT EXISTS facility_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        terminal_id INT NOT NULL,
        agreement_id INT NULL,
        type ENUM('Business Permit', 'Barangay Clearance', 'Terminal Permit', 'MOA', 'Contract', 'Other') DEFAULT 'Other',
        permit_number VARCHAR(100),
        valid_until DATE,
        file_path VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
        FOREIGN KEY (agreement_id) REFERENCES facility_agreements(id) ON DELETE SET NULL
    )"
];

foreach ($queries as $sql) {
    if (!$db->query($sql)) {
        echo "Error executing query: " . $db->error . "\n";
    } else {
        echo "Table created/checked successfully.\n";
    }
}

// Add index for faster lookups
$db->query("ALTER TABLE facility_agreements ADD INDEX idx_terminal_status (terminal_id, status)");

echo "Database enhancements completed.\n";
?>
