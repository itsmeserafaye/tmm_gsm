<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

try {
    $db = db();
    require_role(['SuperAdmin']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $db->begin_transaction();

    // 1. Deduplicate Roles (Keep MIN id, remove others)
    $db->query("CREATE TEMPORARY TABLE tmp_role_keep SELECT name, MIN(id) AS keep_id FROM rbac_roles GROUP BY name");
    
    // Update references in user_roles
    $db->query("UPDATE rbac_user_roles ur JOIN rbac_roles r ON r.id = ur.role_id JOIN tmp_role_keep k ON k.name = r.name SET ur.role_id = k.keep_id");
    
    // Update references in role_permissions
    $db->query("UPDATE rbac_role_permissions rp JOIN rbac_roles r ON r.id = rp.role_id JOIN tmp_role_keep k ON k.name = r.name SET rp.role_id = k.keep_id");
    
    // Delete duplicate roles
    $db->query("DELETE r FROM rbac_roles r JOIN tmp_role_keep k ON k.name = r.name WHERE r.id <> k.keep_id");
    $db->query("DROP TEMPORARY TABLE tmp_role_keep");

    // 2. Add Unique Constraint to Roles
    // Check if exists first to avoid error
    $check = $db->query("SHOW INDEX FROM rbac_roles WHERE Key_name = 'uniq_rbac_roles_name'");
    if ($check->num_rows === 0) {
        $db->query("ALTER TABLE rbac_roles ADD UNIQUE KEY uniq_rbac_roles_name (name)");
    }

    // 3. Deduplicate User Roles (Exact duplicates)
    $db->query("DELETE ur1 FROM rbac_user_roles ur1 JOIN rbac_user_roles ur2 ON ur1.user_id = ur2.user_id AND ur1.role_id = ur2.role_id AND ur1.assigned_at > ur2.assigned_at");

    // 4. Add Primary Key to User Roles (if missing)
    $checkPK = $db->query("SHOW INDEX FROM rbac_user_roles WHERE Key_name = 'PRIMARY'");
    if ($checkPK->num_rows === 0) {
        $db->query("ALTER TABLE rbac_user_roles ADD PRIMARY KEY (user_id, role_id)");
    }
    // Ensure assigned_at column exists (older schemas may not have it)
    $checkAssignedAt = $db->query("SHOW COLUMNS FROM rbac_user_roles LIKE 'assigned_at'");
    if (!$checkAssignedAt || $checkAssignedAt->num_rows === 0) {
        $db->query("ALTER TABLE rbac_user_roles ADD COLUMN assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    // 5. Ensure Auto Increment on IDs
    $db->query("ALTER TABLE rbac_roles MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    $db->query("ALTER TABLE rbac_users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    $db->query("ALTER TABLE rbac_permissions MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");

    // 6. Deduplicate Permissions (Keep MIN id, remove others)
    $db->query("CREATE TEMPORARY TABLE tmp_perm_keep SELECT code, MIN(id) AS keep_id FROM rbac_permissions GROUP BY code");
    $db->query("UPDATE rbac_role_permissions rp JOIN rbac_permissions p ON p.id = rp.permission_id JOIN tmp_perm_keep k ON k.code = p.code SET rp.permission_id = k.keep_id");
    $db->query("DELETE p FROM rbac_permissions p JOIN tmp_perm_keep k ON k.code = p.code WHERE p.id <> k.keep_id");
    $db->query("DROP TEMPORARY TABLE tmp_perm_keep");

    $checkPermUniq = $db->query("SHOW INDEX FROM rbac_permissions WHERE Key_name = 'uniq_rbac_permissions_code'");
    if ($checkPermUniq && $checkPermUniq->num_rows === 0) {
        $db->query("ALTER TABLE rbac_permissions ADD UNIQUE KEY uniq_rbac_permissions_code (code)");
    }

    $db->commit();

    echo json_encode(['ok' => true, 'message' => 'Database repaired successfully. Duplicates removed.']);

} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
