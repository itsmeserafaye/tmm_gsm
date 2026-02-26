<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = db();
require_role(['SuperAdmin']);

$sql = "CREATE TABLE IF NOT EXISTS terminal_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    owner_type ENUM('Person', 'Cooperative', 'Company', 'Government', 'Other') DEFAULT 'Other',
    owner_name VARCHAR(255) NOT NULL,
    owner_contact VARCHAR(255),
    agreement_type ENUM('MOA', 'Lease Contract', 'Rental Agreement', 'Other') DEFAULT 'Other',
    agreement_reference_no VARCHAR(100),
    rent_amount DECIMAL(15, 2) DEFAULT 0.00,
    rent_frequency ENUM('Monthly', 'Weekly', 'Annual', 'One-time') DEFAULT 'Monthly',
    terms_summary TEXT,
    start_date DATE,
    end_date DATE,
    permit_type ENUM('Business Permit', 'Barangay Clearance', 'Terminal Permit', 'Other') DEFAULT 'Other',
    permit_number VARCHAR(100),
    permit_valid_until DATE,
    moa_file_url TEXT,
    contract_file_url TEXT,
    permit_file_url TEXT,
    other_attachments JSON,
    status ENUM('Active', 'Expired', 'Expiring Soon') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_terminal_id (terminal_id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
)";

if ($db->query($sql)) {
    echo "Table terminal_contracts created successfully.\n";
} else {
    echo "Error creating table: " . $db->error . "\n";
}
?>
