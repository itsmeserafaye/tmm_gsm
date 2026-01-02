<?php
function db() {
  static $conn;
  if ($conn) return $conn;
  $host = '127.0.0.1';
  $user = 'root';
  $pass = '';
  $name = 'tmm';
  $conn = @new mysqli($host, $user, $pass);
  if ($conn->connect_error) { die('DB connect error'); }
  $conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $conn->select_db($name);
  $conn->set_charset('utf8mb4');
  $conn->query("CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32) UNIQUE,
    vehicle_type VARCHAR(64),
    operator_name VARCHAR(128),
    coop_name VARCHAR(128) DEFAULT NULL,
    franchise_id VARCHAR(64) DEFAULT NULL,
    route_id VARCHAR(64) DEFAULT NULL,
    status VARCHAR(32) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
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
  if ($colDocs) {
    while ($c = $colDocs->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'verified') { $haveVerifiedCol = true; break; }
    }
  }
  if (!$haveVerifiedCol) { $conn->query("ALTER TABLE documents ADD COLUMN verified TINYINT(1) DEFAULT 0"); }
  $conn->query("CREATE TABLE IF NOT EXISTS ownership_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(32),
    new_operator_name VARCHAR(128),
    deed_ref VARCHAR(128),
    transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
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
  $conn->query("CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id VARCHAR(64) UNIQUE,
    route_name VARCHAR(128),
    max_vehicle_limit INT DEFAULT 50,
    status VARCHAR(32) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $check = $conn->query("SELECT COUNT(*) AS c FROM routes");
  if ($check && ($check->fetch_assoc()['c'] ?? 0) == 0) {
    $conn->query("INSERT INTO routes(route_id, route_name, max_vehicle_limit, status) VALUES
      ('R-12','Central Loop',50,'Active'),
      ('R-08','East Corridor',30,'Active'),
      ('R-05','North Spur',40,'Active')");
  }

  // Module 2: Franchise Management Tables
  $conn->query("CREATE TABLE IF NOT EXISTS franchise_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    franchise_ref_number VARCHAR(50) NOT NULL UNIQUE,
    operator_id INT NOT NULL,
    coop_id INT,
    vehicle_count INT DEFAULT 1,
    status ENUM('Pending', 'Under Review', 'Endorsed', 'Rejected') DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
  $conn->query("CREATE TABLE IF NOT EXISTS operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(128) UNIQUE,
    contact_info VARCHAR(128) DEFAULT NULL,
    coop_name VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
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
      ('DTS', 'Disregarding Traffic Signs', 500.00, 'General', 'STS-DTS'),
      ('NLZ', 'No Loading/Unloading Zone', 1000.00, 'Loading', 'STS-NLZ')");
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
    franchise_id VARCHAR(64) DEFAULT NULL,
    coop_id INT DEFAULT NULL,
    driver_name VARCHAR(128) DEFAULT NULL,
    issued_by VARCHAR(128) DEFAULT NULL,
    issued_by_badge VARCHAR(64) DEFAULT NULL,
    officer_id INT DEFAULT NULL,
    status ENUM('Pending','Validated','Settled','Escalated') DEFAULT 'Pending',
    fine_amount DECIMAL(10,2) DEFAULT 0,
    due_date DATE DEFAULT NULL,
    payment_ref VARCHAR(64) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    INDEX (vehicle_plate),
    FOREIGN KEY (violation_code) REFERENCES violation_types(violation_code)
  ) ENGINE=InnoDB");
  // Ensure new audit columns exist (safe migrations)
  $colCheck = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='tickets'");
  $haveBadge = false; $haveOfficer = false;
  if ($colCheck) {
    while ($c = $colCheck->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'issued_by_badge') $haveBadge = true;
      if (($c['COLUMN_NAME'] ?? '') === 'officer_id') $haveOfficer = true;
    }
  }
  if (!$haveBadge) { $conn->query("ALTER TABLE tickets ADD COLUMN issued_by_badge VARCHAR(64) DEFAULT NULL"); }
  if (!$haveOfficer) { 
    $conn->query("ALTER TABLE tickets ADD COLUMN officer_id INT DEFAULT NULL");
    // Add FK if table exists
    $conn->query("ALTER TABLE tickets ADD INDEX idx_officer_id (officer_id)");
    $conn->query("ALTER TABLE tickets ADD CONSTRAINT fk_tickets_officer FOREIGN KEY (officer_id) REFERENCES officers(officer_id)");
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
    scheduled_at DATETIME NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    inspector_id INT DEFAULT NULL,
    status ENUM('Scheduled','Completed','Cancelled','Rescheduled','Pending Verification') DEFAULT 'Scheduled',
    cr_verified TINYINT(1) DEFAULT 0,
    or_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (plate_number),
    FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES officers(officer_id)
  ) ENGINE=InnoDB");
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
  return $conn;
}
?> 
