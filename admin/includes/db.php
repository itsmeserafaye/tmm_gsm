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
  $colVeh = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='vehicles'");
  $haveInspStatus = false; $haveInspDate = false; $haveInspCert = false;
  if ($colVeh) {
    while ($c = $colVeh->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'inspection_status') $haveInspStatus = true;
      if (($c['COLUMN_NAME'] ?? '') === 'inspection_last_date') $haveInspDate = true;
      if (($c['COLUMN_NAME'] ?? '') === 'inspection_cert_ref') $haveInspCert = true;
    }
  }
  if (!$haveInspStatus) { $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_status VARCHAR(16) DEFAULT NULL"); }
  if (!$haveInspDate) { $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_last_date DATETIME DEFAULT NULL"); }
  if (!$haveInspCert) { $conn->query("ALTER TABLE vehicles ADD COLUMN inspection_cert_ref VARCHAR(64) DEFAULT NULL"); }
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
  $conn->query("ALTER TABLE franchise_applications MODIFY COLUMN status ENUM('Pending','Under Review','Endorsed','Rejected','Suspended') DEFAULT 'Pending'");

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
  $conn->query("CREATE TABLE IF NOT EXISTS terminal_permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    application_no VARCHAR(64),
    applicant_name VARCHAR(128),
    status ENUM('Pending','Pending Payment','Active','Expired','Revoked') DEFAULT 'Pending',
    conditions TEXT,
    issue_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (terminal_id),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $colTP = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='terminal_permits'");
  $havePayRec = false; $havePayVer = false; $haveFeeAmt = false; $haveIdCol = false;
  $haveAppNo = false; $haveApplicant = false; $haveIssueDate = false; $haveExpiryDate = false; $haveCreatedAt = false; $haveTerminalId = false;
  if ($colTP) {
    while ($c = $colTP->fetch_assoc()) {
      if (($c['COLUMN_NAME'] ?? '') === 'payment_receipt') $havePayRec = true;
      if (($c['COLUMN_NAME'] ?? '') === 'payment_verified') $havePayVer = true;
      if (($c['COLUMN_NAME'] ?? '') === 'fee_amount') $haveFeeAmt = true;
      if (($c['COLUMN_NAME'] ?? '') === 'id') $haveIdCol = true;
      if (($c['COLUMN_NAME'] ?? '') === 'application_no') $haveAppNo = true;
      if (($c['COLUMN_NAME'] ?? '') === 'applicant_name') $haveApplicant = true;
      if (($c['COLUMN_NAME'] ?? '') === 'issue_date') $haveIssueDate = true;
      if (($c['COLUMN_NAME'] ?? '') === 'expiry_date') $haveExpiryDate = true;
      if (($c['COLUMN_NAME'] ?? '') === 'created_at') $haveCreatedAt = true;
      if (($c['COLUMN_NAME'] ?? '') === 'terminal_id') $haveTerminalId = true;
    }
  }
  if (!$haveIdCol) {
    $autoRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='terminal_permits' AND EXTRA LIKE '%auto_increment%'");
    $autoCol = null;
    if ($autoRes) { $row = $autoRes->fetch_assoc(); if ($row) { $autoCol = $row['COLUMN_NAME'] ?? null; } }
    $pkRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$name' AND TABLE_NAME='terminal_permits' AND CONSTRAINT_NAME='PRIMARY'");
    $pkCol = null;
    if ($pkRes) { $row2 = $pkRes->fetch_assoc(); if ($row2) { $pkCol = $row2['COLUMN_NAME'] ?? null; } }
    if ($autoCol) {
      $conn->query("ALTER TABLE terminal_permits ADD COLUMN id INT DEFAULT NULL");
      $conn->query("UPDATE terminal_permits SET id = `" . $autoCol . "`");
      $conn->query("ALTER TABLE terminal_permits ADD UNIQUE INDEX uniq_terminal_permits_id (id)");
    } elseif ($pkCol) {
      $conn->query("ALTER TABLE terminal_permits ADD COLUMN id INT DEFAULT NULL");
      $conn->query("UPDATE terminal_permits SET id = `" . $pkCol . "`");
      $conn->query("ALTER TABLE terminal_permits ADD UNIQUE INDEX uniq_terminal_permits_id (id)");
    } else {
      $conn->query("ALTER TABLE terminal_permits ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY");
    }
  }
  if (!$havePayRec) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN payment_receipt VARCHAR(64) DEFAULT NULL"); }
  if (!$havePayVer) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN payment_verified TINYINT(1) DEFAULT 0"); }
  if (!$haveFeeAmt) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN fee_amount DECIMAL(10,2) DEFAULT 0.00"); }
  if (!$haveAppNo) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN application_no VARCHAR(64) DEFAULT NULL"); }
  if (!$haveApplicant) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN applicant_name VARCHAR(128) DEFAULT NULL"); }
  if (!$haveIssueDate) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN issue_date DATE DEFAULT NULL"); }
  if (!$haveExpiryDate) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN expiry_date DATE DEFAULT NULL"); }
  if (!$haveCreatedAt) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); }
  if (!$haveTerminalId) { $conn->query("ALTER TABLE terminal_permits ADD COLUMN terminal_id INT DEFAULT NULL"); }
  $conn->query("ALTER TABLE terminal_permits MODIFY COLUMN status ENUM('Pending','Pending Payment','Active','Expired','Revoked') DEFAULT 'Pending'");
  $conn->query("CREATE TABLE IF NOT EXISTS terminal_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    vehicle_plate VARCHAR(32) NOT NULL,
    operator_id INT DEFAULT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    activity_type ENUM('Arrival','Departure') DEFAULT 'Arrival',
    remarks VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (terminal_id),
    INDEX (vehicle_plate),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_plate) REFERENCES vehicles(plate_number) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("ALTER TABLE terminal_logs MODIFY COLUMN activity_type ENUM('Arrival','Departure','Dispatch') DEFAULT 'Arrival'");
  // Optimization Indexes
  $idxLogs = $conn->query("SHOW INDEX FROM terminal_logs WHERE Key_name='idx_logs_time'");
  if ($idxLogs && $idxLogs->num_rows == 0) { $conn->query("ALTER TABLE terminal_logs ADD INDEX idx_logs_time (time_in)"); }
  
  $idxLogs2 = $conn->query("SHOW INDEX FROM terminal_logs WHERE Key_name='idx_logs_activity'");
  if ($idxLogs2 && $idxLogs2->num_rows == 0) { $conn->query("ALTER TABLE terminal_logs ADD INDEX idx_logs_activity (activity_type, time_in)"); }

  $idxTickets1 = $conn->query("SHOW INDEX FROM tickets WHERE Key_name='idx_tickets_date'");
  if ($idxTickets1 && $idxTickets1->num_rows == 0) { $conn->query("ALTER TABLE tickets ADD INDEX idx_tickets_date (date_issued)"); }

  $idxTickets2 = $conn->query("SHOW INDEX FROM tickets WHERE Key_name='idx_tickets_status'");
  if ($idxTickets2 && $idxTickets2->num_rows == 0) { $conn->query("ALTER TABLE tickets ADD INDEX idx_tickets_status (status)"); }

  $conn->query("CREATE TABLE IF NOT EXISTS route_cap_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id VARCHAR(64) NOT NULL,
    ts DATETIME NOT NULL,
    cap INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    confidence DOUBLE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(route_id, ts)
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS demand_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    route_id VARCHAR(64) NOT NULL,
    ts DATETIME NOT NULL,
    horizon_min INT NOT NULL,
    forecast_trips DOUBLE NOT NULL,
    lower_ci DOUBLE DEFAULT NULL,
    upper_ci DOUBLE DEFAULT NULL,
    model_version VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (terminal_id, route_id, ts),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  // Forecast & Cap Optimizations (Indexes)
  $idxForecastTs = $conn->query("SHOW INDEX FROM demand_forecasts WHERE Key_name='idx_forecast_ts_only'");
  if ($idxForecastTs && $idxForecastTs->num_rows == 0) { $conn->query("ALTER TABLE demand_forecasts ADD INDEX idx_forecast_ts_only (ts)"); }

  $idxCapTs = $conn->query("SHOW INDEX FROM route_cap_schedule WHERE Key_name='idx_cap_ts_only'");
  if ($idxCapTs && $idxCapTs->num_rows == 0) { $conn->query("ALTER TABLE route_cap_schedule ADD INDEX idx_cap_ts_only (ts)"); }

  $conn->query("CREATE TABLE IF NOT EXISTS demand_forecast_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type ENUM('train','forecast') NOT NULL,
    status ENUM('queued','running','succeeded','failed') NOT NULL DEFAULT 'queued',
    params_json TEXT,
    started_at DATETIME DEFAULT NULL,
    finished_at DATETIME DEFAULT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS parking_areas (
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
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS parking_transactions (
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
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS parking_violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parking_area_id INT NULL,
    vehicle_plate VARCHAR(20),
    violation_type VARCHAR(100) NOT NULL,
    penalty_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parking_area_id) REFERENCES parking_areas(id) ON DELETE SET NULL
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS weather_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    ts DATETIME NOT NULL,
    temp_c DOUBLE DEFAULT NULL,
    humidity DOUBLE DEFAULT NULL,
    rainfall_mm DOUBLE DEFAULT NULL,
    wind_kph DOUBLE DEFAULT NULL,
    weather_code VARCHAR(32) DEFAULT NULL,
    source VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_weather (terminal_id, ts),
    INDEX idx_weather_ts (terminal_id, ts),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS event_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    title VARCHAR(128) NOT NULL,
    ts_start DATETIME NOT NULL,
    ts_end DATETIME DEFAULT NULL,
    expected_attendance INT DEFAULT NULL,
    priority INT DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    source VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_ts (terminal_id, ts_start),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  $conn->query("CREATE TABLE IF NOT EXISTS traffic_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    terminal_id INT NOT NULL,
    route_id VARCHAR(64) DEFAULT NULL,
    ts DATETIME NOT NULL,
    avg_speed_kph DOUBLE DEFAULT NULL,
    congestion_index DOUBLE DEFAULT NULL,
    travel_time_min DOUBLE DEFAULT NULL,
    source VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_traffic (terminal_id, route_id, ts),
    INDEX idx_traffic_ts (terminal_id, route_id, ts),
    FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");
  return $conn;
}
?>
