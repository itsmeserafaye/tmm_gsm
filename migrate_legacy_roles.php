<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/rbac.php';

$db = db();

$map = [
    'Admin' => 'Admin / Transport Officer',
    'Treasurer' => 'Treasurer / Cashier',
    'ParkingStaff' => 'Terminal Manager',
];

echo "Migrating legacy roles...\n";

foreach ($map as $oldName => $newName) {
    // Get Old ID
    $oldId = rbac_role_id($db, $oldName);
    // Get New ID
    $newId = rbac_role_id($db, $newName);

    if ($oldId && $newId) {
        echo "Migrating '$oldName' ($oldId) -> '$newName' ($newId)...\n";
        
        // 1. Move users
        // Update user_roles where role is oldId to newId
        // Handle constraint violation if user already has newId (IGNORE)
        $db->query("UPDATE IGNORE rbac_user_roles SET role_id = $newId WHERE role_id = $oldId");
        
        // Delete any remaining oldId assignments (duplicates that were ignored)
        $db->query("DELETE FROM rbac_user_roles WHERE role_id = $oldId");
        
        // 2. Delete old role
        $db->query("DELETE FROM rbac_roles WHERE id = $oldId");
        echo "Done.\n";
    } elseif ($oldId && !$newId) {
        // Just rename if new one doesn't exist? 
        // No, config should have created new one.
        echo "Warning: New role '$newName' not found. Renaming '$oldName'...\n";
        $stmt = $db->prepare("UPDATE rbac_roles SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $newName, $oldId);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Skipping '$oldName' (not found or already migrated).\n";
    }
}

echo "Migration complete.\n";
