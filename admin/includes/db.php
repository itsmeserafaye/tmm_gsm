<?php
require_once __DIR__ . '/../../includes/env.php';
tmm_load_env(__DIR__ . '/../../.env');

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
    status VARCHAR(32) DEFAULT 'Active',
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
  $conn->query("UPDATE vehicles SET record_status=CASE
    WHEN record_status IN ('Encoded','Linked','Archived') THEN record_status
    WHEN operator_id IS NOT NULL AND operator_id>0 THEN 'Linked'
    ELSE 'Encoded' END");
  $conn->query("UPDATE vehicles SET current_operator_id=operator_id WHERE (current_operator_id IS NULL OR current_operator_id=0) AND operator_id IS NOT NULL AND operator_id>0");
  $conn->query("UPDATE vehicles SET status='Active' WHERE status IN ('Linked','Unlinked') OR status IS NULL OR status=''");
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
  if ($colDocs) {
    while ($c = $colDocs->fetch_assoc()) {
      $colName = $c['COLUMN_NAME'] ?? '';
      if ($colName === 'verified') {
        $haveVerifiedCol = true;
      }
      if ($colName === 'application_id') {
        $haveAppIdCol = true;
      }
    }
  }
  if (!$haveVerifiedCol) {
    $conn->query("ALTER TABLE documents ADD COLUMN verified TINYINT(1) DEFAULT 0");
  }
  if (!$haveAppIdCol) {
    $conn->query("ALTER TABLE documents ADD COLUMN application_id INT NULL");
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

  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'MCU/Monumento Terminal','Monumento','Caloocan City','Rizal Ave Ext / EDSA (near LRT-1 Monumento)',800,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='MCU/Monumento Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Bagong Barrio Terminal','Bagong Barrio','Caloocan City','EDSA Bagong Barrio',500,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Barrio Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Sangandaan Terminal','Sangandaan','Caloocan City','Samson Rd / Sangandaan',250,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Sangandaan Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Grace Park Terminal','Grace Park','Caloocan City','10th Ave / 5th Ave area',300,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Grace Park Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Camarin Terminal','Camarin','Caloocan City','Camarin Rd / Zabarte Rd area',400,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Camarin Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Deparo Terminal','Deparo','Caloocan City','Deparo Rd area',350,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Deparo Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Tala Terminal','Tala','Caloocan City','Tala area / Dr. Jose N. Rodriguez Memorial Hospital vicinity',250,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Tala Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Bagong Silang Terminal','Bagong Silang','Caloocan City','Zabarte Rd / Phase terminals',600,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Silang Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Novaliches Bayan Terminal','Novaliches','Caloocan City','Quirino Highway / Novaliches Bayan',500,'Terminal'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Novaliches Bayan Terminal')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'MCU/Monumento Parking','Monumento','Caloocan City','Rizal Ave Ext / EDSA (near LRT-1 Monumento)',200,'Parking'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='MCU/Monumento Parking')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Bagong Silang Parking','Bagong Silang','Caloocan City','Zabarte Rd / Bagong Silang',150,'Parking'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Bagong Silang Parking')");
  $conn->query("INSERT INTO terminals (name, location, city, address, capacity, type)
                SELECT 'Grace Park Parking','Grace Park','Caloocan City','10th Ave / 5th Ave area',120,'Parking'
                WHERE NOT EXISTS (SELECT 1 FROM terminals WHERE name='Grace Park Parking')");

  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-02' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-02' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-03' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-06' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-01' FROM terminals t WHERE t.name='MCU/Monumento Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-01' FROM terminals t WHERE t.name='Bagong Barrio Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-02' FROM terminals t WHERE t.name='Sangandaan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-06' FROM terminals t WHERE t.name='Grace Park Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-08' FROM terminals t WHERE t.name='Grace Park Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-03' FROM terminals t WHERE t.name='Grace Park Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-04' FROM terminals t WHERE t.name='Camarin Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-07' FROM terminals t WHERE t.name='Camarin Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-04' FROM terminals t WHERE t.name='Camarin Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-09' FROM terminals t WHERE t.name='Camarin Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-03' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-05' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-01' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-02' FROM terminals t WHERE t.name='Deparo Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-03' FROM terminals t WHERE t.name='Tala Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-05' FROM terminals t WHERE t.name='Tala Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-02' FROM terminals t WHERE t.name='Tala Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TR-04' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-06' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-07' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-08' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-03' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-04' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-02' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'BUS-03' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'TRI-01' FROM terminals t WHERE t.name='Bagong Silang Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'JEEP-05' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-06' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-07' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-08' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");
  $conn->query("INSERT IGNORE INTO terminal_routes (terminal_id, route_id) SELECT t.id, 'UV-09' FROM terminals t WHERE t.name='Novaliches Bayan Terminal'");

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
  if (!isset($routeCols['authorized_units'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN authorized_units INT DEFAULT NULL");
  }
  if (!isset($routeCols['approved_by'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN approved_by VARCHAR(128) DEFAULT NULL");
  }
  if (!isset($routeCols['approved_date'])) {
    $conn->query("ALTER TABLE routes ADD COLUMN approved_date DATE DEFAULT NULL");
  }
  $conn->query("UPDATE routes SET route_code=route_id WHERE (route_code IS NULL OR route_code='') AND COALESCE(route_id,'')<>''");
  $conn->query("UPDATE routes SET route_id=route_code WHERE (route_id IS NULL OR route_id='') AND COALESCE(route_code,'')<>''");
  $conn->query("UPDATE routes SET status=CASE WHEN status IN ('Active','Inactive') THEN status ELSE 'Active' END");
  $check = $conn->query("SELECT COUNT(*) AS c FROM routes");
  if ($check && ($check->fetch_assoc()['c'] ?? 0) == 0) {
    $conn->query("INSERT INTO routes(route_id, route_code, route_name, vehicle_type, origin, destination, via, structure, authorized_units, max_vehicle_limit, status) VALUES
      ('TR-01','TR-01','Monumento Loop','Jeepney','Monumento','Monumento','EDSA • Rizal Ave Ext • Samson Rd • 10th Ave/5th Ave (Caloocan)','Loop',60,60,'Active'),
      ('TR-02','TR-02','Monumento–Sangandaan Connector','Jeepney','Monumento','Sangandaan','Samson Rd • Sangandaan Area (Caloocan)','Point-to-Point',45,45,'Active'),
      ('TR-03','TR-03','Deparo–Tala Service','Jeepney','Deparo','Tala','Deparo Rd • Tala Area (Caloocan)','Point-to-Point',40,40,'Active'),
      ('TR-04','TR-04','Bagong Silang–Camarin Loop','Jeepney','Bagong Silang','Camarin','Bagong Silang • Camarin (Caloocan)','Loop',55,55,'Active'),
      ('TR-05','TR-05','Grace Park–Monumento Shuttle','UV','Grace Park','Monumento','Grace Park • EDSA/Monumento (Caloocan)','Point-to-Point',30,30,'Active')");
  }

  // Add new bus routes for Caloocan City
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

  $conn->query("CREATE TABLE IF NOT EXISTS operator_portal_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(128) DEFAULT NULL,
    contact_info VARCHAR(64) DEFAULT NULL,
    association_name VARCHAR(128) DEFAULT NULL,
    status ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
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

  // Module 2: Franchise Management Tables
  $conn->query("CREATE TABLE IF NOT EXISTS franchise_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(50) NOT NULL UNIQUE,
    operator_id INT NOT NULL,
    coop_id INT,
    vehicle_count INT DEFAULT 1,
    status ENUM('Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','Rejected') DEFAULT 'Submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("UPDATE franchise_applications SET status='Submitted' WHERE status IN ('Pending','Under Review')");
  $statusCol = $conn->query("SHOW COLUMNS FROM franchise_applications LIKE 'status'");
  if ($statusCol && ($row = $statusCol->fetch_assoc())) {
    $t = (string) ($row['Type'] ?? '');
    if (stripos($t, 'lgu-endorsed') === false || stripos($t, 'ltfrb-approved') === false || stripos($t, 'approved') === false || stripos($t, 'submitted') === false) {
      $conn->query("ALTER TABLE franchise_applications MODIFY COLUMN status ENUM('Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','Rejected') DEFAULT 'Submitted'");
    }
  }
  $faCols = [
    'route_id' => "INT DEFAULT NULL",
    'route_ids' => "VARCHAR(255)",
    'fee_receipt_id' => "VARCHAR(100)",
    'representative_name' => "VARCHAR(150) DEFAULT NULL",
    'validation_notes' => "TEXT",
    'lptrp_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'coop_status' => "VARCHAR(50) DEFAULT 'Pending'",
    'endorsed_at' => "DATETIME DEFAULT NULL",
    'approved_at' => "DATETIME DEFAULT NULL",
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

  $conn->query("CREATE TABLE IF NOT EXISTS franchises (
    franchise_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    ltfrb_ref_no VARCHAR(80) DEFAULT NULL,
    decision_order_no VARCHAR(80) DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    status ENUM('Active','Expired','Revoked') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_franchise_app (application_id),
    INDEX idx_ltfrb_ref (ltfrb_ref_no),
    FOREIGN KEY (application_id) REFERENCES franchise_applications(application_id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS franchise_vehicles (
    fv_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_id INT DEFAULT NULL,
    franchise_ref_number VARCHAR(64) DEFAULT NULL,
    route_id INT DEFAULT NULL,
    vehicle_id INT NOT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_franchise_id (franchise_id),
    INDEX idx_franchise_ref (franchise_ref_number),
    INDEX idx_route_status (route_id, status),
    INDEX idx_vehicle_status (vehicle_id, status),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (franchise_id) REFERENCES franchises(franchise_id) ON DELETE SET NULL
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS compliance_cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(50) NOT NULL,
    violation_type VARCHAR(100),
    status ENUM('Open', 'Resolved', 'Escalated') DEFAULT 'Open',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");

  $conn->query("CREATE TABLE IF NOT EXISTS endorsement_records (
    endorsement_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    issued_date DATE,
    permit_number VARCHAR(50),
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
    doc_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
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
    $conn->query("ALTER TABLE operator_documents ADD COLUMN doc_status ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending'");
  }
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
    sts_equivalent_code VARCHAR(64) DEFAULT NULL
  ) ENGINE=InnoDB");
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
