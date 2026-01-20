<?php
require_once __DIR__ . '/../../admin/includes/db.php';

$db = db();

echo "Ensuring Operator Portal schema...\n";

// 1. Operator Portal Fees
$sql = "CREATE TABLE IF NOT EXISTS operator_portal_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plate_number VARCHAR(32) NOT NULL,
    type VARCHAR(64) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Paid', 'Verification') DEFAULT 'Pending',
    proof_doc VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (plate_number)
) ENGINE=InnoDB";
if ($db->query($sql)) {
    echo " - Table 'operator_portal_fees' ensured.\n";
} else {
    echo " - Error creating 'operator_portal_fees': " . $db->error . "\n";
}

// 2. Operator Portal Notifications
$sql = "CREATE TABLE IF NOT EXISTS operator_portal_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(128) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB";
if ($db->query($sql)) {
    echo " - Table 'operator_portal_notifications' ensured.\n";
} else {
    echo " - Error creating 'operator_portal_notifications': " . $db->error . "\n";
}

// 3. Ensure Violations/Tickets table exists (using 'violations' as standard)
// Detailed structure usually exists, but we ensure a basic one if not.
$sql = "CREATE TABLE IF NOT EXISTS violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32) NOT NULL,
    violation_type VARCHAR(128),
    amount DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('Unpaid', 'Paid', 'Appealed') DEFAULT 'Unpaid',
    violation_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number)
) ENGINE=InnoDB";
if ($db->query($sql)) {
    echo " - Table 'violations' ensured.\n";
} else {
    echo " - Error creating 'violations': " . $db->error . "\n";
}

echo "Schema update completed.\n";
