CREATE TABLE IF NOT EXISTS rbac_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  middle_name VARCHAR(80) DEFAULT NULL,
  suffix VARCHAR(16) DEFAULT NULL,
  employee_no VARCHAR(32) DEFAULT NULL,
  department VARCHAR(120) DEFAULT NULL,
  position_title VARCHAR(120) DEFAULT NULL,
  status ENUM('Active','Inactive','Locked') NOT NULL DEFAULT 'Active',
  last_login_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(128) NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_rbac_user_roles_user FOREIGN KEY (user_id) REFERENCES rbac_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rbac_user_roles_role FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rbac_role_permissions_role FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rbac_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES rbac_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rbac_login_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  email VARCHAR(190) DEFAULT NULL,
  ok TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(64) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_email (email),
  CONSTRAINT fk_rbac_login_audit_user FOREIGN KEY (user_id) REFERENCES rbac_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rbac_roles(name, description) VALUES
('SuperAdmin','City ICTO super administrator'),
('Admin','Transport Management Office administrator'),
('Encoder','Frontline encoder for vehicles, routes, records'),
('Inspector','Field inspector / traffic enforcer role'),
('Treasurer','City Treasurer cashier / payment verification role'),
('ParkingStaff','Parking operations staff role'),
('Viewer','Read-only executive / monitoring role');

INSERT IGNORE INTO rbac_permissions(code, description) VALUES
('dashboard.view','View dashboards and overview screens'),
('analytics.view','View analytics and AI insights'),
('module1.vehicles.write','Create and update vehicle records'),
('module1.routes.write','Create and update route records'),
('module1.coops.write','Create and update cooperative records'),
('module2.franchises.manage','Process franchise applications and endorsements'),
('module4.inspections.manage','Schedule and record inspections'),
('tickets.issue','Issue traffic/parking citations'),
('tickets.validate','Validate and escalate citations'),
('tickets.settle','Record payments and settle citations'),
('parking.manage','Manage parking areas, permits, and payments'),
('reports.export','Export reports'),
('settings.manage','Manage system settings');

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p
WHERE r.name = 'SuperAdmin';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','analytics.view','module1.vehicles.write','module1.routes.write','module1.coops.write',
  'module2.franchises.manage','module4.inspections.manage','tickets.validate','parking.manage','reports.export','settings.manage'
)
WHERE r.name = 'Admin';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','module1.vehicles.write','module1.routes.write','module1.coops.write','reports.export'
)
WHERE r.name = 'Encoder';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','analytics.view','module4.inspections.manage','tickets.issue','reports.export'
)
WHERE r.name = 'Inspector';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','tickets.settle','reports.export'
)
WHERE r.name = 'Treasurer';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','parking.manage','tickets.issue'
)
WHERE r.name = 'ParkingStaff';

INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id)
SELECT r.id, p.id
FROM rbac_roles r
JOIN rbac_permissions p ON p.code IN (
  'dashboard.view','analytics.view','reports.export'
)
WHERE r.name = 'Viewer';
