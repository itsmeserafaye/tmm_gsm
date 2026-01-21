SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS operators (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(128) UNIQUE,
  contact_info VARCHAR(128) DEFAULT NULL,
  coop_name VARCHAR(128) DEFAULT NULL,
  operator_type ENUM('Individual','Cooperative','Corporation') DEFAULT 'Individual',
  registered_name VARCHAR(255) DEFAULT NULL,
  name VARCHAR(255) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  contact_no VARCHAR(64) DEFAULT NULL,
  email VARCHAR(128) DEFAULT NULL,
  status ENUM('Pending','Approved','Inactive') DEFAULT 'Approved',
  verification_status ENUM('Draft','Verified','Inactive') NOT NULL DEFAULT 'Draft',
  workflow_status ENUM('Draft','Pending Validation','Active','Returned','Rejected','Inactive') NOT NULL DEFAULT 'Draft',
  workflow_remarks TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plate_number VARCHAR(32) UNIQUE,
  vehicle_type VARCHAR(64),
  operator_id INT DEFAULT NULL,
  operator_name VARCHAR(128),
  coop_name VARCHAR(128) DEFAULT NULL,
  franchise_id VARCHAR(64) DEFAULT NULL,
  route_id VARCHAR(64) DEFAULT NULL,
  engine_no VARCHAR(100) DEFAULT NULL,
  chassis_no VARCHAR(100) DEFAULT NULL,
  make VARCHAR(100) DEFAULT NULL,
  model VARCHAR(100) DEFAULT NULL,
  year_model VARCHAR(8) DEFAULT NULL,
  fuel_type VARCHAR(64) DEFAULT NULL,
  inspection_status VARCHAR(20) DEFAULT 'Pending',
  inspection_cert_ref VARCHAR(64) DEFAULT NULL,
  inspection_passed_at DATETIME DEFAULT NULL,
  record_status ENUM('Encoded','Linked','Archived') NOT NULL DEFAULT 'Encoded',
  status VARCHAR(32) DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_operator_id (operator_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS routes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  route_id VARCHAR(64) UNIQUE,
  route_name VARCHAR(128),
  max_vehicle_limit INT DEFAULT 50,
  status VARCHAR(32) DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  route_code VARCHAR(64) DEFAULT NULL,
  origin VARCHAR(100) DEFAULT NULL,
  destination VARCHAR(100) DEFAULT NULL,
  structure ENUM('Loop','Point-to-Point') DEFAULT NULL,
  distance_km DECIMAL(10,2) DEFAULT NULL,
  authorized_units INT DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS terminal_assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  plate_number VARCHAR(32),
  route_id VARCHAR(64),
  terminal_name VARCHAR(128),
  status VARCHAR(32) DEFAULT 'Authorized',
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  terminal_id INT DEFAULT NULL,
  vehicle_id INT DEFAULT NULL,
  UNIQUE KEY uniq_plate (plate_number),
  UNIQUE KEY uniq_vehicle (vehicle_id),
  INDEX (route_id),
  FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plate_number VARCHAR(32),
  type VARCHAR(16),
  file_path VARCHAR(255),
  uploaded_by VARCHAR(64) DEFAULT 'admin',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified TINYINT(1) DEFAULT 0,
  application_id INT NULL,
  INDEX (plate_number),
  FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ownership_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plate_number VARCHAR(32),
  new_operator_name VARCHAR(128),
  deed_ref VARCHAR(128),
  transferred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (plate_number),
  FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS terminals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  location VARCHAR(255) DEFAULT NULL,
  capacity INT DEFAULT 0,
  type VARCHAR(50) DEFAULT 'Terminal',
  city VARCHAR(100) DEFAULT NULL,
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parking_slots (
  slot_id INT AUTO_INCREMENT PRIMARY KEY,
  terminal_id INT NOT NULL,
  slot_no VARCHAR(64) NOT NULL,
  status ENUM('Free','Occupied') NOT NULL DEFAULT 'Free',
  UNIQUE KEY uniq_terminal_slot (terminal_id, slot_no),
  INDEX (terminal_id),
  FOREIGN KEY (terminal_id) REFERENCES terminals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parking_payments (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operator_documents (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_documents (
  doc_id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  doc_type ENUM('ORCR','Insurance','Emission','Others') DEFAULT 'Others',
  file_path VARCHAR(255) NOT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_by INT DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  INDEX (vehicle_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operator_portal_users (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operator_portal_user_plates (
  user_id INT NOT NULL,
  plate_number VARCHAR(32) NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, plate_number),
  UNIQUE KEY uniq_plate (plate_number),
  CONSTRAINT fk_operator_portal_user_plates_user FOREIGN KEY (user_id) REFERENCES operator_portal_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_operator_portal_user_plates_plate FOREIGN KEY (plate_number) REFERENCES vehicles(plate_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS operator_portal_applications (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS franchise_applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  franchise_ref_number VARCHAR(50) NOT NULL UNIQUE,
  operator_id INT NOT NULL,
  coop_id INT,
  vehicle_count INT DEFAULT 1,
  status ENUM('Submitted','Pending','Under Review','Endorsed','LGU-Endorsed','Approved','LTFRB-Approved','Rejected') DEFAULT 'Submitted',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  route_id INT DEFAULT NULL,
  route_ids VARCHAR(255),
  fee_receipt_id VARCHAR(100),
  representative_name VARCHAR(150) DEFAULT NULL,
  validation_notes TEXT,
  lptrp_status VARCHAR(50) DEFAULT 'Pending',
  coop_status VARCHAR(50) DEFAULT 'Pending',
  endorsed_at DATETIME DEFAULT NULL,
  approved_at DATETIME DEFAULT NULL,
  remarks TEXT,
  assigned_officer_id INT,
  INDEX idx_franchise_route_id (route_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS franchises (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS officers (
  officer_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128),
  role VARCHAR(64),
  badge_no VARCHAR(64) UNIQUE,
  station_id VARCHAR(64) DEFAULT NULL,
  active_status TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS violation_types (
  violation_code VARCHAR(32) PRIMARY KEY,
  description VARCHAR(255),
  fine_amount DECIMAL(10,2) DEFAULT 0,
  category VARCHAR(64) DEFAULT NULL,
  sts_equivalent_code VARCHAR(64) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tickets (
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
  INDEX idx_officer_id (officer_id),
  FOREIGN KEY (violation_code) REFERENCES violation_types(violation_code),
  CONSTRAINT fk_tickets_officer FOREIGN KEY (officer_id) REFERENCES officers(officer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_records (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  date_paid DATETIME DEFAULT CURRENT_TIMESTAMP,
  receipt_ref VARCHAR(64),
  verified_by_treasury TINYINT(1) DEFAULT 0,
  payment_channel VARCHAR(64) DEFAULT NULL,
  external_payment_id VARCHAR(128) DEFAULT NULL,
  exported_to_treasury TINYINT(1) DEFAULT 0,
  exported_at DATETIME DEFAULT NULL,
  INDEX (ticket_id),
  FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  or_no VARCHAR(64) NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (ticket_id),
  FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS treasury_payment_requests (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_module VARCHAR(32) NOT NULL,
  filter_period VARCHAR(16) DEFAULT '',
  filter_status VARCHAR(16) DEFAULT '',
  filter_officer_id INT DEFAULT NULL,
  filter_q VARCHAR(128) DEFAULT '',
  ticket_count INT NOT NULL DEFAULT 0,
  last_ticket_date DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_schedules (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_registrations (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  orcr_no VARCHAR(64) NOT NULL,
  orcr_date DATE NOT NULL,
  registration_status ENUM('Pending','Recorded','Registered','Expired') DEFAULT 'Registered',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (vehicle_id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspections (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_results (
  result_id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  overall_status ENUM('Passed','Failed','Pending','For Reinspection') DEFAULT 'Pending',
  remarks VARCHAR(255) DEFAULT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (schedule_id),
  FOREIGN KEY (schedule_id) REFERENCES inspection_schedules(schedule_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_checklist_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  result_id INT NOT NULL,
  item_code VARCHAR(32),
  item_label VARCHAR(128),
  status ENUM('Pass','Fail','NA') DEFAULT 'NA',
  INDEX (result_id),
  FOREIGN KEY (result_id) REFERENCES inspection_results(result_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_certificates (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inspection_photos (
  photo_id INT AUTO_INCREMENT PRIMARY KEY,
  result_id INT NOT NULL,
  file_path VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (result_id),
  FOREIGN KEY (result_id) REFERENCES inspection_results(result_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS puv_demand_observations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_type ENUM('terminal','route','parking_area') NOT NULL,
  area_ref VARCHAR(128) NOT NULL,
  observed_at DATETIME NOT NULL,
  demand_count INT NOT NULL DEFAULT 0,
  source VARCHAR(32) NOT NULL DEFAULT 'system',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_area_hour (area_type, area_ref, observed_at),
  INDEX idx_area_time (area_type, area_ref, observed_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;
