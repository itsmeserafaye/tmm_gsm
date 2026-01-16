<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

echo "Initializing Module 5 Database Tables...\n";

// 1. Terminals
$sql = "CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address TEXT,
    capacity INT DEFAULT 0,
    type VARCHAR(50) DEFAULT 'Terminal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) echo "Table 'terminals' checked/created.\n";
else echo "Error 'terminals': " . $db->error . "\n";

// 2. Terminal Areas / Routes
$sql = "CREATE TABLE IF NOT EXISTS terminal_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    route_name VARCHAR(100) NOT NULL,
    fare_range VARCHAR(50),
    slot_capacity INT DEFAULT 0,
    puv_type VARCHAR(50),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
)";
if ($db->query($sql)) echo "Table 'terminal_areas' checked/created.\n";
else echo "Error 'terminal_areas': " . $db->error . "\n";

// 3. Operators
$sql = "CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    coop_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($db->query($sql)) echo "Table 'operators' checked/created.\n";
else echo "Error 'operators': " . $db->error . "\n";

// 4. Drivers
$sql = "CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    driver_name VARCHAR(255) NOT NULL,
    license_no VARCHAR(50),
    contact_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
)";
if ($db->query($sql)) echo "Table 'drivers' checked/created.\n";
else echo "Error 'drivers': " . $db->error . "\n";

// 5. Terminal Area Operators (Junction)
$sql = "CREATE TABLE IF NOT EXISTS terminal_area_operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    operator_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES terminal_areas(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
)";
if ($db->query($sql)) echo "Table 'terminal_area_operators' checked/created.\n";
else echo "Error 'terminal_area_operators': " . $db->error . "\n";

// 6. Parking Areas
$sql = "CREATE TABLE IF NOT EXISTS parking_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    location TEXT,
    type VARCHAR(50) NOT NULL,
    terminal_id INT NULL,
    total_slots INT DEFAULT 0,
    allowed_puv_types TEXT,
    status VARCHAR(50) DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
)";
if ($db->query($sql)) echo "Table 'parking_areas' checked/created.\n";
else echo "Error 'parking_areas': " . $db->error . "\n";

// 7. Parking Rates
$sql = "CREATE TABLE IF NOT EXISTS parking_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_area_id INT NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    rate_type VARCHAR(50) DEFAULT 'Hourly',
    amount DECIMAL(10,2) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_area_id) REFERENCES parking_areas(id) ON DELETE CASCADE
)";
if ($db->query($sql)) echo "Table 'parking_rates' checked/created.\n";
else echo "Error 'parking_rates': " . $db->error . "\n";

// 8. Parking Transactions
$sql = "CREATE TABLE IF NOT EXISTS parking_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_area_id INT NULL,
    terminal_id INT NULL,
    vehicle_plate VARCHAR(20),
    amount DECIMAL(10,2) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'Paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_area_id) REFERENCES parking_areas(id) ON DELETE SET NULL,
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
)";
if ($db->query($sql)) echo "Table 'parking_transactions' checked/created.\n";
else echo "Error 'parking_transactions': " . $db->error . "\n";

// 9. Parking Violations
$sql = "CREATE TABLE IF NOT EXISTS parking_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_area_id INT NULL,
    vehicle_plate VARCHAR(20),
    violation_type VARCHAR(100) NOT NULL,
    penalty_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_area_id) REFERENCES parking_areas(id) ON DELETE SET NULL
)";
if ($db->query($sql)) echo "Table 'parking_violations' checked/created.\n";
else echo "Error 'parking_violations': " . $db->error . "\n";

echo "Database setup completed.\n";
?>
