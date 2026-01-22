SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS franchise_applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  operator_id INT NOT NULL,
  route_id INT DEFAULT NULL,
  requested_vehicle_count INT DEFAULT 1,
  representative_name VARCHAR(150) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'Submitted',
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  endorsed_at DATETIME DEFAULT NULL,
  remarks TEXT
) ENGINE=InnoDB;

SET @db := DATABASE();

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='requested_vehicle_count');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN requested_vehicle_count INT DEFAULT 1','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='representative_name');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN representative_name VARCHAR(150) DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='endorsed_at');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN endorsed_at DATETIME DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='remarks');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN remarks TEXT','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='route_id');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN route_id INT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='submitted_at');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='operator_id');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN operator_id INT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='status');
SET @sql := IF(@c=0,'ALTER TABLE franchise_applications ADD COLUMN status VARCHAR(50) DEFAULT ''Submitted''','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_vc := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchise_applications' AND COLUMN_NAME='vehicle_count');
SET @sql := IF(@has_vc>0,
  'UPDATE franchise_applications SET requested_vehicle_count=COALESCE(requested_vehicle_count, vehicle_count, 1) WHERE requested_vehicle_count IS NULL OR requested_vehicle_count=0',
  'UPDATE franchise_applications SET requested_vehicle_count=COALESCE(requested_vehicle_count, 1) WHERE requested_vehicle_count IS NULL OR requested_vehicle_count=0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS franchises (
  franchise_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  ltfrb_ref_no VARCHAR(80) DEFAULT NULL,
  decision_order_no VARCHAR(80) DEFAULT NULL,
  approved_units INT DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  franchise_status ENUM('Active','Expired','Revoked') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_franchise_app (application_id),
  INDEX idx_ltfrb_ref (ltfrb_ref_no)
) ENGINE=InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchises' AND COLUMN_NAME='approved_units');
SET @sql := IF(@c=0,'ALTER TABLE franchises ADD COLUMN approved_units INT DEFAULT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchises' AND COLUMN_NAME='franchise_status');
SET @sql := IF(@c=0,'ALTER TABLE franchises ADD COLUMN franchise_status ENUM(''Active'',''Expired'',''Revoked'') DEFAULT ''Active''','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_status := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='franchises' AND COLUMN_NAME='status');
SET @sql := IF(@has_status>0,
  'UPDATE franchises SET franchise_status=status WHERE (franchise_status IS NULL OR franchise_status='''') AND status IS NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS vehicle_registrations (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  orcr_no VARCHAR(64) NOT NULL,
  orcr_date DATE NOT NULL,
  registration_status ENUM('Recorded','Expired') DEFAULT 'Recorded',
  recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (vehicle_id)
) ENGINE=InnoDB;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle_registrations' AND COLUMN_NAME='recorded_at');
SET @sql := IF(@c=0,'ALTER TABLE vehicle_registrations ADD COLUMN recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_created_at := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle_registrations' AND COLUMN_NAME='created_at');
SET @sql := IF(@has_created_at>0,
  'UPDATE vehicle_registrations SET recorded_at=COALESCE(recorded_at, created_at, NOW()) WHERE recorded_at IS NULL',
  'UPDATE vehicle_registrations SET recorded_at=COALESCE(recorded_at, NOW()) WHERE recorded_at IS NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS=1;
