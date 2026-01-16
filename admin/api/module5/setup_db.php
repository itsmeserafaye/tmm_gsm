<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
$db = db();
require_role(['SuperAdmin']);

// 1. Create Tables
$queries = [
    // Terminals (Enhanced)
    "CREATE TABLE IF NOT EXISTS terminals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        city VARCHAR(100),
        address TEXT,
        type ENUM('Terminal', 'Parking', 'LoadingBay') DEFAULT 'Terminal',
        capacity INT DEFAULT 0,
        status ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Terminal Areas (Queuing Lines / Routes)
    "CREATE TABLE IF NOT EXISTS terminal_areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        terminal_id INT NOT NULL,
        area_name VARCHAR(255) NOT NULL, -- e.g., Line 1
        route_name VARCHAR(255), -- e.g., Downtown Route
        fare_range VARCHAR(100), -- e.g., 15-25 PHP
        max_slots INT DEFAULT 0,
        current_usage INT DEFAULT 0,
        puv_type ENUM('Tricycle', 'Jeepney', 'Bus', 'Van', 'Other') DEFAULT 'Jeepney',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
    )",

    // Operators
    "CREATE TABLE IF NOT EXISTS operators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        association_name VARCHAR(255),
        contact_info VARCHAR(255),
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // Drivers
    "CREATE TABLE IF NOT EXISTS drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operator_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        license_no VARCHAR(50),
        contact_info VARCHAR(255),
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
    )",

    // Linking Operators to Terminal Areas
    "CREATE TABLE IF NOT EXISTS terminal_area_operators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        area_id INT NOT NULL,
        operator_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (area_id) REFERENCES terminal_areas(id) ON DELETE CASCADE,
        FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
    )",

    // Parking Areas (City-Level)
    "CREATE TABLE IF NOT EXISTS parking_areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        location TEXT,
        type ENUM('Terminal Parking', 'Standalone') DEFAULT 'Standalone',
        terminal_id INT NULL,
        total_slots INT DEFAULT 0,
        allowed_puv_types VARCHAR(255), -- Comma separated or JSON
        status ENUM('Available', 'Full', 'Restricted', 'Maintenance') DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
    )"
];

foreach ($queries as $sql) {
    if (!$db->query($sql)) {
        echo "Error executing query: " . $db->error . "\n";
    } else {
        echo "Table created/checked successfully.\n";
    }
}

// 2. Schema Updates (Add columns if missing)
$check = $db->query("SHOW COLUMNS FROM terminals LIKE 'city'");
if ($check && $check->num_rows == 0) {
    $db->query("ALTER TABLE terminals ADD COLUMN city VARCHAR(100) AFTER name");
    echo "Added city column to terminals.\n";
}

// Check terminal_areas for new columns
$check = $db->query("SHOW COLUMNS FROM terminal_areas LIKE 'route_name'");
if ($check && $check->num_rows == 0) {
    $db->query("ALTER TABLE terminal_areas ADD COLUMN route_name VARCHAR(255) AFTER area_name");
    echo "Added route_name column to terminal_areas.\n";
}

$check = $db->query("SHOW COLUMNS FROM terminal_areas LIKE 'fare_range'");
if ($check && $check->num_rows == 0) {
    $db->query("ALTER TABLE terminal_areas ADD COLUMN fare_range VARCHAR(100) AFTER route_name");
    echo "Added fare_range column to terminal_areas.\n";
}

$check = $db->query("SHOW COLUMNS FROM terminal_areas LIKE 'puv_type'");
if ($check && $check->num_rows == 0) {
    $db->query("ALTER TABLE terminal_areas ADD COLUMN puv_type ENUM('Tricycle', 'Jeepney', 'Bus', 'Van', 'Other') DEFAULT 'Jeepney' AFTER max_slots");
    echo "Added puv_type column to terminal_areas.\n";
}

// 3. Seed Data
 $seedDemo = (string)($_GET['seed_demo'] ?? '') === '1';
// Check if terminals are empty
$result = $db->query("SELECT COUNT(*) as count FROM terminals");
$row = $result->fetch_assoc();
if ($seedDemo && $row['count'] == 0) {
    // Insert Terminals
    $db->query("INSERT INTO terminals (name, city, address, type, capacity) VALUES 
        ('Central Integrated Terminal', 'Caloocan City', '123 Rizal Ave', 'Terminal', 500),
        ('North Bound Terminal', 'Caloocan City', '456 Edsa Ext', 'Terminal', 300),
        ('Barangay 101 Tricycle Hub', 'Caloocan City', 'Corner St', 'Terminal', 50)
    ");
    echo "Seeded Terminals.\n";

    $term_id1 = $db->insert_id; // Approximately correct for demo

    // Insert Terminal Areas
    $db->query("INSERT INTO terminal_areas (terminal_id, area_name, route_name, fare_range, max_slots, puv_type) VALUES 
        ($term_id1, 'Line 1', 'Downtown Route', '15-25 PHP', 20, 'Jeepney'),
        ($term_id1, 'Line 2', 'Uptown Route', '20-30 PHP', 15, 'Jeepney'),
        ($term_id1, 'Bay A', 'Provincial Bus', '150-300 PHP', 5, 'Bus')
    ");
    echo "Seeded Terminal Areas.\n";

    // Insert Operators
    $db->query("INSERT INTO operators (name, association_name) VALUES 
        ('Juan Dela Cruz', 'Jeepney Drivers Assoc.'),
        ('ABC Transport Corp', 'Provincial Bus Operators'),
        ('Maria Santos', 'Tricycle TODA')
    ");
    echo "Seeded Operators.\n";

    $op_id1 = $db->insert_id;

    // Insert Drivers
    $db->query("INSERT INTO drivers (operator_id, name, license_no) VALUES 
        ($op_id1, 'Pedro Penduko', 'N01-23-456789'),
        ($op_id1, 'Cardo Dalisay', 'N02-34-567890')
    ");
    echo "Seeded Drivers.\n";
}

// Check if parking_areas are empty
$result = $db->query("SELECT COUNT(*) as count FROM parking_areas");
$row = $result->fetch_assoc();
if ($seedDemo && $row['count'] == 0) {
    $db->query("INSERT INTO parking_areas (name, city, location, type, total_slots, allowed_puv_types, status) VALUES 
        ('City Hall Parking', 'Caloocan City', 'City Hall Complex', 'Standalone', 100, 'Car,Van', 'Available'),
        ('Public Market Parking', 'Caloocan City', 'Wet Market Side', 'Standalone', 50, 'Tricycle,Jeepney', 'Full'),
        ('Central Terminal Annex', 'Caloocan City', 'Beside Central Terminal', 'Terminal Parking', 200, 'Bus,Jeepney', 'Maintenance')
    ");
    echo "Seeded Parking Areas.\n";
}

echo "Database setup completed.\n";
?>
