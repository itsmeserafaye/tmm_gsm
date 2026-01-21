START TRANSACTION;

ALTER TABLE operators
  ADD COLUMN IF NOT EXISTS registered_name VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS verification_status ENUM('Draft','Verified','Inactive') NOT NULL DEFAULT 'Draft';

UPDATE operators
SET registered_name = COALESCE(NULLIF(registered_name,''), NULLIF(name,''), full_name)
WHERE registered_name IS NULL OR registered_name='';

UPDATE operators
SET verification_status = CASE
  WHEN COALESCE(NULLIF(verification_status,''),'')<>'' THEN verification_status
  WHEN status='Approved' THEN 'Verified'
  WHEN status='Inactive' THEN 'Inactive'
  ELSE 'Draft'
END;

ALTER TABLE operator_documents
  MODIFY COLUMN doc_type ENUM('GovID','CDA','SEC','BarangayCert','Others') DEFAULT 'Others',
  ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS verified_by INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS verified_at DATETIME DEFAULT NULL;

UPDATE operator_documents
SET doc_type = CASE
  WHEN LOWER(COALESCE(doc_type,'')) IN ('id','govid','valid id','validid') THEN 'GovID'
  WHEN COALESCE(doc_type,'') IN ('GovID','CDA','SEC','BarangayCert','Others') THEN doc_type
  ELSE 'Others'
END;

CREATE TABLE IF NOT EXISTS trusted_devices (
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
) ENGINE=InnoDB;

ALTER TABLE trusted_devices
  ADD COLUMN IF NOT EXISTS user_agent_hash VARCHAR(64) DEFAULT NULL AFTER device_hash;

COMMIT;
