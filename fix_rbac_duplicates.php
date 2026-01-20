<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/rbac.php';

$db = db();
echo "<pre>";
echo "<h2>RBAC Duplicate Fixer</h2>";

// 1. Identify Duplicates
$res = $db->query("SELECT name, COUNT(*) as c, GROUP_CONCAT(id ORDER BY id ASC) as ids FROM rbac_roles GROUP BY name HAVING c > 1");

if ($res && $res->num_rows > 0) {
    echo "Found duplicate roles:\n";
    while ($row = $res->fetch_assoc()) {
        $name = $row['name'];
        $ids = explode(',', $row['ids']);
        $keepId = $ids[0]; // Keep the first one (usually the oldest)
        $removeIds = array_slice($ids, 1);
        
        echo "Role: <strong>$name</strong> (IDs: " . implode(', ', $ids) . ")\n";
        echo " -> Keeping ID: $keepId\n";
        echo " -> Merging & Removing IDs: " . implode(', ', $removeIds) . "\n";
        
        foreach ($removeIds as $badId) {
            // Reassign users
            $db->query("UPDATE IGNORE rbac_user_roles SET role_id = $keepId WHERE role_id = $badId");
            $db->query("DELETE FROM rbac_user_roles WHERE role_id = $badId"); // Delete remaining (duplicates)
            
            // Reassign permissions (or just delete, as we will re-seed)
            $db->query("DELETE FROM rbac_role_permissions WHERE role_id = $badId");
            
            // Delete Role
            $db->query("DELETE FROM rbac_roles WHERE id = $badId");
        }
        echo " -> Fixed.\n\n";
    }
} else {
    echo "No duplicate roles found by name.\n\n";
}

// 2. Ensure Unique Constraint
echo "Checking UNIQUE constraint on rbac_roles.name...\n";
$hasIndex = false;
$idxRes = $db->query("SHOW INDEX FROM rbac_roles WHERE Key_name = 'name' OR Column_name = 'name'");
while ($idx = $idxRes->fetch_assoc()) {
    if ($idx['Non_unique'] == 0) {
        $hasIndex = true;
    }
}

if (!$hasIndex) {
    echo "Adding UNIQUE constraint to rbac_roles(name)...\n";
    try {
        $db->query("ALTER TABLE rbac_roles ADD UNIQUE KEY uniq_name (name)");
        echo " -> Constraint added.\n";
    } catch (Exception $e) {
        echo " -> Failed to add constraint: " . $e->getMessage() . "\n";
    }
} else {
    echo "Constraint already exists.\n";
}

echo "Checking UNIQUE constraint on rbac_permissions.code...\n";
$hasIndexP = false;
$idxResP = $db->query("SHOW INDEX FROM rbac_permissions WHERE Key_name = 'code' OR Column_name = 'code'");
while ($idx = $idxResP->fetch_assoc()) {
    if ($idx['Non_unique'] == 0) {
        $hasIndexP = true;
    }
}

if (!$hasIndexP) {
    echo "Adding UNIQUE constraint to rbac_permissions(code)...\n";
    try {
        $db->query("ALTER TABLE rbac_permissions ADD UNIQUE KEY uniq_code (code)");
        echo " -> Constraint added.\n";
    } catch (Exception $e) {
        echo " -> Failed to add constraint: " . $e->getMessage() . "\n";
    }
} else {
    echo "Constraint already exists.\n";
}

echo "\n<h3>Done.</h3>";
echo "</pre>";
