<?php
require_once __DIR__ . '/../../includes/db.php';
$db = db();

function createTable($db, $sql) {
    if ($db->query($sql) === TRUE) {
        echo "Table created/checked successfully.\n";
    } else {
        echo "Error creating table: " . $db->error . "\n";
    }
}

function checkColumn($db, $table, $col, $def) {
    $res = $db->query("SHOW COLUMNS FROM $table LIKE '$col'");
    if ($res->num_rows == 0) {
        $db->query("ALTER TABLE $table ADD COLUMN $col $def");
        echo "Added column $col to $table.\n";
    }
}

// 1. Terminals
$sql = "CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
createTable($db, $sql);

// Add columns if missing
checkColumn($db, 'terminals', 'city', 'VARCHAR(100)');
checkColumn($db, 'terminals', 'location', 'VARCHAR(255)');
checkColumn($db, 'terminals', 'type', 'VARCHAR(50)');


// 2. Terminal Designated Areas (Queuing Lines)
$sql = "CREATE TABLE IF NOT EXISTS terminal_designated_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    area_name VARCHAR(100) NOT NULL,
    route_name VARCHAR(255),
    fare_range VARCHAR(50),
    max_slots INT DEFAULT 0,
    current_usage INT DEFAULT 0,
    puv_type VARCHAR(50),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
)";
createTable($db, $sql);

// 3. Parking Areas
$sql = "CREATE TABLE IF NOT EXISTS parking_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100),
    location VARCHAR(255),
    type VARCHAR(50) COMMENT 'Terminal Parking or Standalone',
    terminal_id INT NULL,
    total_slots INT DEFAULT 0,
    allowed_puv_types VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Available',
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
)";
createTable($db, $sql);

// 4. Operators / Associations (Updated structure)
$sql = "CREATE TABLE IF NOT EXISTS terminal_operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NULL, 
    operator_name VARCHAR(255) NOT NULL,
    association_name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'Active',
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
)";
createTable($db, $sql);

// 5. Drivers
$sql = "CREATE TABLE IF NOT EXISTS terminal_drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    license_no VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Active',
    FOREIGN KEY (operator_id) REFERENCES terminal_operators(id) ON DELETE CASCADE
)";
createTable($db, $sql);

// 6. Area Assignments (Linking Operators to Areas)
$sql = "CREATE TABLE IF NOT EXISTS terminal_area_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    operator_id INT NOT NULL,
    FOREIGN KEY (area_id) REFERENCES terminal_designated_areas(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES terminal_operators(id) ON DELETE CASCADE
)";
createTable($db, $sql);

// Insert Dummy Data if empty
$res = $db->query("SELECT COUNT(*) as c FROM terminals");
if ($res && $res->fetch_assoc()['c'] == 0) {
    $db->query("INSERT INTO terminals (name, city, location, type) VALUES 
        ('Central Terminal A', 'Caloocan City', '10th Ave', 'Multimodal'),
        ('North Hub B', 'Caloocan City', 'Monumento', 'Jeepney')");
    
    $termId = $db->insert_id;
    $db->query("INSERT INTO terminal_designated_areas (terminal_id, area_name, route_name, fare_range, max_slots, puv_type) VALUES 
        ($termId, 'Line 1', 'Downtown Route', '15-25 PHP', 20, 'Tricycle'),
        ($termId, 'Line 2', 'Barangay Route', '10-15 PHP', 15, 'Tricycle')");

    $db->query("INSERT INTO parking_areas (name, city, location, type, total_slots, allowed_puv_types) VALUES 
        ('City Hall Parking', 'Caloocan City', 'City Hall Complex', 'Standalone', 50, 'Private, Official'),
        ('Terminal A Annex', 'Caloocan City', '10th Ave', 'Terminal Parking', 30, 'Jeepney, UV Express')");
}

echo "Database setup completed.";
?>