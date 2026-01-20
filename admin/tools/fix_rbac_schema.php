<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../../includes/rbac.php';

header('Content-Type: text/plain');

try {
    $db = db();
    echo "Starting RBAC Schema Repair...\n\n";

    // 1. Fix rbac_roles: Deduplicate and Add Unique Constraint
    echo "1. Deduplicating Roles...\n";
    $db->query("CREATE TEMPORARY TABLE tmp_role_keep SELECT name, MIN(id) AS keep_id FROM rbac_roles GROUP BY name");
    
    // Move references to the 'keep' ID
    $db->query("UPDATE rbac_user_roles ur JOIN rbac_roles r ON r.id = ur.role_id JOIN tmp_role_keep k ON k.name = r.name SET ur.role_id = k.keep_id");
    $db->query("UPDATE rbac_role_permissions rp JOIN rbac_roles r ON r.id = rp.role_id JOIN tmp_role_keep k ON k.name = r.name SET rp.role_id = k.keep_id");
    
    // Delete duplicates
    $db->query("DELETE r FROM rbac_roles r JOIN tmp_role_keep k ON k.name = r.name WHERE r.id <> k.keep_id");
    $db->query("DROP TEMPORARY TABLE tmp_role_keep");
    echo "   Done.\n";

    echo "2. Enforcing Unique Roles...\n";
    // Drop index if exists to avoid error, then add
    try { $db->query("ALTER TABLE rbac_roles DROP INDEX uniq_rbac_roles_name"); } catch (Exception $e) {}
    $db->query("ALTER TABLE rbac_roles ADD UNIQUE KEY uniq_rbac_roles_name (name)");
    
    // Ensure ID is Auto Increment (if not already)
    // We try to add PK if missing
    $res = $db->query("SHOW INDEX FROM rbac_roles WHERE Key_name = 'PRIMARY'");
    if ($res->num_rows === 0) {
         $db->query("ALTER TABLE rbac_roles ADD PRIMARY KEY (id)");
    }
    $db->query("ALTER TABLE rbac_roles MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    echo "   Done.\n";

    // 2. Fix rbac_user_roles: Deduplicate and Add Primary Key
    echo "3. Cleaning User Roles...\n";
    // Remove exact duplicates
    $db->query("DELETE ur1 FROM rbac_user_roles ur1 JOIN rbac_user_roles ur2 ON ur1.user_id = ur2.user_id AND ur1.role_id = ur2.role_id AND ur1.assigned_at > ur2.assigned_at");
    
    // Add Primary Key (composite)
    try { $db->query("ALTER TABLE rbac_user_roles DROP PRIMARY KEY"); } catch (Exception $e) {}
    $db->query("ALTER TABLE rbac_user_roles ADD PRIMARY KEY (user_id, role_id)");
    echo "   Done.\n";

    // 3. Fix rbac_users: Ensure ID is Auto Increment
    echo "4. Fixing Users Table...\n";
    $res = $db->query("SHOW INDEX FROM rbac_users WHERE Key_name = 'PRIMARY'");
    if ($res->num_rows === 0) {
         $db->query("ALTER TABLE rbac_users ADD PRIMARY KEY (id)");
    }
    $db->query("ALTER TABLE rbac_users MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT");
    echo "   Done.\n";

    echo "\nSUCCESS: Database schema repaired and strict constraints enforced.";

} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage();
    if ($db->error) {
        echo "\nMySQL Error: " . $db->error;
    }
}
