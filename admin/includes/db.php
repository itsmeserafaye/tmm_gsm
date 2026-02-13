<?php
require_once __DIR__ . '/../../includes/env.php';
tmm_load_env_default();

function db()
{
  static $conn;
  if ($conn)
    return $conn;

  $host = trim((string) getenv('TMM_DB_HOST'));
  $user = trim((string) getenv('TMM_DB_USER'));
  $pass = (string) getenv('TMM_DB_PASS');
  $name = trim((string) getenv('TMM_DB_NAME'));

  if ($host === '')
    $host = 'localhost';
  if ($user === '')
    $user = 'tmm_tmmgosergfvx';
  if ($name === '')
    $name = 'tmm_tmm';

  $candidates = [
    [$host, $user, $pass, $name],
  ];
  if (strtolower($host) === 'localhost') {
    $candidates[] = [$host, 'root', '', $name];
    $candidates[] = [$host, 'root', '', 'tmm'];
  }

  $lastErr = '';
  foreach ($candidates as $c) {
    [$h, $u, $p, $n] = $c;
    try {
      $try = @new mysqli($h, $u, $p, $n);
      if (!$try->connect_error) {
        $conn = $try;
        $host = $h;
        $user = $u;
        $pass = $p;
        $name = $n;
        break;
      }
      $lastErr = (string) $try->connect_error;
    } catch (Throwable $e) {
      $lastErr = $e->getMessage();
    }
  }
  if (!$conn || $conn->connect_error) {
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $isApi = (strpos($script, '/api/') !== false) || (strpos($script, '\\api\\') !== false);
    if ($isApi) {
      http_response_code(500);
      if (!headers_sent()) header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'db_connect_error']);
      exit;
    }
    die('DB connect error');
  }
  $conn->set_charset('utf8mb4');
  $conn->query("CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32) UNIQUE,
    vehicle_type VARCHAR(64),
    operator_name VARCHAR(128),
    coop_name VARCHAR(128) DEFAULT NULL,
    franchise_id VARCHAR(64) DEFAULT NULL,
    route_id VARCHAR(64) DEFAULT NULL,
    color VARCHAR(64) DEFAULT NULL,
    record_status ENUM('Encoded','Linked','Archived') NOT NULL DEFAULT 'Encoded',
    status VARCHAR(32) DEFAULT 'Declared',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $colVeh = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='vehicles'");
  $haveInspectionStatus = false;
  $haveInspectionCert = false;
  $haveInspectionPassedAt = false;
  if ($colVeh) {
    while ($c = $colVeh->fetch_assoc()) {
      $colName = $c['COLUMN_NAME'] ?? '';
      if ($colName === 'inspection_status') {
        $haveInspectionStatus = true;
      }
      if ($colName === 'inspection_cert_ref') {
        $haveInspectionCert = true;
      }
      if ($colName === 'inspection_passed_at') {
        $haveInspectionPassedAt = true;
      }
    }
  }
  if (!$haveInspectionStatus) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_status VARCHAR(20) DEFAULT 'Pending'");
  }
  if (!$haveInspectionCert) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_cert_ref VARCHAR(64) DEFAULT NULL");
  }
  if (!$haveInspectionPassedAt) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_passed_at DATETIME DEFAULT NULL");
  }
  $colVeh2 = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='vehicles'");
  $vehCols = [];
  if ($colVeh2) {
    while ($c = $colVeh2->fetch_assoc()) {
      $vehCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($vehCols['operator_id'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN operator_id INT DEFAULT NULL");
  }
  if (!isset($vehCols['engine_no'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN engine_no VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($vehCols['chassis_no'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN chassis_no VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($vehCols['make'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN make VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($vehCols['model'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN model VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($vehCols['year_model'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN year_model VARCHAR(8) DEFAULT NULL");
  }
  if (!isset($vehCols['fuel_type'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN fuel_type VARCHAR(64) DEFAULT NULL");
  }
  if (!isset($vehCols['color'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN color VARCHAR(64) DEFAULT NULL");
  }
  if (!isset($vehCols['current_operator_id'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN current_operator_id INT DEFAULT NULL");
  }
  if (!isset($vehCols['ownership_status'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN ownership_status ENUM('Active','Transferred') DEFAULT 'Active'");
  }
  if (!isset($vehCols['record_status'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN record_status ENUM('Encoded','Linked','Archived') NOT NULL DEFAULT 'Encoded'");
  }
  if (!isset($vehCols['submitted_by_portal_user_id'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN submitted_by_portal_user_id INT DEFAULT NULL");
  }
  if (!isset($vehCols['submitted_by_name'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN submitted_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!isset($vehCols['submitted_at'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN submitted_at DATETIME DEFAULT NULL");
  }
  if (!isset($vehCols['compliance_status'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN compliance_status ENUM('Active','Flagged','Suspended','For Review') NOT NULL DEFAULT 'Active'");
  }
  if (!isset($vehCols['compliance_updated_at'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN compliance_updated_at DATETIME DEFAULT NULL");
  }
  if (!isset($vehCols['compliance_reason'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN compliance_reason VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($vehCols['risk_level'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN risk_level ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low'");
  }
  if (!isset($vehCols['or_number'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN or_number VARCHAR(20) DEFAULT NULL");
  }
  if (!isset($vehCols['cr_number'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN cr_number VARCHAR(20) DEFAULT NULL");
  }
  if (!isset($vehCols['cr_issue_date'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN cr_issue_date DATE DEFAULT NULL");
  }
  if (!isset($vehCols['registered_owner'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN registered_owner VARCHAR(120) DEFAULT NULL");
  }
  if (!isset($vehCols['approved_by_user_id'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN approved_by_user_id INT DEFAULT NULL");
  }
  if (!isset($vehCols['approved_by_name'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN approved_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!isset($vehCols['approved_at'])) {
    $conn->query("ALTER TABLE vehicles ADD COLUMN approved_at DATETIME DEFAULT NULL");
  }
  $conn->query("UPDATE vehicles SET record_status=CASE
    WHEN record_status IN ('Encoded','Linked','Archived') THEN record_status
    WHEN operator_id IS NOT NULL AND operator_id>0 THEN 'Linked'
    ELSE 'Encoded' END");
  $conn->query("UPDATE vehicles SET current_operator_id=operator_id WHERE (current_operator_id IS NULL OR current_operator_id=0) AND operator_id IS NOT NULL AND operator_id>0");
  $conn->query("UPDATE vehicles SET status='Declared' WHERE status='Declared/linked'");
  $conn->query("UPDATE vehicles SET status='Declared' WHERE status IS NULL OR status=''");
  $conn->query("CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32),
    type VARCHAR(16),
    file_path VARCHAR(255),
    uploaded_by VARCHAR(64) DEFAULT 'admin',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $colDocs = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='documents'");
  $haveVerifiedCol = false;
  $haveAppIdCol = false;
  $haveExpiryCol = false;
  if ($colDocs) {
    while ($c = $colDocs->fetch_assoc()) {
      $colName = $c['COLUMN_NAME'] ?? '';
      if ($colName === 'verified') {
        $haveVerifiedCol = true;
      }
      if ($colName === 'application_id') {
        $haveAppIdCol = true;
      }
      if ($colName === 'expiry_date') {
        $haveExpiryCol = true;
      }
    }
  }
  if (!$haveVerifiedCol) {
    $conn->query("ALTER TABLE documents ADD COLUMN verified TINYINT(1) DEFAULT 0");
  }
  if (!$haveAppIdCol) {
    $conn->query("ALTER TABLE documents ADD COLUMN application_id INT NULL");
  }
  if (!$haveExpiryCol) {
    $conn->query("ALTER TABLE documents ADD COLUMN expiry_date DATE DEFAULT NULL");
  }
  $conn->query("CREATE TABLE IF NOT EXISTS ownership_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32),
    new_operator_name VARCHAR(128),
    deed_ref VARCHAR(128),
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS vehicle_ownership_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    from_operator_id INT DEFAULT NULL,
    to_operator_id INT DEFAULT NULL,
    transfer_type ENUM('Sale','Donation','Inheritance','Reassignment') NOT NULL DEFAULT 'Reassignment',
    lto_reference_no VARCHAR(128) DEFAULT NULL,
    deed_of_sale_path VARCHAR(255) DEFAULT NULL,
    orcr_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    effective_date DATE DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (from_operator_id) REFERENCES operators(id) ON DELETE SET NULL,
    FOREIGN KEY (to_operator_id) REFERENCES operators(id) ON DELETE SET NULL
  ) ENGINE=InnoDB");
  $colVot = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='vehicle_ownership_transfers'");
  $votCols = [];
  if ($colVot) {
    while ($c = $colVot->fetch_assoc()) {
      $votCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($votCols['to_operator_name'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN to_operator_name VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($votCols['requested_by_portal_user_id'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN requested_by_portal_user_id INT DEFAULT NULL");
  }
  if (!isset($votCols['requested_by_name'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN requested_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!isset($votCols['requested_at'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN requested_at DATETIME DEFAULT NULL");
  }
  if (!isset($votCols['or_path'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN or_path VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($votCols['cr_path'])) {
    $conn->query("ALTER TABLE vehicle_ownership_transfers ADD COLUMN cr_path VARCHAR(255) DEFAULT NULL");
  }
  $conn->query("CREATE TABLE IF NOT EXISTS terminal_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32),
    route_id VARCHAR(64),
    terminal_name VARCHAR(128),
    status VARCHAR(32) DEFAULT 'Authorized',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_plate (plate_number),
    INDEX (route_id),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $colTA = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='terminal_assignments'");
  $taCols = [];
  if ($colTA) {
    while ($c = $colTA->fetch_assoc()) {
      $taCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (isset($taCols['id']) && !isset($taCols['assignment_id'])) {
    $conn->query("ALTER TABLE terminal_assignments CHANGE COLUMN id assignment_id INT AUTO_INCREMENT");
  }
  if (!isset($taCols['terminal_id'])) {
    $conn->query("ALTER TABLE terminal_assignments ADD COLUMN terminal_id INT DEFAULT NULL");
  }
  if (!isset($taCols['vehicle_id'])) {
    $conn->query("ALTER TABLE terminal_assignments ADD COLUMN vehicle_id INT DEFAULT NULL");
  }
  $idxVeh = $conn->query("SHOW INDEX FROM terminal_assignments WHERE Key_name='uniq_vehicle'");
  if (!$idxVeh || $idxVeh->num_rows == 0) {
    $conn->query("ALTER TABLE terminal_assignments ADD UNIQUE KEY uniq_vehicle (vehicle_id)");
  }
  $conn->query("UPDATE terminal_assignments ta JOIN vehicles v ON v.plate_number=ta.plate_number SET ta.vehicle_id=v.id WHERE ta.vehicle_id IS NULL");

  $conn->query("CREATE TABLE IF NOT EXISTS terminals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    capacity INT DEFAULT 0,
    type VARCHAR(50) DEFAULT 'Terminal',
    category VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $colTerm = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='terminals'");
  $termCols = [];
  if ($colTerm) {
    while ($c = $colTerm->fetch_assoc()) {
      $termCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($termCols['location'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN location VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($termCols['capacity'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN capacity INT DEFAULT 0");
  }
  if (!isset($termCols['type'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN type VARCHAR(50) DEFAULT 'Terminal'");
  }
  if (!isset($termCols['city'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN city VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($termCols['address'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN address TEXT");
  }
  if (!isset($termCols['category'])) {
    $conn->query("ALTER TABLE terminals ADD COLUMN category VARCHAR(100) DEFAULT NULL");
  }
  $conn->query("UPDATE terminals SET city='Caloocan City' WHERE type <> 'Parking' AND (city IS NULL OR city='')");
  $conn->query("UPDATE terminals SET location=TRIM(CONCAT(COALESCE(address,''), CASE WHEN address IS NOT NULL AND address <> '' AND city IS NOT NULL AND city <> '' THEN ', ' ELSE '' END, COALESCE(city,''))) WHERE (location IS NULL OR location='') AND ((address IS NOT NULL AND address <> '') OR (city IS NOT NULL AND city <> ''))");
  $conn->query("UPDATE terminal_assignments ta JOIN terminals t ON t.name=ta.terminal_name SET ta.terminal_id=t.id WHERE ta.terminal_id IS NULL AND ta.terminal_name IS NOT NULL AND ta.terminal_name<>''");

  $conn->query("CREATE TABLE IF NOT EXISTS terminal_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    route_id VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_terminal_route (terminal_id, route_id),
    INDEX idx_terminal (terminal_id),
    INDEX idx_route (route_id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Victory Liner - Caloocan (Monumento)','Monumento','Caloocan City','Monumento area',0,'Terminal','Provincial Bus Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Victory Liner - Caloocan (Monumento)')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Baliwag Transit - Caloocan','Caloocan','Caloocan City','Caloocan area',0,'Terminal','Provincial Bus Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Baliwag Transit - Caloocan')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Monumento Carousel Terminal','Monumento','Caloocan City','EDSA Carousel - Monumento',0,'Terminal','City Transport Hub'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Monumento Carousel Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'SM City Caloocan Terminal','North Caloocan','Caloocan City','SM City Caloocan',0,'Terminal','District Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='SM City Caloocan Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Deparo UV Express Terminal','Deparo','Caloocan City','Deparo',0,'Terminal','District Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Deparo UV Express Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Sangandaan / City Hall Jeep Terminal','Sangandaan','Caloocan City','Sangandaan / City Hall',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Sangandaan / City Hall Jeep Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Bagumbong - Novaliches Jeep Terminal','Bagumbong','Caloocan City','Bagumbong / Novaliches',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagumbong - Novaliches Jeep Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Bagumbong Tricycle Terminal','Bagumbong','Caloocan City','Bagumbong',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagumbong Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Deparo Tricycle Terminal','Deparo','Caloocan City','Deparo',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Deparo Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Camarin Tricycle Terminal','Camarin','Caloocan City','Camarin',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Camarin Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Tala Tricycle Terminal','Tala','Caloocan City','Tala (Near Tala Hospital)',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Tala Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Sangandaan Tricycle Terminal','Sangandaan','Caloocan City','Sangandaan',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Sangandaan Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT 'Grace Park Tricycle Terminal','Grace Park','Caloocan City','Grace Park',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Grace Park Tricycle Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type, category)
                SELECT '5th Avenue Tricycle Terminal','5th Avenue','Caloocan City','5th Avenue',0,'Terminal','Barangay Transport Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='5th Avenue Tricycle Terminal')");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-VICTORY-OLONGAPO' FROM terminals t WHERE t.name='Victory Liner - Caloocan (Monumento)'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-VICTORY-IBA_ZAMBALES' FROM terminals t WHERE t.name='Victory Liner - Caloocan (Monumento)'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-VICTORY-SANTA_CRUZ_ZAMBALES' FROM terminals t WHERE t.name='Victory Liner - Caloocan (Monumento)'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-VICTORY-BAGUIO' FROM terminals t WHERE t.name='Victory Liner - Caloocan (Monumento)'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-VICTORY-TUGUEGARAO' FROM terminals t WHERE t.name='Victory Liner - Caloocan (Monumento)'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-BALIWAG-BALIWAG' FROM terminals t WHERE t.name='Baliwag Transit - Caloocan'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-BALIWAG-CABANATUAN' FROM terminals t WHERE t.name='Baliwag Transit - Caloocan'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-BALIWAG-GAPAN' FROM terminals t WHERE t.name='Baliwag Transit - Caloocan'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-BALIWAG-SAN_JOSE_NE' FROM terminals t WHERE t.name='Baliwag Transit - Caloocan'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-CAROUSEL-MONUMENTO-PITX' FROM terminals t WHERE t.name='Monumento Carousel Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-SM_CALOOCAN-NOVALICHES_BAYAN' FROM terminals t WHERE t.name='SM City Caloocan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-SM_CALOOCAN-SM_FAIRVIEW' FROM terminals t WHERE t.name='SM City Caloocan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-SM_CALOOCAN-BLUMENTRITT' FROM terminals t WHERE t.name='SM City Caloocan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-SM_CALOOCAN-MONUMENTO' FROM terminals t WHERE t.name='SM City Caloocan Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-DEPARO-SM_NORTH' FROM terminals t WHERE t.name='Deparo UV Express Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-DEPARO-CUBAO' FROM terminals t WHERE t.name='Deparo UV Express Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-DEPARO-QUEZON_AVE' FROM terminals t WHERE t.name='Deparo UV Express Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-DEPARO-NOVALICHES_BAYAN' FROM terminals t WHERE t.name='Deparo UV Express Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-SANGANDAAN-DIVISORIA' FROM terminals t WHERE t.name='Sangandaan / City Hall Jeep Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-SANGANDAAN-RECTO' FROM terminals t WHERE t.name='Sangandaan / City Hall Jeep Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-SANGANDAAN-BLUMENTRITT' FROM terminals t WHERE t.name='Sangandaan / City Hall Jeep Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-SANGANDAAN-MONUMENTO' FROM terminals t WHERE t.name='Sangandaan / City Hall Jeep Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-BAGUMBONG-NOVALICHES_BAYAN' FROM terminals t WHERE t.name='Bagumbong - Novaliches Jeep Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-BAGUMBONG-SM_FAIRVIEW' FROM terminals t WHERE t.name='Bagumbong - Novaliches Jeep Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-BAGUMBONG-DEPARO' FROM terminals t WHERE t.name='Bagumbong - Novaliches Jeep Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-BAGUMBONG-DEPARO' FROM terminals t WHERE t.name='Bagumbong Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-BAGUMBONG-CAMARIN' FROM terminals t WHERE t.name='Bagumbong Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-BAGUMBONG-TALA_HOSPITAL' FROM terminals t WHERE t.name='Bagumbong Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-DEPARO-CAMARIN' FROM terminals t WHERE t.name='Deparo Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-DEPARO-BAGUMBONG' FROM terminals t WHERE t.name='Deparo Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-DEPARO-SUSANO_ROAD' FROM terminals t WHERE t.name='Deparo Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-CAMARIN-DEPARO' FROM terminals t WHERE t.name='Camarin Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-CAMARIN-BAGUMBONG' FROM terminals t WHERE t.name='Camarin Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-CAMARIN-TALA' FROM terminals t WHERE t.name='Camarin Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-TALA-CAMARIN' FROM terminals t WHERE t.name='Tala Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-TALA-BAGUMBONG' FROM terminals t WHERE t.name='Tala Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-TALA-DEPARO' FROM terminals t WHERE t.name='Tala Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-SANGANDAAN-GRACE_PARK' FROM terminals t WHERE t.name='Sangandaan Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-SANGANDAAN-MONUMENTO' FROM terminals t WHERE t.name='Sangandaan Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-SANGANDAAN-5TH_AVE' FROM terminals t WHERE t.name='Sangandaan Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-GRACE_PARK-10TH_AVE' FROM terminals t WHERE t.name='Grace Park Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-GRACE_PARK-5TH_AVE' FROM terminals t WHERE t.name='Grace Park Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-GRACE_PARK-RIZAL_AVE' FROM terminals t WHERE t.name='Grace Park Tricycle Terminal'");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-5TH_AVE-A_MABINI' FROM terminals t WHERE t.name='5th Avenue Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-5TH_AVE-SANGANDAAN' FROM terminals t WHERE t.name='5th Avenue Tricycle Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-5TH_AVE-GRACE_PARK' FROM terminals t WHERE t.name='5th Avenue Tricycle Terminal'");

  $conn->query("CREATE TABLE IF NOT EXISTS parking_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    slot_no VARCHAR(64) NOT NULL,
    status ENUM('Free','Occupied') NOT NULL DEFAULT 'Free',
    UNIQUE KEY uniq_terminal_slot (terminal_id, slot_no),
    INDEX (terminal_id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS parking_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    slot_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    or_no VARCHAR(64) NOT NULL,
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    exported_to_treasury TINYINT(1) DEFAULT 0,
    exported_at DATETIME DEFAULT NULL,
    INDEX (vehicle_id),
    INDEX (slot_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(slot_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS terminal_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    vehicle_id INT DEFAULT NULL,
    plate_number VARCHAR(32) NOT NULL,
    priority ENUM('Normal','Priority') NOT NULL DEFAULT 'Normal',
    status ENUM('Queued','Served','Cancelled') NOT NULL DEFAULT 'Queued',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    served_at DATETIME DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    INDEX idx_terminal_status_created (terminal_id, status, created_at),
    INDEX idx_terminal_priority_created (terminal_id, priority, created_at),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS parking_slot_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    slot_id INT NOT NULL,
    vehicle_id INT DEFAULT NULL,
    plate_number VARCHAR(32) DEFAULT NULL,
    payment_id INT DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    or_no VARCHAR(64) DEFAULT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME DEFAULT NULL,
    occupied_by_user_id INT DEFAULT NULL,
    occupied_by_name VARCHAR(150) DEFAULT NULL,
    released_by_user_id INT DEFAULT NULL,
    released_by_name VARCHAR(150) DEFAULT NULL,
    INDEX idx_terminal_timein (terminal_id, time_in),
    INDEX idx_slot_open (slot_id, time_out),
    INDEX idx_vehicle (vehicle_id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES parking_slots(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_id) REFERENCES parking_payments(payment_id) ON DELETE SET NULL
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id VARCHAR(64) UNIQUE,
    route_name VARCHAR(128),
    max_vehicle_limit INT DEFAULT 50,
    status VARCHAR(32) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $colRoutes = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='routes'");
  $routeCols = [];
  if ($colRoutes) {
    while ($c = $colRoutes->fetch_assoc()) {
      $routeCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($routeCols['route_code'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN route_code VARCHAR(64) DEFAULT NULL");
  }
  if (!isset($routeCols['via'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN via TEXT DEFAULT NULL");
  }
  if (!isset($routeCols['vehicle_type'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN vehicle_type ENUM('Tricycle','Jeepney','UV','Bus') DEFAULT NULL");
  }
  if (!isset($routeCols['route_category'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN route_category VARCHAR(64) DEFAULT NULL");
  }
  if (!isset($routeCols['origin'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN origin VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($routeCols['destination'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN destination VARCHAR(100) DEFAULT NULL");
  }
  if (!isset($routeCols['structure'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN structure ENUM('Loop','Point-to-Point') DEFAULT NULL");
  }
  if (!isset($routeCols['distance_km'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN distance_km DECIMAL(10,2) DEFAULT NULL");
  }
  if (!isset($routeCols['fare'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN fare DECIMAL(10,2) DEFAULT NULL");
  }
  if (!isset($routeCols['fare_min'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN fare_min DECIMAL(10,2) DEFAULT NULL");
  }
  if (!isset($routeCols['fare_max'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN fare_max DECIMAL(10,2) DEFAULT NULL");
  }
  if (!isset($routeCols['authorized_units'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN authorized_units INT DEFAULT NULL");
  }
  if (!isset($routeCols['approved_by'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN approved_by VARCHAR(128) DEFAULT NULL");
  }
  if (!isset($routeCols['approved_date'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN approved_date DATE DEFAULT NULL");
  }
  $rtVtCol = $conn->query("SHOW COLUMNS FROM routes LIKE 'vehicle_type'");
  $rtVt = $rtVtCol ? $rtVtCol->fetch_assoc() : null;
  $rtVtType = strtolower((string)($rtVt['Type'] ?? ''));
  if ($rtVtType !== '' && substr($rtVtType, 0, 5) === 'enum(' && strpos($rtVtType, "'modern jeepney'") === false) {
    @$conn->query("ALTER TABLE routes MODIFY COLUMN vehicle_type ENUM('Tricycle','Jeepney','Modern Jeepney','UV','UV Express','Bus','City Bus','Mini-bus','E-trike','Shuttle Van','Motorized Pedicab','Taxi') DEFAULT NULL");
  }
  $conn->query("UPDATE routes SET route_code=route_id WHERE (route_code IS NULL OR route_code='') AND COALESCE(route_id,'')<>''");
  $conn->query("UPDATE routes SET route_id=route_code WHERE (route_id IS NULL OR route_id='') AND COALESCE(route_code,'')<>''");
  $conn->query("UPDATE routes SET status=CASE WHEN status IN ('Active','Inactive') THEN status ELSE 'Active' END");
  $conn->query("UPDATE routes SET fare_min=COALESCE(fare_min, fare) WHERE fare_min IS NULL AND fare IS NOT NULL");
  $conn->query("UPDATE routes SET fare_max=COALESCE(fare_max, fare) WHERE fare_max IS NULL AND fare IS NOT NULL");
  $conn->query("UPDATE routes
                SET authorized_units = CASE
                  WHEN COALESCE(vehicle_type,'')='Bus' AND (route_code LIKE '%CAROUSEL%' OR route_id LIKE '%CAROUSEL%') THEN 200
                  WHEN COALESCE(vehicle_type,'')='Bus' AND COALESCE(distance_km,0) >= 150 THEN 25
                  WHEN COALESCE(vehicle_type,'')='Bus' AND COALESCE(distance_km,0) >= 80 THEN 35
                  WHEN COALESCE(vehicle_type,'')='Bus' AND COALESCE(distance_km,0) > 0 THEN 55
                  WHEN COALESCE(vehicle_type,'')='Bus' AND COALESCE(fare, COALESCE(fare_min, fare_max), 0) >= 700 THEN 25
                  WHEN COALESCE(vehicle_type,'')='Bus' AND COALESCE(fare, COALESCE(fare_min, fare_max), 0) >= 300 THEN 35
                  WHEN COALESCE(vehicle_type,'')='Bus' THEN 45
                  WHEN COALESCE(vehicle_type,'')='UV' AND COALESCE(distance_km,0) >= 25 THEN 80
                  WHEN COALESCE(vehicle_type,'')='UV' AND COALESCE(distance_km,0) > 0 THEN 110
                  WHEN COALESCE(vehicle_type,'')='UV' AND COALESCE(fare, COALESCE(fare_min, fare_max), 0) >= 50 THEN 90
                  WHEN COALESCE(vehicle_type,'')='UV' THEN 120
                  WHEN COALESCE(vehicle_type,'')='Jeepney' AND COALESCE(distance_km,0) >= 18 THEN 90
                  WHEN COALESCE(vehicle_type,'')='Jeepney' AND COALESCE(distance_km,0) > 0 THEN 120
                  WHEN COALESCE(vehicle_type,'')='Jeepney' AND COALESCE(fare, COALESCE(fare_min, fare_max), 0) >= 25 THEN 100
                  WHEN COALESCE(vehicle_type,'')='Jeepney' THEN 140
                  WHEN COALESCE(vehicle_type,'')='Tricycle' AND COALESCE(distance_km,0) >= 6 THEN 180
                  WHEN COALESCE(vehicle_type,'')='Tricycle' AND COALESCE(distance_km,0) > 0 THEN 220
                  WHEN COALESCE(vehicle_type,'')='Tricycle' AND COALESCE(fare, COALESCE(fare_min, fare_max), 0) >= 30 THEN 220
                  WHEN COALESCE(vehicle_type,'')='Tricycle' THEN 260
                  ELSE COALESCE(NULLIF(max_vehicle_limit,0), 50)
                END
                WHERE authorized_units IS NULL OR authorized_units<=0");

  $conn->query("CREATE TABLE IF NOT EXISTS route_vehicle_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    vehicle_type ENUM('Tricycle','Jeepney','UV','Bus') NOT NULL,
    authorized_units INT DEFAULT NULL,
    fare_min DECIMAL(10,2) DEFAULT NULL,
    fare_max DECIMAL(10,2) DEFAULT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_route_vehicle (route_id, vehicle_type),
    INDEX idx_route_status (route_id, status),
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $rvtCol = $conn->query("SHOW COLUMNS FROM route_vehicle_types LIKE 'vehicle_type'");
  $rvt = $rvtCol ? $rvtCol->fetch_assoc() : null;
  $rvtType = strtolower((string)($rvt['Type'] ?? ''));
  if ($rvtType !== '' && substr($rvtType, 0, 5) === 'enum(' && strpos($rvtType, "'modern jeepney'") === false) {
    @$conn->query("ALTER TABLE route_vehicle_types MODIFY COLUMN vehicle_type ENUM('Tricycle','Jeepney','Modern Jeepney','UV','UV Express','Bus','City Bus','Mini-bus','E-trike','Shuttle Van','Motorized Pedicab','Taxi') NOT NULL");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS route_legacy_map (
    legacy_route_pk INT PRIMARY KEY,
    route_id INT NOT NULL,
    vehicle_type ENUM('Tricycle','Jeepney','UV','Bus') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_route_vehicle (route_id, vehicle_type),
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $rlmCol = $conn->query("SHOW COLUMNS FROM route_legacy_map LIKE 'vehicle_type'");
  $rlm = $rlmCol ? $rlmCol->fetch_assoc() : null;
  $rlmType = strtolower((string)($rlm['Type'] ?? ''));
  if ($rlmType !== '' && substr($rlmType, 0, 5) === 'enum(' && strpos($rlmType, "'modern jeepney'") === false) {
    @$conn->query("ALTER TABLE route_legacy_map MODIFY COLUMN vehicle_type ENUM('Tricycle','Jeepney','Modern Jeepney','UV','UV Express','Bus','City Bus','Mini-bus','E-trike','Shuttle Van','Motorized Pedicab','Taxi') NOT NULL");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS tricycle_service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_code VARCHAR(64) NOT NULL UNIQUE,
    area_name VARCHAR(128) NOT NULL,
    barangay VARCHAR(128) DEFAULT NULL,
    terminal_id INT DEFAULT NULL,
    authorized_units INT DEFAULT NULL,
    fare_min DECIMAL(10,2) DEFAULT NULL,
    fare_max DECIMAL(10,2) DEFAULT NULL,
    coverage_notes TEXT DEFAULT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_terminal (terminal_id),
    INDEX idx_status (status),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE SET NULL
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS tricycle_service_area_points (
    point_id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    point_name VARCHAR(128) NOT NULL,
    point_type ENUM('Landmark','Terminal','Barangay','Other') DEFAULT 'Landmark',
    lat DECIMAL(10,7) DEFAULT NULL,
    lng DECIMAL(10,7) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    INDEX idx_area (area_id),
    FOREIGN KEY (area_id) REFERENCES tricycle_service_areas(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS tricycle_legacy_map (
    legacy_route_pk INT PRIMARY KEY,
    service_area_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_area (service_area_id),
    FOREIGN KEY (service_area_id) REFERENCES tricycle_service_areas(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $check = $conn->query("SELECT COUNT(*) AS c FROM routes");
  if ($check && ($check->fetch_assoc()['c'] ?? 0) == 0) {
    $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, structure, fare_min, fare_max, fare, status) VALUES
      ('BUS-VICTORY-OLONGAPO','BUS-VICTORY-OLONGAPO','Caloocan - Olongapo','Bus','Caloocan','Olongapo','Point-to-Point',300,350,300,'Active'),
      ('BUS-VICTORY-IBA_ZAMBALES','BUS-VICTORY-IBA_ZAMBALES','Caloocan - Iba, Zambales','Bus','Caloocan','Iba, Zambales','Point-to-Point',450,520,450,'Active'),
      ('BUS-VICTORY-SANTA_CRUZ_ZAMBALES','BUS-VICTORY-SANTA_CRUZ_ZAMBALES','Caloocan - Santa Cruz, Zambales','Bus','Caloocan','Santa Cruz, Zambales','Point-to-Point',480,550,480,'Active'),
      ('BUS-VICTORY-BAGUIO','BUS-VICTORY-BAGUIO','Caloocan - Baguio','Bus','Caloocan','Baguio','Point-to-Point',750,900,750,'Active'),
      ('BUS-VICTORY-TUGUEGARAO','BUS-VICTORY-TUGUEGARAO','Caloocan - Tuguegarao','Bus','Caloocan','Tuguegarao','Point-to-Point',1000,1300,1000,'Active'),
      ('BUS-BALIWAG-BALIWAG','BUS-BALIWAG-BALIWAG','Caloocan - Baliwag','Bus','Caloocan','Baliwag','Point-to-Point',110,140,110,'Active'),
      ('BUS-BALIWAG-CABANATUAN','BUS-BALIWAG-CABANATUAN','Caloocan - Cabanatuan','Bus','Caloocan','Cabanatuan','Point-to-Point',210,260,210,'Active'),
      ('BUS-BALIWAG-GAPAN','BUS-BALIWAG-GAPAN','Caloocan - Gapan','Bus','Caloocan','Gapan','Point-to-Point',190,240,190,'Active'),
      ('BUS-BALIWAG-SAN_JOSE_NE','BUS-BALIWAG-SAN_JOSE_NE','Caloocan - San Jose, NE','Bus','Caloocan','San Jose, NE','Point-to-Point',280,340,280,'Active'),
      ('BUS-CAROUSEL-MONUMENTO-PITX','BUS-CAROUSEL-MONUMENTO-PITX','Monumento - PITX','Bus','Monumento','PITX','Point-to-Point',75.50,75.50,75.50,'Active'),
      ('UV-SM_CALOOCAN-NOVALICHES_BAYAN','UV-SM_CALOOCAN-NOVALICHES_BAYAN','SM Caloocan - Novaliches Bayan','UV','SM City Caloocan','Novaliches Bayan','Point-to-Point',25,30,25,'Active'),
      ('UV-SM_CALOOCAN-SM_FAIRVIEW','UV-SM_CALOOCAN-SM_FAIRVIEW','SM Caloocan - SM Fairview','UV','SM City Caloocan','SM Fairview','Point-to-Point',30,35,30,'Active'),
      ('UV-SM_CALOOCAN-BLUMENTRITT','UV-SM_CALOOCAN-BLUMENTRITT','SM Caloocan - Blumentritt','UV','SM City Caloocan','Blumentritt','Point-to-Point',35,45,35,'Active'),
      ('UV-SM_CALOOCAN-MONUMENTO','UV-SM_CALOOCAN-MONUMENTO','SM Caloocan - Monumento','UV','SM City Caloocan','Monumento','Point-to-Point',30,40,30,'Active'),
      ('UV-DEPARO-SM_NORTH','UV-DEPARO-SM_NORTH','Deparo - SM North','UV','Deparo','SM North','Point-to-Point',45,55,45,'Active'),
      ('UV-DEPARO-CUBAO','UV-DEPARO-CUBAO','Deparo - Cubao','UV','Deparo','Cubao','Point-to-Point',50,60,50,'Active'),
      ('UV-DEPARO-QUEZON_AVE','UV-DEPARO-QUEZON_AVE','Deparo - Quezon Ave','UV','Deparo','Quezon Ave','Point-to-Point',45,55,45,'Active'),
      ('UV-DEPARO-NOVALICHES_BAYAN','UV-DEPARO-NOVALICHES_BAYAN','Deparo - Novaliches Bayan','UV','Deparo','Novaliches Bayan','Point-to-Point',25,30,25,'Active'),
      ('JEEP-SANGANDAAN-DIVISORIA','JEEP-SANGANDAAN-DIVISORIA','Sangandaan - Divisoria','Jeepney','Sangandaan','Divisoria','Point-to-Point',30,40,30,'Active'),
      ('JEEP-SANGANDAAN-RECTO','JEEP-SANGANDAAN-RECTO','Sangandaan - Recto','Jeepney','Sangandaan','Recto','Point-to-Point',28,35,28,'Active'),
      ('JEEP-SANGANDAAN-BLUMENTRITT','JEEP-SANGANDAAN-BLUMENTRITT','Sangandaan - Blumentritt','Jeepney','Sangandaan','Blumentritt','Point-to-Point',20,25,20,'Active'),
      ('JEEP-SANGANDAAN-MONUMENTO','JEEP-SANGANDAAN-MONUMENTO','Sangandaan - Monumento','Jeepney','Sangandaan','Monumento','Point-to-Point',13,18,13,'Active'),
      ('JEEP-BAGUMBONG-NOVALICHES_BAYAN','JEEP-BAGUMBONG-NOVALICHES_BAYAN','Bagumbong - Novaliches Bayan','Jeepney','Bagumbong','Novaliches Bayan','Point-to-Point',13,15,13,'Active'),
      ('JEEP-BAGUMBONG-SM_FAIRVIEW','JEEP-BAGUMBONG-SM_FAIRVIEW','Bagumbong - SM Fairview','Jeepney','Bagumbong','SM Fairview','Point-to-Point',18,22,18,'Active'),
      ('JEEP-BAGUMBONG-DEPARO','JEEP-BAGUMBONG-DEPARO','Bagumbong - Deparo','Jeepney','Bagumbong','Deparo','Point-to-Point',13,18,13,'Active'),
      ('TRI-BAGUMBONG-DEPARO','TRI-BAGUMBONG-DEPARO','Bagumbong - Deparo','Tricycle','Bagumbong','Deparo','Point-to-Point',15,30,15,'Active'),
      ('TRI-BAGUMBONG-CAMARIN','TRI-BAGUMBONG-CAMARIN','Bagumbong - Camarin','Tricycle','Bagumbong','Camarin','Point-to-Point',15,30,15,'Active'),
      ('TRI-BAGUMBONG-TALA_HOSPITAL','TRI-BAGUMBONG-TALA_HOSPITAL','Bagumbong - Tala Hospital','Tricycle','Bagumbong','Tala Hospital','Point-to-Point',15,30,15,'Active'),
      ('TRI-DEPARO-CAMARIN','TRI-DEPARO-CAMARIN','Deparo - Camarin','Tricycle','Deparo','Camarin','Point-to-Point',15,35,15,'Active'),
      ('TRI-DEPARO-BAGUMBONG','TRI-DEPARO-BAGUMBONG','Deparo - Bagumbong','Tricycle','Deparo','Bagumbong','Point-to-Point',15,35,15,'Active'),
      ('TRI-DEPARO-SUSANO_ROAD','TRI-DEPARO-SUSANO_ROAD','Deparo - Susano Road','Tricycle','Deparo','Susano Road','Point-to-Point',15,35,15,'Active'),
      ('TRI-CAMARIN-DEPARO','TRI-CAMARIN-DEPARO','Camarin - Deparo','Tricycle','Camarin','Deparo','Point-to-Point',15,35,15,'Active'),
      ('TRI-CAMARIN-BAGUMBONG','TRI-CAMARIN-BAGUMBONG','Camarin - Bagumbong','Tricycle','Camarin','Bagumbong','Point-to-Point',15,35,15,'Active'),
      ('TRI-CAMARIN-TALA','TRI-CAMARIN-TALA','Camarin - Tala','Tricycle','Camarin','Tala','Point-to-Point',15,35,15,'Active'),
      ('TRI-TALA-CAMARIN','TRI-TALA-CAMARIN','Tala - Camarin','Tricycle','Tala','Camarin','Point-to-Point',20,40,20,'Active'),
      ('TRI-TALA-BAGUMBONG','TRI-TALA-BAGUMBONG','Tala - Bagumbong','Tricycle','Tala','Bagumbong','Point-to-Point',20,40,20,'Active'),
      ('TRI-TALA-DEPARO','TRI-TALA-DEPARO','Tala - Deparo','Tricycle','Tala','Deparo','Point-to-Point',20,40,20,'Active'),
      ('TRI-SANGANDAAN-GRACE_PARK','TRI-SANGANDAAN-GRACE_PARK','Sangandaan - Grace Park','Tricycle','Sangandaan','Grace Park','Point-to-Point',15,30,15,'Active'),
      ('TRI-SANGANDAAN-MONUMENTO','TRI-SANGANDAAN-MONUMENTO','Sangandaan - Monumento','Tricycle','Sangandaan','Monumento','Point-to-Point',15,30,15,'Active'),
      ('TRI-SANGANDAAN-5TH_AVE','TRI-SANGANDAAN-5TH_AVE','Sangandaan - 5th Ave','Tricycle','Sangandaan','5th Ave','Point-to-Point',15,30,15,'Active'),
      ('TRI-GRACE_PARK-10TH_AVE','TRI-GRACE_PARK-10TH_AVE','Grace Park - 10th Ave','Tricycle','Grace Park','10th Ave','Point-to-Point',15,25,15,'Active'),
      ('TRI-GRACE_PARK-5TH_AVE','TRI-GRACE_PARK-5TH_AVE','Grace Park - 5th Ave','Tricycle','Grace Park','5th Ave','Point-to-Point',15,25,15,'Active'),
      ('TRI-GRACE_PARK-RIZAL_AVE','TRI-GRACE_PARK-RIZAL_AVE','Grace Park - Rizal Ave','Tricycle','Grace Park','Rizal Ave','Point-to-Point',15,25,15,'Active'),
      ('TRI-5TH_AVE-A_MABINI','TRI-5TH_AVE-A_MABINI','5th Ave - A. Mabini','Tricycle','5th Ave','A. Mabini','Point-to-Point',15,30,15,'Active'),
      ('TRI-5TH_AVE-SANGANDAAN','TRI-5TH_AVE-SANGANDAAN','5th Ave - Sangandaan','Tricycle','5th Ave','Sangandaan','Point-to-Point',15,30,15,'Active'),
      ('TRI-5TH_AVE-GRACE_PARK','TRI-5TH_AVE-GRACE_PARK','5th Ave - Grace Park','Tricycle','5th Ave','Grace Park','Point-to-Point',15,30,15,'Active')");
  }

  $seedLegacyRoutes = false;
  if ($seedLegacyRoutes) {
  $busRoutes = [
    ['BUS-04', 'BUS-04', 'EDSA Carousel North (Monumento–PITX)', 'Bus', 'Monumento', 'PITX Parañaque', 'EDSA (Balintawak • SM North • Quezon Ave • Ortigas • Ayala • Taft)', 'Point-to-Point', 250, 100, 100, 'Active'],
    ['BUS-05', 'BUS-05', 'EDSA Carousel South (PITX–Monumento)', 'Bus', 'PITX Parañaque', 'Monumento', 'EDSA (Taft • Ayala • Ortigas • Quezon Ave • SM North • Balintawak)', 'Point-to-Point', 250, 100, 100, 'Active'],
    ['BUS-06', 'BUS-06', 'Monumento–Baguio City (Victory Liner)', 'Bus', 'Monumento', 'Baguio City', 'NLEX • TPLEX • Kennon Road', 'Point-to-Point', 250, 30, 30, 'Active'],
    ['BUS-07', 'BUS-07', 'Monumento–Dagupan (Victory Liner)', 'Bus', 'Monumento', 'Dagupan', 'NLEX • TPLEX', 'Point-to-Point', 200, 25, 25, 'Active'],
    ['BUS-08', 'BUS-08', 'Monumento–Olongapo (Victory Liner)', 'Bus', 'Monumento', 'Olongapo', 'NLEX • SCTEX', 'Point-to-Point', 120, 20, 20, 'Active'],
    ['BUS-09', 'BUS-09', 'Monumento–San Fernando Pampanga (Victory Liner)', 'Bus', 'Monumento', 'San Fernando, Pampanga', 'NLEX', 'Point-to-Point', 80, 15, 15, 'Active'],
    ['BUS-10', 'BUS-10', 'Monumento–Iba Zambales (Victory Liner)', 'Bus', 'Monumento', 'Iba, Zambales', 'NLEX • SCTEX', 'Point-to-Point', 150, 15, 15, 'Active'],
    ['BUS-11', 'BUS-11', 'Monumento–Dagupan (Five Star)', 'Bus', 'Monumento', 'Dagupan', 'NLEX • TPLEX', 'Point-to-Point', 200, 20, 20, 'Active'],
    ['BUS-12', 'BUS-12', 'Monumento–San Isidro Pangasinan (Five Star)', 'Bus', 'Monumento', 'San Isidro, Pangasinan', 'NLEX • TPLEX', 'Point-to-Point', 180, 15, 15, 'Active'],
    ['BUS-13', 'BUS-13', 'Monumento–Sta Cruz Zambales (Genesis)', 'Bus', 'Monumento', 'Sta. Cruz, Zambales', 'NLEX • SCTEX', 'Point-to-Point', 140, 15, 15, 'Active'],
  ];

  foreach ($busRoutes as $route) {
    $routeId = $conn->real_escape_string($route[0]);
    $checkRoute = $conn->query("SELECT route_id FROM routes WHERE route_id='$routeId' LIMIT 1");
    if ($checkRoute && $checkRoute->num_rows == 0) {
      $routeCode = $conn->real_escape_string($route[1]);
      $routeName = $conn->real_escape_string($route[2]);
      $vehicleType = $conn->real_escape_string($route[3]);
      $origin = $conn->real_escape_string($route[4]);
      $destination = $conn->real_escape_string($route[5]);
      $via = $conn->real_escape_string($route[6]);
      $structure = $conn->real_escape_string($route[7]);
      $distanceKm = (float) $route[8];
      $authorizedUnits = (int) $route[9];
      $maxVehicleLimit = (int) $route[10];
      $status = $conn->real_escape_string($route[11]);

      $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, authorized_units, max_vehicle_limit, status) 
                    VALUES ('$routeId','$routeCode','$routeName','$vehicleType','$origin','$destination','$via','$structure',$distanceKm,$authorizedUnits,$maxVehicleLimit,'$status')");
    }
  }

  // Link new bus routes to Monumento Terminal
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-04' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-05' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-06' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-07' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-08' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-09' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-10' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-11' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-12' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-13' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");

  // Add Jeepney routes (JEEP-10 to JEEP-19)
  $jeepRoutes = [
    ['JEEP-10', 'JEEP-10', 'Novaliches–Rizal Ave via Banal', 'Jeepney', 'Novaliches Bayan', 'Rizal Avenue', 'Banal • Quirino Highway', 'Point-to-Point', 12, 40, 40, 'Active'],
    ['JEEP-11', 'JEEP-11', 'Bagong Barrio–G. De Jesus/EDSA', 'Jeepney', 'Bagong Barrio', 'G. De Jesus/EDSA', 'Repara Road • Milagrosa Street', 'Point-to-Point', 8, 35, 35, 'Active'],
    ['JEEP-12', 'JEEP-12', 'Bagong Silang–Novaliches via Susano', 'Jeepney', 'Bagong Silang', 'Novaliches', 'Susano Road • Quirino Highway', 'Point-to-Point', 10, 40, 40, 'Active'],
    ['JEEP-13', 'JEEP-13', 'Bagumbong–Novaliches', 'Jeepney', 'Bagumbong', 'Novaliches', 'Quirino Highway', 'Point-to-Point', 9, 35, 35, 'Active'],
    ['JEEP-14', 'JEEP-14', 'Novaliches–Bignay', 'Jeepney', 'Novaliches', 'Bignay', 'General Luis • Llano Road', 'Point-to-Point', 7, 30, 30, 'Active'],
    ['JEEP-15', 'JEEP-15', 'Blumentritt–Novaliches', 'Jeepney', 'Blumentritt', 'Novaliches', 'Rizal Ave • Quirino Highway', 'Point-to-Point', 15, 45, 45, 'Active'],
    ['JEEP-16', 'JEEP-16', 'Monumento–Malabon via Acacia', 'Jeepney', 'Monumento', 'Malabon Bayan', 'Acacia Avenue', 'Point-to-Point', 6, 35, 35, 'Active'],
    ['JEEP-17', 'JEEP-17', 'Monumento–Navotas', 'Jeepney', 'Monumento', 'Navotas', 'Rizal Ave Ext • C-4 Road', 'Point-to-Point', 8, 35, 35, 'Active'],
    ['JEEP-18', 'JEEP-18', 'Monumento–Malanday', 'Jeepney', 'Monumento', 'Malanday', 'Rizal Ave • Malanday Road', 'Point-to-Point', 7, 30, 30, 'Active'],
    ['JEEP-19', 'JEEP-19', 'Monumento–Angat', 'Jeepney', 'Monumento', 'Angat', 'NLEX', 'Point-to-Point', 65, 25, 25, 'Active'],
  ];

  foreach ($jeepRoutes as $route) {
    $routeId = $conn->real_escape_string($route[0]);
    $checkRoute = $conn->query("SELECT route_id FROM routes WHERE route_id='$routeId' LIMIT 1");
    if ($checkRoute && $checkRoute->num_rows == 0) {
      $routeCode = $conn->real_escape_string($route[1]);
      $routeName = $conn->real_escape_string($route[2]);
      $vehicleType = $conn->real_escape_string($route[3]);
      $origin = $conn->real_escape_string($route[4]);
      $destination = $conn->real_escape_string($route[5]);
      $via = $conn->real_escape_string($route[6]);
      $structure = $conn->real_escape_string($route[7]);
      $distanceKm = (float) $route[8];
      $authorizedUnits = (int) $route[9];
      $maxVehicleLimit = (int) $route[10];
      $status = $conn->real_escape_string($route[11]);

      $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, authorized_units, max_vehicle_limit, status) 
                    VALUES ('$routeId','$routeCode','$routeName','$vehicleType','$origin','$destination','$via','$structure',$distanceKm,$authorizedUnits,$maxVehicleLimit,'$status')");
    }
  }

  // Add Tricycle routes (TRI-04 to TRI-13)
  $triRoutes = [
    ['TRI-04', 'TRI-04', 'Bagong Silang Phase 1-3 Loop', 'Tricycle', 'Phase 1', 'Phase 3', 'Internal roads', 'Loop', 3, 25, 25, 'Active'],
    ['TRI-05', 'TRI-05', 'Bagong Silang Phase 4-9 Loop', 'Tricycle', 'Phase 4', 'Phase 9', 'Internal roads', 'Loop', 4, 30, 30, 'Active'],
    ['TRI-06', 'TRI-06', 'Bagong Silang Phase 10-12 Loop', 'Tricycle', 'Phase 10', 'Phase 12', 'Internal roads', 'Loop', 3, 25, 25, 'Active'],
    ['TRI-07', 'TRI-07', 'Sta. Quiteria Loop', 'Tricycle', 'Sta. Quiteria Terminal', 'Sta. Quiteria Barangay', 'Local roads', 'Loop', 2, 20, 20, 'Active'],
    ['TRI-08', 'TRI-08', 'Zabarte Road Loop', 'Tricycle', 'Zabarte Terminal', 'Zabarte Area', 'Zabarte Road', 'Loop', 2, 20, 20, 'Active'],
    ['TRI-09', 'TRI-09', 'General Luis Loop', 'Tricycle', 'General Luis Terminal', 'General Luis Area', 'Local roads', 'Loop', 2, 20, 20, 'Active'],
    ['TRI-10', 'TRI-10', 'Grace Park Loop', 'Tricycle', 'Grace Park Terminal', 'Grace Park Area', '5th Ave • 10th Ave', 'Loop', 2, 20, 20, 'Active'],
    ['TRI-11', 'TRI-11', 'Camarin Loop', 'Tricycle', 'Camarin Terminal', 'Camarin Area', 'Local roads', 'Loop', 3, 25, 25, 'Active'],
    ['TRI-12', 'TRI-12', 'Deparo Loop', 'Tricycle', 'Deparo Terminal', 'Deparo Area', 'Deparo Road', 'Loop', 2, 20, 20, 'Active'],
    ['TRI-13', 'TRI-13', 'Tala Loop', 'Tricycle', 'Tala Terminal', 'Tala Area', 'Local roads', 'Loop', 2, 20, 20, 'Active'],
  ];

  foreach ($triRoutes as $route) {
    $routeId = $conn->real_escape_string($route[0]);
    $checkRoute = $conn->query("SELECT route_id FROM routes WHERE route_id='$routeId' LIMIT 1");
    if ($checkRoute && $checkRoute->num_rows == 0) {
      $routeCode = $conn->real_escape_string($route[1]);
      $routeName = $conn->real_escape_string($route[2]);
      $vehicleType = $conn->real_escape_string($route[3]);
      $origin = $conn->real_escape_string($route[4]);
      $destination = $conn->real_escape_string($route[5]);
      $via = $conn->real_escape_string($route[6]);
      $structure = $conn->real_escape_string($route[7]);
      $distanceKm = (float) $route[8];
      $authorizedUnits = (int) $route[9];
      $maxVehicleLimit = (int) $route[10];
      $status = $conn->real_escape_string($route[11]);

      $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, authorized_units, max_vehicle_limit, status) 
                    VALUES ('$routeId','$routeCode','$routeName','$vehicleType','$origin','$destination','$via','$structure',$distanceKm,$authorizedUnits,$maxVehicleLimit,'$status')");
    }
  }

  // Add UV Express routes (UV-10 to UV-19)
  $uvRoutes = [
    ['UV-10', 'UV-10', 'Deparo–SM North EDSA', 'UV', 'Deparo', 'SM North EDSA', 'Quirino Highway • EDSA', 'Point-to-Point', 15, 30, 30, 'Active'],
    ['UV-11', 'UV-11', 'Deparo–Blumentritt', 'UV', 'Deparo', 'Blumentritt', 'Quirino Highway • Rizal Ave', 'Point-to-Point', 18, 25, 25, 'Active'],
    ['UV-12', 'UV-12', 'Cubao–Novaliches (24/7)', 'UV', 'Cubao', 'Novaliches', 'EDSA • Quirino Highway', 'Point-to-Point', 12, 35, 35, 'Active'],
    ['UV-13', 'UV-13', 'Cubao–Deparo', 'UV', 'Cubao', 'Deparo', 'EDSA • Quirino Highway', 'Point-to-Point', 14, 30, 30, 'Active'],
    ['UV-14', 'UV-14', 'SM North–TM Kalaw Manila', 'UV', 'SM North EDSA', 'TM Kalaw, Manila', 'EDSA • Taft Ave', 'Point-to-Point', 20, 30, 30, 'Active'],
    ['UV-15', 'UV-15', 'Tandang Sora–TM Kalaw Manila', 'UV', 'Tandang Sora', 'TM Kalaw, Manila', 'Commonwealth • EDSA • Taft', 'Point-to-Point', 22, 25, 25, 'Active'],
    ['UV-16', 'UV-16', 'Monumento–Cubao', 'UV', 'Monumento', 'Cubao', 'EDSA', 'Point-to-Point', 12, 35, 35, 'Active'],
    ['UV-17', 'UV-17', 'Monumento–SM Fairview', 'UV', 'Monumento', 'SM Fairview', 'EDSA • Commonwealth', 'Point-to-Point', 18, 30, 30, 'Active'],
    ['UV-18', 'UV-18', 'Novaliches–Trinoma', 'UV', 'Novaliches', 'Trinoma', 'Quirino Highway • EDSA', 'Point-to-Point', 10, 30, 30, 'Active'],
    ['UV-19', 'UV-19', 'Bagong Silang–SM North', 'UV', 'Bagong Silang', 'SM North EDSA', 'Quirino Highway • EDSA', 'Point-to-Point', 16, 25, 25, 'Active'],
  ];

  foreach ($uvRoutes as $route) {
    $routeId = $conn->real_escape_string($route[0]);
    $checkRoute = $conn->query("SELECT route_id FROM routes WHERE route_id='$routeId' LIMIT 1");
    if ($checkRoute && $checkRoute->num_rows == 0) {
      $routeCode = $conn->real_escape_string($route[1]);
      $routeName = $conn->real_escape_string($route[2]);
      $vehicleType = $conn->real_escape_string($route[3]);
      $origin = $conn->real_escape_string($route[4]);
      $destination = $conn->real_escape_string($route[5]);
      $via = $conn->real_escape_string($route[6]);
      $structure = $conn->real_escape_string($route[7]);
      $distanceKm = (float) $route[8];
      $authorizedUnits = (int) $route[9];
      $maxVehicleLimit = (int) $route[10];
      $status = $conn->real_escape_string($route[11]);

      $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, distance_km, authorized_units, max_vehicle_limit, status) 
                    VALUES ('$routeId','$routeCode','$routeName','$vehicleType','$origin','$destination','$via','$structure',$distanceKm,$authorizedUnits,$maxVehicleLimit,'$status')");
    }
  }

  // Link new routes to appropriate terminals
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-10' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-11' FROM terminals t WHERE t.name='Bagong Barrio Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-12' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-13' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-14' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-15' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-16' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-17' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-18' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-19' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-10' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-11' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-13' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-16' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-17' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-18' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-19' FROM terminals t WHERE t.name='Bagong Silang Terminal'");


  $legacySeed = [
    ['old' => 'R-12', 'old_name' => 'Central Loop', 'new' => 'TR-01', 'new_name' => 'Monumento Loop', 'vehicle_type' => 'Jeepney', 'origin' => 'Monumento', 'destination' => 'Monumento', 'via' => 'EDSA • Rizal Ave Ext • Samson Rd • 10th Ave/5th Ave (Caloocan)', 'structure' => 'Loop', 'units' => 60],
    ['old' => 'R-08', 'old_name' => 'East Corridor', 'new' => 'TR-02', 'new_name' => 'Monumento–Sangandaan Connector', 'vehicle_type' => 'Jeepney', 'origin' => 'Monumento', 'destination' => 'Sangandaan', 'via' => 'Samson Rd • Sangandaan Area (Caloocan)', 'structure' => 'Point-to-Point', 'units' => 45],
    ['old' => 'R-05', 'old_name' => 'North Spur', 'new' => 'TR-03', 'new_name' => 'Deparo–Tala Service', 'vehicle_type' => 'Jeepney', 'origin' => 'Deparo', 'destination' => 'Tala', 'via' => 'Deparo Rd • Tala Area (Caloocan)', 'structure' => 'Point-to-Point', 'units' => 40],
  ];
  foreach ($legacySeed as $ls) {
    $old = (string) $ls['old'];
    $oldName = (string) $ls['old_name'];
    $new = (string) $ls['new'];
    $chkOld = $conn->prepare("SELECT id FROM routes WHERE route_id=? AND route_name=? LIMIT 1");
    if (!$chkOld)
      continue;
    $chkOld->bind_param('ss', $old, $oldName);
    $chkOld->execute();
    $oldRow = $chkOld->get_result()->fetch_assoc();
    $chkOld->close();
    if (!$oldRow)
      continue;

    $chkNew = $conn->prepare("SELECT 1 FROM routes WHERE route_id=? LIMIT 1");
    if (!$chkNew)
      continue;
    $chkNew->bind_param('s', $new);
    $chkNew->execute();
    $newExists = (bool) $chkNew->get_result()->fetch_row();
    $chkNew->close();
    if ($newExists)
      continue;

    $stmtU = $conn->prepare("UPDATE routes
      SET route_id=?, route_code=?, route_name=?, vehicle_type=?, origin=?, destination=?, via=?, structure=?, authorized_units=?, max_vehicle_limit=?, status='Active'
      WHERE route_id=? AND route_name=?");
    if (!$stmtU)
      continue;
    $units = (int) $ls['units'];
    $newName = (string) $ls['new_name'];
    $vehType = (string) $ls['vehicle_type'];
    $orig = (string) $ls['origin'];
    $dest = (string) $ls['destination'];
    $via = (string) $ls['via'];
    $struct = (string) $ls['structure'];
    $stmtU->bind_param('ssssssssiiss', $new, $new, $newName, $vehType, $orig, $dest, $via, $struct, $units, $units, $old, $oldName);
    $stmtU->execute();
    $stmtU->close();
  }

  }

  $conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("INSERT IGNORE INTO app_settings(setting_key, setting_value) VALUES
    ('weather_lat','14.5995'),
    ('weather_lon','120.9842'),
    ('weather_label','Manila, PH'),
    ('events_country','PH'),
    ('events_city','Manila'),
    ('events_rss_url',''),
    ('recaptcha_site_key',''),
    ('recaptcha_secret_key','')");

  $conn->query("CREATE TABLE IF NOT EXISTS external_data_cache (
    cache_key VARCHAR(190) PRIMARY KEY,
    payload LONGTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_expires (expires_at)
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS trusted_devices (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_type VARCHAR(20) NOT NULL,
    user_id BIGINT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    user_agent_hash VARCHAR(64) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_device (user_type, user_id, device_hash),
    INDEX idx_expires (expires_at)
  ) ENGINE=InnoDB");
  $colsTd = $conn->query("SHOW COLUMNS FROM trusted_devices LIKE 'user_agent_hash'");
  if (!$colsTd || $colsTd->num_rows === 0) {
    $conn->query("ALTER TABLE trusted_devices ADD COLUMN user_agent_hash VARCHAR(64) DEFAULT NULL AFTER device_hash");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS operator_portal_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) DEFAULT NULL,
    contact_info VARCHAR(64) DEFAULT NULL,
    association_name VARCHAR(128) DEFAULT NULL,
    operator_type VARCHAR(16) DEFAULT 'Individual',
    approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    verification_submitted_at DATETIME DEFAULT NULL,
    approval_remarks TEXT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    approved_by INT DEFAULT NULL,
    terms_accepted_at DATETIME DEFAULT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verified_at DATETIME DEFAULT NULL,
    status ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_approval_status (approval_status)
  ) ENGINE=InnoDB");

  $opUserCols = [
    'operator_type' => "VARCHAR(16) DEFAULT 'Individual'",
    'puv_operator_id' => "INT DEFAULT NULL",
    'approval_status' => "ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'",
    'verification_submitted_at' => "DATETIME DEFAULT NULL",
    'approval_remarks' => "TEXT DEFAULT NULL",
    'approved_at' => "DATETIME DEFAULT NULL",
    'approved_by' => "INT DEFAULT NULL",
    'terms_accepted_at' => "DATETIME DEFAULT NULL",
    'email_verified' => "TINYINT(1) NOT NULL DEFAULT 0",
    'email_verified_at' => "DATETIME DEFAULT NULL",
  ];
  foreach ($opUserCols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM operator_portal_users LIKE '$col'");
    if ($check && $check->num_rows == 0) {
      $conn->query("ALTER TABLE operator_portal_users ADD COLUMN $col $def");
    }
  }
  $opApprovalCol = $conn->query("SHOW COLUMNS FROM operator_portal_users LIKE 'approval_status'");
  if ($opApprovalCol && ($row = $opApprovalCol->fetch_assoc())) {
    $t = (string)($row['Type'] ?? '');
    if (stripos($t, 'pending') === false || stripos($t, 'approved') === false || stripos($t, 'rejected') === false) {
      $conn->query("ALTER TABLE operator_portal_users MODIFY COLUMN approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
    }
  }
  $idxOpApproval = $conn->query("SHOW INDEX FROM operator_portal_users WHERE Key_name='idx_approval_status'");
  if (!$idxOpApproval || $idxOpApproval->num_rows == 0) {
    $conn->query("ALTER TABLE operator_portal_users ADD INDEX idx_approval_status (approval_status)");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS operator_portal_documents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_key VARCHAR(64) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('Pending','Valid','Invalid') NOT NULL DEFAULT 'Pending',
    remarks TEXT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    UNIQUE KEY uniq_user_doc (user_id, doc_key),
    INDEX idx_user_status (user_id, status),
    CONSTRAINT fk_operator_portal_documents_user FOREIGN KEY (user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS operator_portal_user_plates (
    user_id INT NOT NULL,
    plate_number VARCHAR(32) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, plate_number),
    UNIQUE KEY uniq_plate (plate_number),
    CONSTRAINT fk_operator_portal_user_plates_user FOREIGN KEY (user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_operator_portal_user_plates_plate FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS operator_portal_applications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plate_number VARCHAR(32) NOT NULL,
    type VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    notes TEXT DEFAULT NULL,
    documents LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    CONSTRAINT fk_operator_portal_app_user FOREIGN KEY (user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_operator_portal_app_plate FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS operator_record_submissions (
    submission_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portal_user_id INT NOT NULL,
    operator_type VARCHAR(16) DEFAULT 'Individual',
    registered_name VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    contact_no VARCHAR(64) DEFAULT NULL,
    email VARCHAR(128) DEFAULT NULL,
    coop_name VARCHAR(128) DEFAULT NULL,
    status ENUM('Submitted','Approved','Rejected') NOT NULL DEFAULT 'Submitted',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_by_name VARCHAR(150) DEFAULT NULL,
    approved_by_user_id INT DEFAULT NULL,
    approved_by_name VARCHAR(150) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    approval_remarks TEXT DEFAULT NULL,
    operator_id INT DEFAULT NULL,
    INDEX idx_portal_status (portal_user_id, status),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    FOREIGN KEY (portal_user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $idxOrs = $conn->query("SHOW INDEX FROM operator_record_submissions WHERE Key_name='uniq_operator_record_portal_user'");
  if (!$idxOrs || $idxOrs->num_rows == 0) {
    $conn->query("DELETE ors1 FROM operator_record_submissions ors1
                  JOIN operator_record_submissions ors2
                    ON ors1.portal_user_id = ors2.portal_user_id
                   AND ors1.submission_id < ors2.submission_id");
    $conn->query("ALTER TABLE operator_record_submissions ADD UNIQUE KEY uniq_operator_record_portal_user (portal_user_id)");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS vehicle_record_submissions (
    submission_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portal_user_id INT NOT NULL,
    operator_submission_id BIGINT DEFAULT NULL,
    plate_number VARCHAR(32) NOT NULL,
    vehicle_type VARCHAR(64) NOT NULL,
    engine_no VARCHAR(20) DEFAULT NULL,
    chassis_no VARCHAR(17) DEFAULT NULL,
    make VARCHAR(100) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    year_model VARCHAR(8) DEFAULT NULL,
    fuel_type VARCHAR(64) DEFAULT NULL,
    color VARCHAR(64) DEFAULT NULL,
    or_number VARCHAR(12) DEFAULT NULL,
    cr_number VARCHAR(64) DEFAULT NULL,
    cr_issue_date DATE DEFAULT NULL,
    registered_owner VARCHAR(150) DEFAULT NULL,
    cr_file_path VARCHAR(255) DEFAULT NULL,
    or_file_path VARCHAR(255) DEFAULT NULL,
    or_expiry_date DATE DEFAULT NULL,
    status ENUM('Submitted','Approved','Rejected') NOT NULL DEFAULT 'Submitted',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_by_name VARCHAR(150) DEFAULT NULL,
    approved_by_user_id INT DEFAULT NULL,
    approved_by_name VARCHAR(150) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    approval_remarks TEXT DEFAULT NULL,
    vehicle_id INT DEFAULT NULL,
    INDEX idx_portal_status (portal_user_id, status),
    INDEX idx_plate (plate_number),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    FOREIGN KEY (portal_user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS vehicle_link_requests (
    request_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    portal_user_id INT NOT NULL,
    plate_number VARCHAR(32) NOT NULL,
    requested_operator_id INT DEFAULT NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_by_name VARCHAR(150) DEFAULT NULL,
    reviewed_by_user_id INT DEFAULT NULL,
    reviewed_by_name VARCHAR(150) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    INDEX idx_portal_status (portal_user_id, status),
    INDEX idx_plate_status (plate_number, status),
    FOREIGN KEY (portal_user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  // Module 2: Franchise Management Tables
  $conn->query("CREATE TABLE IF NOT EXISTS franchise_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(50) NOT NULL UNIQUE,
    operator_id INT NOT NULL,
    coop_id INT,
    vehicle_count INT DEFAULT 1,
    status ENUM('Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued','Rejected','Expired','Revoked') DEFAULT 'Submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("UPDATE franchise_applications SET status='Submitted' WHERE status IN ('Pending','Under Review')");
  $statusCol = $conn->query("SHOW COLUMNS FROM franchise_applications LIKE 'status'");
  if ($statusCol && ($row = $statusCol->fetch_assoc())) {
    $t = (string) ($row['Type'] ?? '');
    if (stripos($t, 'pa issued') === false || stripos($t, 'cpc issued') === false || stripos($t, 'revoked') === false || stripos($t, 'expired') === false || stripos($t, 'lgu-endorsed') === false || stripos($t, 'ltfrb-approved') === false || stripos($t, 'approved') === false || stripos($t, 'submitted') === false) {
      $conn->query("ALTER TABLE franchise_applications MODIFY COLUMN status ENUM('Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','PA Issued','CPC Issued','Rejected','Expired','Revoked') DEFAULT 'Submitted'");
    }
  }
  $faCols = [
    'route_id' => "INT DEFAULT NULL",
    'vehicle_type' => "ENUM('Tricycle','Jeepney','UV','Bus') DEFAULT NULL",
    'service_area_id' => "INT DEFAULT NULL",
    'route_ids' => "VARCHAR(255)",
    'fee_receipt_id' => "VARCHAR(100)",
    'representative_name' => "VARCHAR(150) DEFAULT NULL",
    'validation_notes' => "TEXT",
    'lptrp_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'coop_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'endorsed_at' => "DATETIME DEFAULT NULL",
    'endorsed_until' => "DATE DEFAULT NULL",
    'approved_at' => "DATETIME DEFAULT NULL",
    'submitted_by_portal_user_id' => "INT DEFAULT NULL",
    'submitted_by_user_id' => "INT DEFAULT NULL",
    'submitted_by_name' => "VARCHAR(150) DEFAULT NULL",
    'submitted_channel' => "VARCHAR(32) DEFAULT NULL",
    'endorsed_by_user_id' => "INT DEFAULT NULL",
    'endorsed_by_name' => "VARCHAR(150) DEFAULT NULL",
    'approved_by_user_id' => "INT DEFAULT NULL",
    'approved_by_name' => "VARCHAR(150) DEFAULT NULL",
    'approved_vehicle_count' => "INT DEFAULT NULL",
    'approved_route_ids' => "VARCHAR(255) DEFAULT NULL",
    'remarks' => "TEXT",
    'assigned_officer_id' => "INT"
  ];
  foreach ($faCols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM franchise_applications LIKE '$col'");
    if ($check && $check->num_rows == 0) {
      $conn->query("ALTER TABLE franchise_applications ADD COLUMN $col $def");
    }
  }
  $idxFaRoute = $conn->query("SHOW INDEX FROM franchise_applications WHERE Key_name='idx_franchise_route_id'");
  if (!$idxFaRoute || $idxFaRoute->num_rows == 0) {
    $conn->query("ALTER TABLE franchise_applications ADD INDEX idx_franchise_route_id (route_id)");
  }
  $idxFaArea = $conn->query("SHOW INDEX FROM franchise_applications WHERE Key_name='idx_franchise_area_id'");
  if (!$idxFaArea || $idxFaArea->num_rows == 0) {
    $conn->query("ALTER TABLE franchise_applications ADD INDEX idx_franchise_area_id (service_area_id)");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS franchises (
    franchise_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    ltfrb_ref_no VARCHAR(80) DEFAULT NULL,
    decision_order_no VARCHAR(80) DEFAULT NULL,
    authority_type ENUM('PA','CPC') DEFAULT NULL,
    issue_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    status ENUM('Active','Expired','Revoked') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_franchise_app (application_id),
    INDEX idx_ltfrb_ref (ltfrb_ref_no),
    FOREIGN KEY (application_id) REFERENCES franchise_applications(application_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $frCols = [
    'authority_type' => "ENUM('PA','CPC') DEFAULT NULL",
    'issue_date' => "DATE DEFAULT NULL",
  ];
  foreach ($frCols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM franchises LIKE '$col'");
    if ($check && $check->num_rows == 0) {
      $conn->query("ALTER TABLE franchises ADD COLUMN $col $def");
    }
  }

  $conn->query("CREATE TABLE IF NOT EXISTS franchise_vehicles (
    fv_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_id INT DEFAULT NULL,
    franchise_ref_number VARCHAR(64) DEFAULT NULL,
    route_id INT DEFAULT NULL,
    vehicle_type ENUM('Tricycle','Jeepney','UV','Bus') DEFAULT NULL,
    service_area_id INT DEFAULT NULL,
    vehicle_id INT NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_franchise_id (franchise_id),
    INDEX idx_franchise_ref (franchise_ref_number),
    INDEX idx_route_status (route_id, status),
    INDEX idx_area_status (service_area_id, status),
    INDEX idx_vehicle_status (vehicle_id, status),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (franchise_id) REFERENCES franchises(franchise_id) ON DELETE SET NULL
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS compliance_cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(50) NULL,
    entity_name VARCHAR(128) NULL,
    violation_type VARCHAR(100),
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    violation_details TEXT DEFAULT NULL,
    status ENUM('Open', 'Resolved', 'Escalated') DEFAULT 'Open',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $colCc = $conn->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='compliance_cases'");
  $ccCols = [];
  $ccFrNullable = null;
  if ($colCc) {
    while ($c = $colCc->fetch_assoc()) {
      $ccCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
      if (($c['COLUMN_NAME'] ?? '') === 'franchise_ref_number') {
        $ccFrNullable = (string)($c['IS_NULLABLE'] ?? '');
      }
    }
  }
  if (!isset($ccCols['penalty_amount'])) {
    $conn->query("ALTER TABLE compliance_cases ADD COLUMN penalty_amount DECIMAL(10,2) DEFAULT 0.00");
  }
  if (!isset($ccCols['entity_name'])) {
    $conn->query("ALTER TABLE compliance_cases ADD COLUMN entity_name VARCHAR(128) NULL");
  }
  if (!isset($ccCols['violation_details'])) {
    $conn->query("ALTER TABLE compliance_cases ADD COLUMN violation_details TEXT NULL");
  }
  if ($ccFrNullable !== null && strtoupper($ccFrNullable) !== 'YES') {
    $conn->query("ALTER TABLE compliance_cases MODIFY COLUMN franchise_ref_number VARCHAR(50) NULL");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS endorsement_records (
    endorsement_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    issued_date DATE,
    permit_number VARCHAR(50),
    endorsement_status VARCHAR(32) DEFAULT NULL,
    conditions TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $idxEr = $conn->query("SHOW INDEX FROM endorsement_records WHERE Key_name='uniq_endorsement_application'");
  if (!$idxEr || $idxEr->num_rows == 0) {
    $conn->query("DELETE er1 FROM endorsement_records er1
                  JOIN endorsement_records er2
                    ON er1.application_id = er2.application_id
                   AND er1.endorsement_id < er2.endorsement_id");
    $conn->query("ALTER TABLE endorsement_records ADD UNIQUE KEY uniq_endorsement_application (application_id)");
  }
  $colEr = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='endorsement_records'");
  $erCols = [];
  if ($colEr) {
    while ($c = $colEr->fetch_assoc()) {
      $erCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($erCols['endorsement_status'])) {
    $conn->query("ALTER TABLE endorsement_records ADD COLUMN endorsement_status VARCHAR(32) DEFAULT NULL");
  }
  if (!isset($erCols['conditions'])) {
    $conn->query("ALTER TABLE endorsement_records ADD COLUMN conditions TEXT DEFAULT NULL");
  }
  $conn->query("CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(128) UNIQUE,
    contact_info VARCHAR(128) DEFAULT NULL,
    coop_name VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $colOps = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='operators'");
  $opsCols = [];
  if ($colOps) {
    while ($c = $colOps->fetch_assoc()) {
      $opsCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  $idxOpsPk = $conn->query("SHOW INDEX FROM operators WHERE Key_name='PRIMARY'");
  $opsHasPk = $idxOpsPk && $idxOpsPk->num_rows > 0;
  if (!isset($opsCols['id'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN id INT NOT NULL AUTO_INCREMENT FIRST");
    $opsCols['id'] = true;
    $opsHasPk = false;
  }
  if (!$opsHasPk && isset($opsCols['id'])) {
    $conn->query("ALTER TABLE operators ADD PRIMARY KEY (id)");
  }
  if (!isset($opsCols['operator_type'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN operator_type ENUM('Individual','Cooperative','Corporation') DEFAULT 'Individual'");
  }
  if (!isset($opsCols['registered_name'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN registered_name VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($opsCols['name'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN name VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($opsCols['address'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address VARCHAR(255) DEFAULT NULL");
  }
  if (!isset($opsCols['address_street'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address_street VARCHAR(160) DEFAULT NULL");
  }
  if (!isset($opsCols['address_barangay'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address_barangay VARCHAR(120) DEFAULT NULL");
  }
  if (!isset($opsCols['address_city'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address_city VARCHAR(120) DEFAULT NULL");
  }
  if (!isset($opsCols['address_province'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address_province VARCHAR(120) DEFAULT NULL");
  }
  if (!isset($opsCols['address_postal_code'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN address_postal_code VARCHAR(10) DEFAULT NULL");
  }
  if (!isset($opsCols['contact_no'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN contact_no VARCHAR(64) DEFAULT NULL");
  }
  if (!isset($opsCols['email'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN email VARCHAR(128) DEFAULT NULL");
  }
  if (!isset($opsCols['status'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN status ENUM('Pending','Approved','Inactive') DEFAULT 'Approved'");
  }
  if (!isset($opsCols['verification_status'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN verification_status ENUM('Draft','Verified','Inactive') NOT NULL DEFAULT 'Draft'");
  }
  if (!isset($opsCols['workflow_status'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN workflow_status ENUM('Draft','Incomplete','Pending Validation','Active','Returned','Rejected','Inactive') NOT NULL DEFAULT 'Draft'");
    $opsCols['workflow_status'] = true;
  } else {
    $colWf = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='operators' AND COLUMN_NAME='workflow_status' LIMIT 1");
    $wfType = '';
    if ($colWf && ($rowWf = $colWf->fetch_assoc()))
      $wfType = (string) ($rowWf['COLUMN_TYPE'] ?? '');
    if ($wfType !== '' && stripos($wfType, "'Incomplete'") === false) {
      $conn->query("ALTER TABLE operators MODIFY COLUMN workflow_status ENUM('Draft','Incomplete','Pending Validation','Active','Returned','Rejected','Inactive') NOT NULL DEFAULT 'Draft'");
    }
  }
  if (!isset($opsCols['workflow_remarks'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN workflow_remarks TEXT DEFAULT NULL");
  }
  if (!isset($opsCols['updated_at'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN updated_at DATETIME DEFAULT NULL");
  }
  if (!isset($opsCols['portal_user_id'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN portal_user_id INT DEFAULT NULL");
  }
  if (!isset($opsCols['submitted_by_name'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN submitted_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!isset($opsCols['submitted_at'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN submitted_at DATETIME DEFAULT NULL");
  }
  if (!isset($opsCols['approved_by_user_id'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN approved_by_user_id INT DEFAULT NULL");
  }
  if (!isset($opsCols['approved_by_name'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN approved_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!isset($opsCols['approved_at'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN approved_at DATETIME DEFAULT NULL");
  }
  if (!isset($opsCols['risk_score'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN risk_score INT NOT NULL DEFAULT 0");
  }
  if (!isset($opsCols['risk_level'])) {
    $conn->query("ALTER TABLE operators ADD COLUMN risk_level ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low'");
  }
  $conn->query("UPDATE operators SET name=COALESCE(NULLIF(name,''), full_name) WHERE (name IS NULL OR name='') AND full_name IS NOT NULL AND full_name<>''");
  $conn->query("UPDATE operators SET registered_name=COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name) WHERE (registered_name IS NULL OR registered_name='') AND (COALESCE(NULLIF(name,''), full_name) IS NOT NULL)");
  $conn->query("UPDATE operators SET verification_status=CASE
    WHEN COALESCE(NULLIF(verification_status,''),'')<>'' THEN verification_status
    WHEN status='Approved' THEN 'Verified'
    WHEN status='Inactive' THEN 'Inactive'
    ELSE 'Draft' END");
  $conn->query("UPDATE operators SET workflow_status=CASE
    WHEN COALESCE(NULLIF(workflow_status,''),'')<>'' THEN workflow_status
    WHEN verification_status='Verified' THEN 'Active'
    WHEN verification_status='Inactive' THEN 'Inactive'
    ELSE 'Draft' END");

  $conn->query("CREATE TABLE IF NOT EXISTS operator_documents (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    doc_type ENUM('GovID','CDA','SEC','BarangayCert','Others') DEFAULT 'Others',
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    doc_status ENUM('Pending Upload','For Review','Verified','Rejected','Expired') NOT NULL DEFAULT 'For Review',
    remarks TEXT DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    INDEX (operator_id),
    FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $colOpDocs = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='operator_documents'");
  $opDocCols = [];
  if ($colOpDocs) {
    while ($c = $colOpDocs->fetch_assoc()) {
      $opDocCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($opDocCols['uploaded_at'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP");
  }
  if (!isset($opDocCols['is_verified'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
  }
  if (!isset($opDocCols['verified_by'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN verified_by INT DEFAULT NULL");
  }
  if (!isset($opDocCols['verified_at'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN verified_at DATETIME DEFAULT NULL");
  }
  if (!isset($opDocCols['doc_status'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN doc_status ENUM('Pending Upload','For Review','Verified','Rejected','Expired') NOT NULL DEFAULT 'For Review'");
  }
  @$conn->query("ALTER TABLE operator_documents MODIFY COLUMN doc_status ENUM('Pending Upload','For Review','Verified','Rejected','Expired') NOT NULL DEFAULT 'For Review'");
  $conn->query("UPDATE operator_documents SET doc_status='For Review' WHERE doc_status='Pending' OR doc_status=''");
  if (!isset($opDocCols['remarks'])) {
    $conn->query("ALTER TABLE operator_documents ADD COLUMN remarks TEXT DEFAULT NULL");
  }
  $conn->query("UPDATE operator_documents SET doc_status=CASE WHEN is_verified=1 THEN 'Verified' ELSE COALESCE(NULLIF(doc_status,''),'Pending') END");
  $conn->query("UPDATE operator_documents SET doc_type=CASE
    WHEN LOWER(COALESCE(doc_type,'')) IN ('id','govid','valid id','validid') THEN 'GovID'
    WHEN COALESCE(doc_type,'') IN ('GovID','CDA','SEC','BarangayCert','Others') THEN doc_type
    ELSE 'Others' END");

  $conn->query("CREATE TABLE IF NOT EXISTS vehicle_documents (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    doc_type ENUM('OR','CR','Insurance','Emission','Others') DEFAULT 'Others',
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    INDEX (vehicle_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $colVehDocs = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='vehicle_documents'");
  $vehDocCols = [];
  if ($colVehDocs) {
    while ($c = $colVehDocs->fetch_assoc()) {
      $vehDocCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($vehDocCols['doc_id']) && isset($vehDocCols['id'])) {
    $conn->query("ALTER TABLE vehicle_documents CHANGE COLUMN id doc_id INT NOT NULL AUTO_INCREMENT");
  }
  if (isset($vehDocCols['doc_id'])) {
    $colDocId = $conn->query("SHOW COLUMNS FROM vehicle_documents LIKE 'doc_id'");
    $docIdExtra = '';
    if ($colDocId) {
      $r = $colDocId->fetch_assoc();
      $docIdExtra = (string) ($r['Extra'] ?? '');
    }
    if (stripos($docIdExtra, 'auto_increment') === false) {
      $conn->query("ALTER TABLE vehicle_documents MODIFY COLUMN doc_id INT NOT NULL AUTO_INCREMENT");
    }
    $pk = $conn->query("SHOW KEYS FROM vehicle_documents WHERE Key_name='PRIMARY'");
    $hasPrimary = false;
    if ($pk) {
      while ($r = $pk->fetch_assoc()) {
        $hasPrimary = true;
      }
    }
    if (!$hasPrimary) {
      $conn->query("ALTER TABLE vehicle_documents ADD PRIMARY KEY (doc_id)");
    }
  }
  if (!isset($vehDocCols['doc_type']) && isset($vehDocCols['document_type'])) {
    $conn->query("ALTER TABLE vehicle_documents CHANGE COLUMN document_type doc_type VARCHAR(32) DEFAULT 'Others'");
  }
  if (!isset($vehDocCols['vehicle_id'])) {
    $conn->query("ALTER TABLE vehicle_documents ADD COLUMN vehicle_id INT NULL AFTER doc_id");
    $conn->query("ALTER TABLE vehicle_documents ADD INDEX idx_vehicle_id (vehicle_id)");
  }
  if (!isset($vehDocCols['is_verified'])) {
    $conn->query("ALTER TABLE vehicle_documents ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
  }
  if (!isset($vehDocCols['verified_by'])) {
    $conn->query("ALTER TABLE vehicle_documents ADD COLUMN verified_by INT DEFAULT NULL");
  }
  if (!isset($vehDocCols['verified_at'])) {
    $conn->query("ALTER TABLE vehicle_documents ADD COLUMN verified_at DATETIME DEFAULT NULL");
  }

  // Update the ENUM to support OR and CR separately
  $docTypeCol = $conn->query("SHOW COLUMNS FROM vehicle_documents LIKE 'doc_type'");
  if ($docTypeCol && ($row = $docTypeCol->fetch_assoc())) {
    $type = (string) ($row['Type'] ?? '');
    // Check if we need to update the ENUM
    if (stripos($type, "'OR'") === false || stripos($type, "'CR'") === false) {
      // First, update any existing ORCR values - we'll keep them as ORCR for now to avoid data loss
      // The migration will happen naturally as new documents are uploaded
      $conn->query("ALTER TABLE vehicle_documents MODIFY COLUMN doc_type ENUM('OR','CR','ORCR','Insurance','Emission','Others') DEFAULT 'Others'");
    }
  }

  // Normalize document types - keep OR and CR separate
  $conn->query("UPDATE vehicle_documents SET doc_type=CASE
    WHEN LOWER(COALESCE(doc_type,''))='or' THEN 'OR'
    WHEN LOWER(COALESCE(doc_type,''))='cr' THEN 'CR'
    WHEN LOWER(COALESCE(doc_type,'')) IN ('orcr','or/cr') THEN 'ORCR'
    WHEN LOWER(COALESCE(doc_type,''))='insurance' THEN 'Insurance'
    WHEN LOWER(COALESCE(doc_type,'')) IN ('emission','emissions') THEN 'Emission'
    WHEN LOWER(COALESCE(doc_type,'')) IN ('others','other','deed') THEN 'Others'
    WHEN COALESCE(doc_type,'') IN ('OR','CR','ORCR','Insurance','Emission','Others') THEN doc_type
    ELSE 'Others' END");
  $conn->query("UPDATE vehicle_documents vd JOIN vehicles v ON v.plate_number=vd.plate_number SET vd.vehicle_id=v.id WHERE (vd.vehicle_id IS NULL OR vd.vehicle_id=0) AND COALESCE(vd.plate_number,'')<>''");
  $conn->query("CREATE TABLE IF NOT EXISTS coops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coop_name VARCHAR(128) UNIQUE,
    address VARCHAR(255) DEFAULT NULL,
    chairperson_name VARCHAR(128) DEFAULT NULL,
    lgu_approval_number VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS violation_types (
    violation_code VARCHAR(32) PRIMARY KEY,
    description VARCHAR(255),
    fine_amount DECIMAL(10,2) DEFAULT 0,
    category VARCHAR(64) DEFAULT NULL,
    sts_equivalent_code VARCHAR(64) DEFAULT NULL,
    severity ENUM('Minor','Severe','Critical') DEFAULT NULL
  ) ENGINE=InnoDB");
  $colVt = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='violation_types'");
  $vtCols = [];
  if ($colVt) {
    while ($c = $colVt->fetch_assoc()) {
      $vtCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
    }
  }
  if (!isset($vtCols['severity'])) {
    $conn->query("ALTER TABLE violation_types ADD COLUMN severity ENUM('Minor','Severe','Critical') DEFAULT NULL");
  }
  $conn->query("UPDATE violation_types SET severity=COALESCE(severity, CASE
    WHEN violation_code IN ('RD','DRK','NDL','EXR','UUT') THEN 'Critical'
    WHEN category IN ('Safety','Registration','Licensing') THEN 'Severe'
    ELSE 'Minor' END)");
  $checkV = $conn->query("SELECT COUNT(*) AS c FROM violation_types");
  if ($checkV && ($checkV->fetch_assoc()['c'] ?? 0) == 0) {
    $conn->query("INSERT INTO violation_types(violation_code, description, fine_amount, category, sts_equivalent_code) VALUES
      ('IP', 'Illegal Parking', 1000.00, 'Parking', 'STS-IP'),
      ('NLZ', 'No Loading/Unloading Zone', 1000.00, 'Parking', 'STS-NLZ'),
      ('DTS', 'Disregarding Traffic Signs', 500.00, 'Traffic Control', 'STS-DTS'),
      ('OSB', 'Obstruction (blocking intersection, driveway, or pedestrian crossing)', 1000.00, 'Traffic Control', 'STS-OSB'),
      ('OSP', 'Overspeeding above posted speed limit', 1500.00, 'Speeding', 'STS-OSP'),
      ('RD', 'Reckless or dangerous driving', 2000.00, 'Safety', 'STS-RD'),
      ('NDL', 'Driving without a valid driver license', 3000.00, 'Licensing', 'STS-NDL'),
      ('EXL', 'Driving with expired driver license', 2000.00, 'Licensing', 'STS-EXL'),
      ('NP', 'No plate, covered or tampered plate number', 2000.00, 'Registration', 'STS-NP'),
      ('EXR', 'Expired OR/CR or vehicle registration', 1500.00, 'Registration', 'STS-EXR'),
      ('NSB', 'Failure to wear seatbelt', 1000.00, 'Safety', 'STS-NSB'),
      ('NHL', 'No helmet (motorcycle rider or backrider)', 1500.00, 'Safety', 'STS-NHL'),
      ('JEPT', 'Overloading of passengers beyond authorized capacity', 1000.00, 'Operations', 'STS-JEPT'),
      ('UUT', 'Unauthorized or out-of-line route', 2500.00, 'Operations', 'STS-UUT'),
      ('COD', 'Number coding/color-coding scheme violation', 1000.00, 'Traffic Control', 'STS-COD'),
      ('STP', 'Stopping or loading in prohibited zone/intersection', 500.00, 'Traffic Control', 'STS-STP')");
  }
  $violationSeeds = [
    ['IP', 'Illegal Parking', 1000.00, 'Parking', 'STS-IP'],
    ['NLZ', 'No Loading/Unloading Zone', 1000.00, 'Parking', 'STS-NLZ'],
    ['DTS', 'Disregarding Traffic Signs', 500.00, 'Traffic Control', 'STS-DTS'],
    ['OSB', 'Obstruction (blocking intersection, driveway, or pedestrian crossing)', 1000.00, 'Traffic Control', 'STS-OSB'],
    ['OSP', 'Overspeeding above posted speed limit', 1500.00, 'Speeding', 'STS-OSP'],
    ['RD', 'Reckless or dangerous driving', 2000.00, 'Safety', 'STS-RD'],
    ['NDL', 'Driving without a valid driver license', 3000.00, 'Licensing', 'STS-NDL'],
    ['EXL', 'Driving with expired driver license', 2000.00, 'Licensing', 'STS-EXL'],
    ['NP', 'No plate, covered or tampered plate number', 2000.00, 'Registration', 'STS-NP'],
    ['EXR', 'Expired OR/CR or vehicle registration', 1500.00, 'Registration', 'STS-EXR'],
    ['NSB', 'Failure to wear seatbelt', 1000.00, 'Safety', 'STS-NSB'],
    ['NHL', 'No helmet (motorcycle rider or backrider)', 1500.00, 'Safety', 'STS-NHL'],
    ['JEPT', 'Overloading of passengers beyond authorized capacity', 1000.00, 'Operations', 'STS-JEPT'],
    ['UUT', 'Unauthorized or out-of-line route', 2500.00, 'Operations', 'STS-UUT'],
    ['COD', 'Number coding/color-coding scheme violation', 1000.00, 'Traffic Control', 'STS-COD'],
    ['STP', 'Stopping or loading in prohibited zone/intersection', 500.00, 'Traffic Control', 'STS-STP'],
    ['BRL', 'Beating the red light', 1500.00, 'Traffic Control', 'STS-BRL'],
    ['CFW', 'Counterflow / Wrong-way driving', 2000.00, 'Safety', 'STS-CFW'],
    ['PHN', 'Using mobile phone or gadget while driving', 1000.00, 'Safety', 'STS-PHN'],
    ['DRK', 'Driving under the influence of alcohol or drugs (apprehension)', 5000.00, 'Safety', 'STS-DRK'],
    ['UTN', 'Unauthorized U-turn or turning in prohibited area', 1000.00, 'Traffic Control', 'STS-UTN'],
    ['NLG', 'No or defective lights (headlight/taillight/brakelight)', 800.00, 'Vehicle Condition', 'STS-NLG'],
    ['NST', 'No side mirror or defective mirror', 800.00, 'Vehicle Condition', 'STS-NST'],
    ['LND', 'Failure to keep to designated lane', 800.00, 'Traffic Control', 'STS-LND'],
    ['PDP', 'Picking up or dropping off passengers in prohibited areas', 1000.00, 'Operations', 'STS-PDP'],
    ['HNK', 'Unnecessary use of horn within restricted zones', 500.00, 'Traffic Control', 'STS-HNK'],
    ['SMB', 'Smoke belching / excessive emission', 2000.00, 'Vehicle Condition', 'STS-SMB'],
    ['DEF', 'Defective parts/accessories (unsafe vehicle condition)', 1000.00, 'Vehicle Condition', 'STS-DEF'],
    ['BLT', 'Bald tires / unsafe tires', 1000.00, 'Vehicle Condition', 'STS-BLT'],
    ['EWD', 'No early warning device (EWD)', 1000.00, 'Safety', 'STS-EWD'],
    ['NIN', 'No valid compulsory third-party liability insurance', 0.00, 'Registration', 'STS-NIN'],
    ['UNR', 'Unregistered motor vehicle / no registration record', 0.00, 'Registration', 'STS-UNR'],
    ['COL', 'Colorum / operating without franchise', 0.00, 'PUV Operations', 'STS-COL'],
    ['NFR', 'No franchise / expired franchise', 0.00, 'PUV Operations', 'STS-NFR'],
    ['NFM', 'No fare matrix / fare information not posted', 0.00, 'PUV Operations', 'STS-NFM'],
    ['OCH', 'Overcharging / overpricing of fare', 0.00, 'PUV Operations', 'STS-OCH'],
    ['RCP', 'Refusal to convey passenger', 0.00, 'PUV Operations', 'STS-RCP'],
    ['TRI', 'Trip cutting / failure to complete trip', 0.00, 'PUV Operations', 'STS-TRI'],
    ['NID', 'No driver ID / operator ID displayed', 0.00, 'PUV Operations', 'STS-NID'],
    ['NSG', 'No route signboard / improper signboard display', 0.00, 'PUV Operations', 'STS-NSG'],
    ['NDB', 'No body number / unit number not displayed', 0.00, 'PUV Operations', 'STS-NDB'],
    ['DUP', 'Dilapidated unit / unroadworthy unit', 0.00, 'Vehicle Condition', 'STS-DUP'],
    ['OBS', 'Obstruction (double parking, blocking driveway/sidewalk)', 1000.00, 'Traffic Control', 'STS-OBS'],
    ['ILP', 'Illegal parking in restricted area', 1000.00, 'Parking', 'STS-ILP'],
    ['NLB', 'No loading/unloading bay compliance', 0.00, 'Operations', 'STS-NLB'],
    ['NHS', 'No headlights at night / improper use of lights', 800.00, 'Vehicle Condition', 'STS-NHS'],
    ['SPD', 'Speed contest / racing', 0.00, 'Safety', 'STS-SPD'],
    ['SWR', 'Swerving / sudden lane changes endangering motorists', 0.00, 'Safety', 'STS-SWR'],
    ['DOP', 'Disobeying traffic officer', 0.00, 'Traffic Control', 'STS-DOP'],
    ['NPA', 'No passenger assistance / improper passenger handling', 0.00, 'PUV Operations', 'STS-NPA'],
    ['NCS', 'No conductors fare ticketing / improper ticketing (if applicable)', 0.00, 'PUV Operations', 'STS-NCS'],
    ['NPU', 'No proper uniform / improper attire (if required by LGU/LTFRB)', 0.00, 'PUV Operations', 'STS-NPU'],
    ['NPT', 'No permit to operate on assigned route / lack of authority', 0.00, 'PUV Operations', 'STS-NPT'],
    ['LOA', 'Loading/unloading in prohibited areas', 500.00, 'Traffic Control', 'STS-LOA'],
    ['OPR', 'Operating on prohibited routes/streets', 0.00, 'Operations', 'STS-OPR'],
    ['BKP', 'Blocking pedestrian lane/crossing', 1000.00, 'Traffic Control', 'STS-BKP'],
    ['NRC', 'Non-compliance with route capacity/dispatch rules', 0.00, 'Operations', 'STS-NRC'],
  ];
  foreach ($violationSeeds as $v) {
    $code = $conn->real_escape_string($v[0]);
    $check = $conn->query("SELECT violation_code FROM violation_types WHERE violation_code = '$code' LIMIT 1");
    if ($check && $check->num_rows == 0) {
      $desc = $conn->real_escape_string($v[1]);
      $fine = (float) $v[2];
      $cat = $conn->real_escape_string($v[3]);
      $sts = $conn->real_escape_string($v[4]);
      $conn->query("INSERT INTO violation_types(violation_code, description, fine_amount, category, sts_equivalent_code) VALUES ('$code', '$desc', $fine, '$cat', '$sts')");
    }
  }
  $conn->query("CREATE TABLE IF NOT EXISTS officers (
    officer_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128),
    role VARCHAR(64),
    badge_no VARCHAR(64) UNIQUE,
    station_id VARCHAR(64) DEFAULT NULL,
    active_status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $checkO = $conn->query("SELECT COUNT(*) AS c FROM officers");
  if ($checkO && ($checkO->fetch_assoc()['c'] ?? 0) == 0) {
    $conn->query("INSERT INTO officers(name, role, badge_no, station_id, active_status) VALUES
      ('Officer Dela Cruz','Enforcer','MMDA-001','Station A',1),
      ('Officer Santos','Enforcer','MMDA-002','Station B',1),
      ('Officer Reyes','Supervisor','MMDA-010','HQ',1),
      ('Officer Garcia','Enforcer','MMDA-003','Station C',1)");
  }
  $conn->query("CREATE TABLE IF NOT EXISTS tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(32) UNIQUE,
    date_issued DATETIME DEFAULT CURRENT_TIMESTAMP,
    violation_code VARCHAR(32),
    vehicle_plate VARCHAR(32),
    operator_id INT DEFAULT NULL,
    franchise_id VARCHAR(64) DEFAULT NULL,
    coop_id INT DEFAULT NULL,
    driver_name VARCHAR(128) DEFAULT NULL,
    issued_by VARCHAR(128) DEFAULT NULL,
    issued_by_badge VARCHAR(64) DEFAULT NULL,
    officer_id INT DEFAULT NULL,
    status ENUM('Unpaid','Pending','Validated','Settled','Escalated') DEFAULT 'Unpaid',
    fine_amount DECIMAL(10,2) DEFAULT 0,
    due_date DATE DEFAULT NULL,
    payment_ref VARCHAR(64) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    evidence_path VARCHAR(255) DEFAULT NULL,
    INDEX (vehicle_plate),
    FOREIGN KEY (violation_code) REFERENCES violation_types(violation_code)
  ) ENGINE=InnoDB");
  $colTicketId = $conn->query("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='tickets' AND COLUMN_NAME='ticket_id' LIMIT 1");
  if ($colTicketId && ($r = $colTicketId->fetch_assoc())) {
    $extra = strtolower((string) ($r['EXTRA'] ?? ''));
    if (strpos($extra, 'auto_increment') === false) {
      $conn->query("ALTER TABLE tickets MODIFY COLUMN ticket_id INT NOT NULL AUTO_INCREMENT");
    }
  }
  $idxTicketId = $conn->query("SHOW INDEX FROM tickets WHERE Column_name='ticket_id'");
  if (!$idxTicketId || $idxTicketId->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD INDEX idx_ticket_id (ticket_id)");
  }
  // Ensure new audit columns exist (safe migrations)
  $colCheck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='tickets'");
  $haveBadge = false;
  $haveOfficer = false;
  $haveOperatorId = false;
  $haveEvidencePath = false;
  if ($colCheck) {
    while ($c = $colCheck->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'issued_by_badge')
        $haveBadge = true;
      if (($c['COLUMN_NAME'] ?? '') === 'officer_id')
        $haveOfficer = true;
      if (($c['COLUMN_NAME'] ?? '') === 'operator_id')
        $haveOperatorId = true;
      if (($c['COLUMN_NAME'] ?? '') === 'evidence_path')
        $haveEvidencePath = true;
    }
  }
  if (!$haveBadge) {
    $conn->query("ALTER TABLE tickets ADD COLUMN issued_by_badge VARCHAR(64) DEFAULT NULL");
  }
  if (!$haveOfficer) {
    $conn->query("ALTER TABLE tickets ADD COLUMN officer_id INT DEFAULT NULL");
    // Add FK if table exists
    $conn->query("ALTER TABLE tickets ADD INDEX idx_officer_id (officer_id)");
    $conn->query("ALTER TABLE tickets ADD CONSTRAINT fk_tickets_officer FOREIGN KEY (officer_id) REFERENCES officers(officer_id)");
  }
  if (!$haveOperatorId) {
    $conn->query("ALTER TABLE tickets ADD COLUMN operator_id INT DEFAULT NULL");
  }
  if (!$haveEvidencePath) {
    $conn->query("ALTER TABLE tickets ADD COLUMN evidence_path VARCHAR(255) DEFAULT NULL");
  }
  $statusColTickets = $conn->query("SHOW COLUMNS FROM tickets LIKE 'status'");
  if ($statusColTickets && ($row = $statusColTickets->fetch_assoc())) {
    $t = (string) ($row['Type'] ?? '');
    if (stripos($t, 'unpaid') === false) {
      $conn->query("ALTER TABLE tickets MODIFY COLUMN status ENUM('Unpaid','Pending','Validated','Settled','Escalated') DEFAULT 'Unpaid'");
      $conn->query("UPDATE tickets SET status='Unpaid' WHERE status IN ('Pending','Validated','Escalated')");
    }
  }
  $conn->query("CREATE TABLE IF NOT EXISTS evidence (
    evidence_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    file_path VARCHAR(255),
    file_type VARCHAR(32),
    uploaded_by VARCHAR(64) DEFAULT 'officer',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS payment_records (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    date_paid DATETIME DEFAULT CURRENT_TIMESTAMP,
    receipt_ref VARCHAR(64),
    verified_by_treasury TINYINT(1) DEFAULT 0,
    INDEX (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS ticket_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    or_no VARCHAR(64) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("INSERT INTO ticket_payments (ticket_id, or_no, amount_paid, paid_at)
    SELECT pr.ticket_id, COALESCE(NULLIF(pr.receipt_ref,''), CONCAT('OR-', pr.payment_id)), pr.amount_paid, pr.date_paid
    FROM payment_records pr
    LEFT JOIN ticket_payments tp ON tp.ticket_id=pr.ticket_id AND tp.amount_paid=pr.amount_paid AND tp.paid_at=pr.date_paid
    WHERE tp.payment_id IS NULL");

  $conn->query("CREATE TABLE IF NOT EXISTS violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL,
    violation_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Unpaid',
    violation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    vehicle_id INT DEFAULT NULL,
    operator_id INT DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    evidence_path VARCHAR(255) DEFAULT NULL,
    workflow_status VARCHAR(20) DEFAULT 'Pending',
    remarks TEXT DEFAULT NULL,
    recorded_by_user_id INT DEFAULT NULL,
    recorded_by_name VARCHAR(150) DEFAULT NULL,
    recorded_at DATETIME DEFAULT NULL,
    INDEX (plate_number),
    INDEX (operator_id),
    INDEX (workflow_status)
  ) ENGINE=InnoDB");
  $colViol = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='violations'");
  $haveWorkflowStatus = false;
  $haveLocation = false;
  $haveEvidence = false;
  $haveRemarks = false;
  $haveRecordedBy = false;
  $haveRecordedAt = false;
  $haveOperatorId = false;
  $haveVehicleId = false;
  if ($colViol) {
    while ($c = $colViol->fetch_assoc()) {
      $cn = $c['COLUMN_NAME'] ?? '';
      if ($cn === 'workflow_status') $haveWorkflowStatus = true;
      if ($cn === 'location') $haveLocation = true;
      if ($cn === 'evidence_path') $haveEvidence = true;
      if ($cn === 'remarks') $haveRemarks = true;
      if ($cn === 'recorded_by_user_id' || $cn === 'recorded_by_name') $haveRecordedBy = true;
      if ($cn === 'recorded_at') $haveRecordedAt = true;
      if ($cn === 'operator_id') $haveOperatorId = true;
      if ($cn === 'vehicle_id') $haveVehicleId = true;
    }
  }
  if (!$haveWorkflowStatus) $conn->query("ALTER TABLE violations ADD COLUMN workflow_status VARCHAR(20) DEFAULT 'Pending'");
  if (!$haveLocation) $conn->query("ALTER TABLE violations ADD COLUMN location VARCHAR(255) DEFAULT NULL");
  if (!$haveEvidence) $conn->query("ALTER TABLE violations ADD COLUMN evidence_path VARCHAR(255) DEFAULT NULL");
  if (!$haveRemarks) $conn->query("ALTER TABLE violations ADD COLUMN remarks TEXT DEFAULT NULL");
  if (!$haveOperatorId) $conn->query("ALTER TABLE violations ADD COLUMN operator_id INT DEFAULT NULL");
  if (!$haveVehicleId) $conn->query("ALTER TABLE violations ADD COLUMN vehicle_id INT DEFAULT NULL");
  if (!$haveRecordedBy) {
    $conn->query("ALTER TABLE violations ADD COLUMN recorded_by_user_id INT DEFAULT NULL");
    $conn->query("ALTER TABLE violations ADD COLUMN recorded_by_name VARCHAR(150) DEFAULT NULL");
  }
  if (!$haveRecordedAt) $conn->query("ALTER TABLE violations ADD COLUMN recorded_at DATETIME DEFAULT NULL");

  $conn->query("CREATE TABLE IF NOT EXISTS sts_tickets (
    sts_ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    sts_ticket_no VARCHAR(64) NOT NULL,
    issued_by VARCHAR(128) DEFAULT NULL,
    date_issued DATE DEFAULT NULL,
    fine_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('Pending Payment','Paid','Closed') DEFAULT 'Pending Payment',
    verification_notes TEXT DEFAULT NULL,
    linked_violation_id INT DEFAULT NULL,
    ticket_scan_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sts_ticket_no (sts_ticket_no),
    INDEX (status),
    INDEX (linked_violation_id)
  ) ENGINE=InnoDB");
  $colSts = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='sts_tickets'");
  $haveStsScan = false;
  if ($colSts) {
    while ($c = $colSts->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'ticket_scan_path') $haveStsScan = true;
    }
  }
  if (!$haveStsScan) $conn->query("ALTER TABLE sts_tickets ADD COLUMN ticket_scan_path VARCHAR(255) DEFAULT NULL");
  $colPay = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='payment_records'");
  $havePayChannel = false;
  $haveExternalPaymentId = false;
  $haveExportedToTreasury = false;
  $haveExportedAt = false;
  if ($colPay) {
    while ($c = $colPay->fetch_assoc()) {
      $cn = $c['COLUMN_NAME'] ?? '';
      if ($cn === 'payment_channel')
        $havePayChannel = true;
      if ($cn === 'external_payment_id')
        $haveExternalPaymentId = true;
      if ($cn === 'exported_to_treasury')
        $haveExportedToTreasury = true;
      if ($cn === 'exported_at')
        $haveExportedAt = true;
    }
  }
  if (!$havePayChannel) {
    $conn->query("ALTER TABLE payment_records ADD COLUMN payment_channel VARCHAR(64) DEFAULT NULL");
  }
  if (!$haveExternalPaymentId) {
    $conn->query("ALTER TABLE payment_records ADD COLUMN external_payment_id VARCHAR(128) DEFAULT NULL");
  }
  if (!$haveExportedToTreasury) {
    $conn->query("ALTER TABLE payment_records ADD COLUMN exported_to_treasury TINYINT(1) DEFAULT 0");
  }
  if (!$haveExportedAt) {
    $conn->query("ALTER TABLE payment_records ADD COLUMN exported_at DATETIME DEFAULT NULL");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS treasury_payment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(64) UNIQUE,
    kind VARCHAR(32) NOT NULL,
    transaction_id VARCHAR(64) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    purpose VARCHAR(255) DEFAULT '',
    status VARCHAR(32) DEFAULT 'pending',
    receipt_ref VARCHAR(64) DEFAULT NULL,
    payment_channel VARCHAR(64) DEFAULT NULL,
    external_payment_id VARCHAR(128) DEFAULT NULL,
    external_url VARCHAR(255) DEFAULT NULL,
    callback_payload MEDIUMTEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (kind),
    INDEX (transaction_id),
    INDEX (status)
  ) ENGINE=InnoDB");

  $tblParking = $conn->query("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='parking_transactions'");
  $hasParkingTx = $tblParking && ((int) ($tblParking->fetch_assoc()['c'] ?? 0) > 0);
  if ($hasParkingTx) {
    $colPark = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='parking_transactions'");
    $parkCols = [];
    if ($colPark) {
      while ($c = $colPark->fetch_assoc()) {
        $parkCols[(string) ($c['COLUMN_NAME'] ?? '')] = true;
      }
    }
    if (!isset($parkCols['receipt_ref'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN receipt_ref VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($parkCols['payment_channel'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN payment_channel VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($parkCols['external_payment_id'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN external_payment_id VARCHAR(128) DEFAULT NULL");
    }
    if (!isset($parkCols['paid_at'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN paid_at DATETIME DEFAULT NULL");
    }
    if (!isset($parkCols['duration_hours'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN duration_hours INT DEFAULT NULL");
    }
    if (!isset($parkCols['payment_method'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN payment_method VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($parkCols['reference_no'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN reference_no VARCHAR(64) DEFAULT NULL");
    }
    if (!isset($parkCols['exported_to_treasury'])) {
      $conn->query("ALTER TABLE parking_transactions ADD COLUMN exported_to_treasury TINYINT(1) DEFAULT 0");
    }
  }
  $conn->query("CREATE TABLE IF NOT EXISTS ticket_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_module VARCHAR(32) NOT NULL,
    filter_period VARCHAR(16) DEFAULT '',
    filter_status VARCHAR(16) DEFAULT '',
    filter_officer_id INT DEFAULT NULL,
    filter_q VARCHAR(128) DEFAULT '',
    ticket_count INT NOT NULL DEFAULT 0,
    last_ticket_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS compliance_summary (
    vehicle_plate VARCHAR(32) PRIMARY KEY,
    franchise_id VARCHAR(64) DEFAULT NULL,
    violation_count INT DEFAULT 0,
    last_violation_date DATE DEFAULT NULL,
    compliance_status VARCHAR(32) DEFAULT 'Normal',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS inspection_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32) NOT NULL,
    vehicle_id INT DEFAULT NULL,
    scheduled_at DATETIME NOT NULL,
    schedule_date DATETIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    inspection_type VARCHAR(32) DEFAULT 'Annual',
    requested_by VARCHAR(128) DEFAULT NULL,
    contact_person VARCHAR(128) DEFAULT NULL,
    contact_number VARCHAR(32) DEFAULT NULL,
    inspector_id INT DEFAULT NULL,
    inspector_label VARCHAR(128) DEFAULT NULL,
    status ENUM('Scheduled','Completed','Cancelled','Rescheduled','Pending Verification','Pending Assignment') DEFAULT 'Pending Verification',
    cr_verified TINYINT(1) DEFAULT 0,
    or_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number),
    INDEX (vehicle_id),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (inspector_id) REFERENCES officers(officer_id)
  ) ENGINE=InnoDB");

  $colSchId = $conn->query("SHOW COLUMNS FROM inspection_schedules LIKE 'schedule_id'");
  $schIdRow = $colSchId ? $colSchId->fetch_assoc() : null;
  $schExtra = strtolower((string)($schIdRow['Extra'] ?? ''));
  if (!$schIdRow) {
    @$conn->query("ALTER TABLE inspection_schedules ADD COLUMN schedule_id INT NULL FIRST");
    @$conn->query("SET @tmm_sid := 0");
    @$conn->query("UPDATE inspection_schedules SET schedule_id = (@tmm_sid := @tmm_sid + 1) WHERE schedule_id IS NULL");
  }
  if ($schExtra === '' || strpos($schExtra, 'auto_increment') === false) {
    $idxSchId = $conn->query("SHOW INDEX FROM inspection_schedules WHERE Column_name='schedule_id'");
    if (!$idxSchId || $idxSchId->num_rows == 0) {
      @$conn->query("ALTER TABLE inspection_schedules ADD UNIQUE KEY uniq_schedule_id (schedule_id)");
    }
    @$conn->query("ALTER TABLE inspection_schedules MODIFY COLUMN schedule_id INT NOT NULL AUTO_INCREMENT");
  }
  $colCheckIns = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='inspection_schedules'");
  $haveInspectionType = false;
  $haveRequestedBy = false;
  $haveContactPerson = false;
  $haveContactNumber = false;
  $haveInspectorLabel = false;
  $haveVehicleId = false;
  $haveScheduleDate = false;
  if ($colCheckIns) {
    while ($c = $colCheckIns->fetch_assoc()) {
      $cn = $c['COLUMN_NAME'] ?? '';
      if ($cn === 'inspection_type')
        $haveInspectionType = true;
      if ($cn === 'requested_by')
        $haveRequestedBy = true;
      if ($cn === 'contact_person')
        $haveContactPerson = true;
      if ($cn === 'contact_number')
        $haveContactNumber = true;
      if ($cn === 'inspector_label')
        $haveInspectorLabel = true;
      if ($cn === 'vehicle_id')
        $haveVehicleId = true;
      if ($cn === 'schedule_date')
        $haveScheduleDate = true;
    }
  }
  if (!$haveInspectionType) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN inspection_type VARCHAR(32) DEFAULT 'Annual'");
  }
  if (!$haveRequestedBy) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN requested_by VARCHAR(128) DEFAULT NULL");
  }
  if (!$haveContactPerson) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN contact_person VARCHAR(128) DEFAULT NULL");
  }
  if (!$haveContactNumber) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN contact_number VARCHAR(32) DEFAULT NULL");
  }
  if (!$haveInspectorLabel) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN inspector_label VARCHAR(128) DEFAULT NULL AFTER inspector_id");
  }
  if (!$haveVehicleId) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN vehicle_id INT DEFAULT NULL AFTER plate_number");
  }
  if (!$haveScheduleDate) {
    $conn->query("ALTER TABLE inspection_schedules ADD COLUMN schedule_date DATETIME DEFAULT NULL AFTER scheduled_at");
  }
  $conn->query("UPDATE inspection_schedules s JOIN vehicles v ON v.plate_number=s.plate_number SET s.vehicle_id=v.id WHERE s.vehicle_id IS NULL");
  $conn->query("UPDATE inspection_schedules SET schedule_date=scheduled_at WHERE schedule_date IS NULL");

  $conn->query("CREATE TABLE IF NOT EXISTS vehicle_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    orcr_no VARCHAR(64) NOT NULL,
    orcr_date DATE NOT NULL,
    registration_status ENUM('Pending','Recorded','Registered','Expired') DEFAULT 'Registered',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (vehicle_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $colReg = $conn->query("SHOW COLUMNS FROM vehicle_registrations LIKE 'registration_status'");
  if ($colReg && ($row = $colReg->fetch_assoc())) {
    $t = (string) ($row['Type'] ?? '');
    if (stripos($t, 'recorded') === false) {
      $conn->query("ALTER TABLE vehicle_registrations MODIFY COLUMN registration_status ENUM('Pending','Recorded','Registered','Expired') DEFAULT 'Registered'");
    }
  }

  $conn->query("CREATE TABLE IF NOT EXISTS inspections (
    inspection_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    schedule_id INT NOT NULL,
    result ENUM('Passed','Failed') NOT NULL,
    remarks TEXT,
    inspected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (vehicle_id),
    INDEX (schedule_id),
    UNIQUE KEY uniq_inspections_schedule (schedule_id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $idxIns = $conn->query("SHOW INDEX FROM inspections WHERE Key_name='uniq_inspections_schedule'");
  if (!$idxIns || $idxIns->num_rows == 0) {
    $conn->query("ALTER TABLE inspections ADD UNIQUE KEY uniq_inspections_schedule (schedule_id)");
  }
  $conn->query("CREATE TABLE IF NOT EXISTS inspection_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    overall_status ENUM('Passed','Failed','Pending','For Reinspection') DEFAULT 'Pending',
    remarks VARCHAR(255) DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS inspection_checklist_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    item_code VARCHAR(32),
    item_label VARCHAR(128),
    status ENUM('Pass','Fail','NA') DEFAULT 'NA',
    INDEX (result_id),
    FOREIGN KEY (result_id) REFERENCES inspection_results(result_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $resCols = $conn->query("SHOW COLUMNS FROM inspection_results");
  if ($resCols) {
    $hasOverall = false;
    $hasRemarks = false;
    $hasSubmittedAt = false;
    while ($r = $resCols->fetch_assoc()) {
      $f = strtolower((string)$r['Field']);
      if ($f === 'overall_status') $hasOverall = true;
      if ($f === 'remarks') $hasRemarks = true;
      if ($f === 'submitted_at') $hasSubmittedAt = true;
    }
    if (!$hasOverall) $conn->query("ALTER TABLE inspection_results ADD COLUMN overall_status ENUM('Passed','Failed','Pending','For Reinspection') DEFAULT 'Pending'");
    if (!$hasRemarks) $conn->query("ALTER TABLE inspection_results ADD COLUMN remarks VARCHAR(255) DEFAULT NULL");
    if (!$hasSubmittedAt) $conn->query("ALTER TABLE inspection_results ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
  }

  $itemCols = $conn->query("SHOW COLUMNS FROM inspection_checklist_items");
  if ($itemCols) {
    $hasCode = false;
    $hasLabel = false;
    $hasStatus = false;
    while ($r = $itemCols->fetch_assoc()) {
      $f = strtolower((string)$r['Field']);
      if ($f === 'item_code') $hasCode = true;
      if ($f === 'item_label') $hasLabel = true;
      if ($f === 'status') $hasStatus = true;
    }
    if (!$hasCode) $conn->query("ALTER TABLE inspection_checklist_items ADD COLUMN item_code VARCHAR(32)");
    if (!$hasLabel) $conn->query("ALTER TABLE inspection_checklist_items ADD COLUMN item_label VARCHAR(128)");
    if (!$hasStatus) $conn->query("ALTER TABLE inspection_checklist_items ADD COLUMN status ENUM('Pass','Fail','NA') DEFAULT 'NA'");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS inspection_certificates (
    cert_id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_number VARCHAR(32) UNIQUE,
    schedule_id INT NOT NULL,
    issued_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT DEFAULT NULL,
    qr_ref VARCHAR(64) DEFAULT NULL,
    status ENUM('Issued','Revoked') DEFAULT 'Issued',
    INDEX (schedule_id),
    FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES officers(officer_id)
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS inspection_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    file_path VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (result_id),
    FOREIGN KEY (result_id) REFERENCES inspection_results(result_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS puv_demand_observations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_type ENUM('terminal','route','parking_area') NOT NULL,
    area_ref VARCHAR(128) NOT NULL,
    observed_at DATETIME NOT NULL,
    demand_count INT NOT NULL DEFAULT 0,
    source VARCHAR(32) NOT NULL DEFAULT 'system',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_area_hour (area_type, area_ref, observed_at),
    INDEX idx_area_time (area_type, area_ref, observed_at)
  ) ENGINE=InnoDB");
  $conn->query("ALTER TABLE puv_demand_observations MODIFY area_type ENUM('terminal','route','parking_area') NOT NULL");

  $conn->query("CREATE TABLE IF NOT EXISTS audit_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_user_id INT NOT NULL DEFAULT 0,
    actor_email VARCHAR(128) DEFAULT '',
    actor_role VARCHAR(64) DEFAULT '',
    action VARCHAR(64) NOT NULL,
    entity_type VARCHAR(64) DEFAULT '',
    entity_key VARCHAR(128) DEFAULT '',
    ip_address VARCHAR(64) DEFAULT '',
    user_agent VARCHAR(255) DEFAULT '',
    meta_json MEDIUMTEXT DEFAULT NULL,
    INDEX idx_event_time (event_time),
    INDEX idx_actor (actor_user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_key)
  ) ENGINE=InnoDB");

  return $conn;
}
