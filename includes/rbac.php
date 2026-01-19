<?php
if (php_sapi_name() !== 'cli' && function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

function rbac_ensure_schema(mysqli $db) {
  $db->query("CREATE TABLE IF NOT EXISTS rbac_users (
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
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT NOT NULL PRIMARY KEY,
    birthdate DATE DEFAULT NULL,
    mobile VARCHAR(32) DEFAULT NULL,
    address_line VARCHAR(255) DEFAULT NULL,
    house_number VARCHAR(64) DEFAULT NULL,
    street VARCHAR(128) DEFAULT NULL,
    barangay VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES rbac_users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS rbac_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS rbac_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(128) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS rbac_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_rbac_user_roles_user FOREIGN KEY (user_id) REFERENCES rbac_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_rbac_user_roles_role FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS rbac_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rbac_role_permissions_role FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rbac_role_permissions_perm FOREIGN KEY (permission_id) REFERENCES rbac_permissions(id) ON DELETE CASCADE
  ) ENGINE=InnoDB");

  $db->query("CREATE TABLE IF NOT EXISTS rbac_login_audit (
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
  ) ENGINE=InnoDB");

  rbac_seed_roles_permissions($db);
  rbac_seed_default_admin($db);
  rbac_migrate_commuter_role($db);
  rbac_ensure_compat_views($db);
}

function rbac_seed_roles_permissions(mysqli $db) {
  $roles = [
    ['SuperAdmin', 'City ICTO super administrator'],
    ['Admin / Transport Officer', 'Full access to modules (except user management)'],
    ['Franchise Officer', 'Handles franchise application & endorsement'],
    ['Encoder', 'Data entry only (Module 1 write, Module 2 apply)'],
    ['Inspector', 'Handles inspection scheduling & execution'],
    ['Traffic Enforcer', 'Issues tickets (traffic enforcement)'],
    ['Treasurer / Cashier', 'Payment & settlement (Module 3 + parking fees)'],
    ['Terminal Manager', 'Handles terminals & parking operations'],
    ['Viewer', 'Read-only (module read access)'],
    ['Commuter', 'Citizen portal account (no admin access)'],
  ];
  foreach ($roles as $r) {
    $stmt = $db->prepare("INSERT IGNORE INTO rbac_roles(name, description) VALUES(?, ?)");
    if ($stmt) {
      $stmt->bind_param('ss', $r[0], $r[1]);
      $stmt->execute();
      $stmt->close();
    }
  }

  $perms = [
    ['dashboard.view', 'View dashboards'],
    ['analytics.view', 'View analytics'],
    ['analytics.train', 'Create/update demand observation logs'],
    ['reports.export', 'Export reports'],
    ['settings.manage', 'Manage system settings'],
    ['users.manage', 'Manage RBAC users and roles'],

    ['module1.read', 'Module 1 read access'],
    ['module1.write', 'Module 1 write access'],
    ['module1.delete', 'Module 1 delete access'],
    ['module1.link_vehicle', 'Link vehicle to operator'],
    ['module1.route_manage', 'Manage routes and terminals'],

    ['module2.read', 'Module 2 read access'],
    ['module2.apply', 'Submit franchise applications'],
    ['module2.endorse', 'Endorse franchise applications'],
    ['module2.approve', 'Approve franchise applications'],
    ['module2.history', 'View franchise history and audit'],

    ['module3.read', 'Module 3 read access'],
    ['module3.issue', 'Issue tickets'],
    ['module3.settle', 'Settle ticket payments'],
    ['module3.analytics', 'Module 3 analytics'],

    ['module4.read', 'Module 4 read access'],
    ['module4.schedule', 'Schedule inspections'],
    ['module4.inspect', 'Conduct inspections'],
    ['module4.certify', 'Certify inspections'],

    ['module5.read', 'Module 5 read access'],
    ['module5.manage_terminal', 'Manage terminals and parking'],
    ['module5.assign_vehicle', 'Assign vehicles to terminals'],
    ['module5.parking_fees', 'Record parking fees'],

    ['module1.view', 'Legacy: View Module 1 screens'],
    ['module2.view', 'Legacy: View Module 2 screens'],
    ['module3.view', 'Legacy: View Module 3 screens'],
    ['module4.view', 'Legacy: View Module 4 screens'],
    ['module5.view', 'Legacy: View Module 5 screens'],
    ['module1.vehicles.write', 'Legacy: Create/update vehicle records'],
    ['module1.routes.write', 'Legacy: Create/update route records'],
    ['module1.coops.write', 'Legacy: Create/update cooperative records'],
    ['module2.franchises.manage', 'Legacy: Process franchise applications'],
    ['module4.inspections.manage', 'Legacy: Manage inspections'],
    ['tickets.issue', 'Legacy: Issue citations'],
    ['tickets.validate', 'Legacy: Validate citations'],
    ['tickets.settle', 'Legacy: Settle citations'],
    ['parking.manage', 'Legacy: Manage parking'],
  ];
  foreach ($perms as $p) {
    $stmt = $db->prepare("INSERT IGNORE INTO rbac_permissions(code, description) VALUES(?, ?)");
    if ($stmt) {
      $stmt->bind_param('ss', $p[0], $p[1]);
      $stmt->execute();
      $stmt->close();
    }
  }

  $rolePerms = [
    'SuperAdmin' => array_map(function ($p) { return $p[0]; }, $perms),
    'Admin / Transport Officer' => [
      'dashboard.view','analytics.view','analytics.train','reports.export','settings.manage',
      'module1.read','module1.write','module1.delete','module1.link_vehicle','module1.route_manage',
      'module2.read','module2.apply','module2.endorse','module2.approve','module2.history',
      'module3.read','module3.issue','module3.settle','module3.analytics',
      'module4.read','module4.schedule','module4.inspect','module4.certify',
      'module5.read','module5.manage_terminal','module5.assign_vehicle','module5.parking_fees',
    ],
    'Franchise Officer' => [
      'dashboard.view','reports.export',
      'module1.read',
      'module2.read','module2.apply','module2.endorse','module2.history'
    ],
    'Encoder' => [
      'dashboard.view',
      'module1.read','module1.write','module1.link_vehicle',
      'module2.read','module2.apply',
    ],
    'Inspector' => [
      'dashboard.view',
      'module1.read',
      'module4.read','module4.schedule','module4.inspect','module4.certify',
    ],
    'Traffic Enforcer' => [
      'dashboard.view',
      'module1.read',
      'module3.read','module3.issue',
    ],
    'Treasurer / Cashier' => [
      'dashboard.view',
      'module1.read',
      'module3.read','module3.settle',
      'module5.read','module5.parking_fees',
    ],
    'Terminal Manager' => [
      'dashboard.view',
      'module1.read',
      'module5.read','module5.manage_terminal','module5.assign_vehicle',
    ],
    'Viewer' => [
      'dashboard.view',
      'module1.read','module2.read','module3.read','module4.read','module5.read'
    ],
  ];

  foreach ($rolePerms as $roleName => $permCodes) {
    $roleId = rbac_role_id($db, $roleName);
    if (!$roleId) continue;
    foreach ($permCodes as $code) {
      $permId = rbac_permission_id($db, $code);
      if (!$permId) continue;
      $stmt = $db->prepare("INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id) VALUES(?, ?)");
      if ($stmt) {
        $stmt->bind_param('ii', $roleId, $permId);
        $stmt->execute();
        $stmt->close();
      }
    }
  }

  $parkingRoleId = rbac_role_id($db, 'Terminal Manager');
  $ticketsIssuePermId = rbac_permission_id($db, 'tickets.issue');
  if ($parkingRoleId && $ticketsIssuePermId) {
    $stmtDel = $db->prepare("DELETE FROM rbac_role_permissions WHERE role_id=? AND permission_id=?");
    if ($stmtDel) {
      $stmtDel->bind_param('ii', $parkingRoleId, $ticketsIssuePermId);
      $stmtDel->execute();
      $stmtDel->close();
    }
  }

  $viewerRoleId = rbac_role_id($db, 'Viewer');
  $reportsExportPermId = rbac_permission_id($db, 'reports.export');
  if ($viewerRoleId && $reportsExportPermId) {
    $stmtDel = $db->prepare("DELETE FROM rbac_role_permissions WHERE role_id=? AND permission_id=?");
    if ($stmtDel) {
      $stmtDel->bind_param('ii', $viewerRoleId, $reportsExportPermId);
      $stmtDel->execute();
      $stmtDel->close();
    }
  }

  $legacyRoleMap = [
    'Admin' => 'Admin / Transport Officer',
    'Treasurer' => 'Treasurer / Cashier',
    'ParkingStaff' => 'Terminal Manager',
  ];
  foreach ($legacyRoleMap as $legacy => $target) {
    $legacyId = rbac_role_id($db, $legacy);
    if (!$legacyId) continue;
    foreach ($rolePerms[$target] ?? [] as $code) {
      $permId = rbac_permission_id($db, $code);
      if (!$permId) continue;
      $stmt = $db->prepare("INSERT IGNORE INTO rbac_role_permissions(role_id, permission_id) VALUES(?, ?)");
      if ($stmt) {
        $stmt->bind_param('ii', $legacyId, $permId);
        $stmt->execute();
        $stmt->close();
      }
    }
  }
}

function rbac_ensure_compat_views(mysqli $db): void {
  $schema = $db->real_escape_string((string)$db->query("SELECT DATABASE() AS d")->fetch_assoc()['d']);
  $check = function (string $name) use ($db, $schema): bool {
    $n = $db->real_escape_string($name);
    $res = $db->query("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='$schema' AND TABLE_NAME='$n' LIMIT 1");
    return (bool)($res && $res->fetch_row());
  };
  $createView = function (string $name, string $sql) use ($db, $check): void {
    if ($check($name)) return;
    $db->query("CREATE VIEW `$name` AS $sql");
  };
  $createView('roles', "SELECT id AS role_id, name AS role_name, description FROM rbac_roles");
  $createView('permissions', "SELECT id AS permission_id, code AS permission_key, description FROM rbac_permissions");
  $createView('role_permissions', "SELECT rp.role_id, rp.permission_id FROM rbac_role_permissions rp");
  $createView('users', "SELECT id AS user_id, email AS username, password_hash, status FROM rbac_users");
}

function rbac_seed_default_admin(mysqli $db) {
  $res = $db->query("SELECT COUNT(*) AS c FROM rbac_users");
  $c = 0;
  if ($res && ($row = $res->fetch_assoc())) $c = (int)($row['c'] ?? 0);
  if ($c > 0) return;

  $email = 'ict.admin@city.gov.ph';
  $pwd = 'Admin@12345';
  $hash = password_hash($pwd, PASSWORD_DEFAULT);
  $first = 'ICTO';
  $last = 'Administrator';
  $dept = 'City ICT Office';
  $pos = 'System Administrator';

  $stmt = $db->prepare("INSERT INTO rbac_users(email, password_hash, first_name, last_name, department, position_title, status) VALUES(?,?,?,?,?,?, 'Active')");
  if (!$stmt) return;
  $stmt->bind_param('ssssss', $email, $hash, $first, $last, $dept, $pos);
  $stmt->execute();
  $userId = (int)$stmt->insert_id;
  $stmt->close();

  if ($userId > 0) {
    $roleId = rbac_role_id($db, 'SuperAdmin');
    if ($roleId) {
      $stmt2 = $db->prepare("INSERT IGNORE INTO rbac_user_roles(user_id, role_id) VALUES(?, ?)");
      if ($stmt2) {
        $stmt2->bind_param('ii', $userId, $roleId);
        $stmt2->execute();
        $stmt2->close();
      }
    }
  }
}

function rbac_role_id(mysqli $db, string $name) {
  $stmt = $db->prepare("SELECT id FROM rbac_roles WHERE name=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $name);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ? (int)$row['id'] : null;
}

function rbac_permission_id(mysqli $db, string $code) {
  $stmt = $db->prepare("SELECT id FROM rbac_permissions WHERE code=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $code);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ? (int)$row['id'] : null;
}

function rbac_get_user_by_email(mysqli $db, string $email) {
  $stmt = $db->prepare("SELECT * FROM rbac_users WHERE email=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

function rbac_get_user_roles(mysqli $db, int $userId) {
  $stmt = $db->prepare("SELECT r.name FROM rbac_user_roles ur JOIN rbac_roles r ON r.id=ur.role_id WHERE ur.user_id=?");
  if (!$stmt) return [];
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $rs = $stmt->get_result();
  $out = [];
  while ($rs && ($row = $rs->fetch_assoc())) {
    if (!empty($row['name'])) $out[] = $row['name'];
  }
  $stmt->close();
  return $out;
}

function rbac_get_user_permissions(mysqli $db, int $userId) {
  $stmt = $db->prepare("
    SELECT DISTINCT p.code
    FROM rbac_user_roles ur
    JOIN rbac_role_permissions rp ON rp.role_id=ur.role_id
    JOIN rbac_permissions p ON p.id=rp.permission_id
    WHERE ur.user_id=?
  ");
  if (!$stmt) return [];
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $rs = $stmt->get_result();
  $out = [];
  while ($rs && ($row = $rs->fetch_assoc())) {
    if (!empty($row['code'])) $out[] = $row['code'];
  }
  $stmt->close();
  return $out;
}

function rbac_primary_role(array $roles) {
  $priority = ['SuperAdmin', 'Admin', 'Encoder', 'Inspector', 'Treasurer', 'ParkingStaff', 'Viewer', 'Commuter'];
  foreach ($priority as $p) {
    if (in_array($p, $roles, true)) return $p;
  }
  return $roles[0] ?? 'Viewer';
}

function rbac_migrate_commuter_role(mysqli $db): void {
  $commuterId = rbac_role_id($db, 'Commuter');
  $viewerId = rbac_role_id($db, 'Viewer');
  if (!$commuterId || !$viewerId) return;

  $db->query("
    INSERT IGNORE INTO rbac_user_roles(user_id, role_id)
    SELECT ur.user_id, $commuterId
    FROM rbac_user_roles ur
    JOIN user_profiles p ON p.user_id=ur.user_id
    WHERE ur.role_id = $viewerId
  ");

  $db->query("
    DELETE urv
    FROM rbac_user_roles urv
    JOIN rbac_user_roles urc ON urc.user_id=urv.user_id AND urc.role_id=$commuterId
    WHERE urv.role_id=$viewerId
  ");
}

function rbac_write_login_audit(mysqli $db, ?int $userId, ?string $email, bool $ok) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
  $okInt = $ok ? 1 : 0;
  $stmt = $db->prepare("INSERT INTO rbac_login_audit(user_id, email, ok, ip_address, user_agent) VALUES(?,?,?,?,?)");
  if (!$stmt) return;
  $stmt->bind_param('isiss', $userId, $email, $okInt, $ip, $ua);
  $stmt->execute();
  $stmt->close();
}
