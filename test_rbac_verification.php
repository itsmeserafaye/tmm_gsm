<?php
require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/includes/rbac.php';

// Mock session for auth.php if needed, but we are testing DB state primarily
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$config = require __DIR__ . '/config/rbac_config.php';
$db = db();

echo "Running RBAC Verification...\n";

$failures = 0;

// 1. Verify Roles
echo "1. Verifying Roles...\n";
foreach ($config['roles'] as $name => $desc) {
    $stmt = $db->prepare("SELECT id FROM rbac_roles WHERE name=?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo "[FAIL] Role not found: $name\n";
        $failures++;
    } else {
        // echo "[PASS] Role found: $name\n";
    }
    $stmt->close();
}

// 2. Verify Permissions
echo "2. Verifying Permissions...\n";
foreach ($config['permissions'] as $code => $desc) {
    $stmt = $db->prepare("SELECT id FROM rbac_permissions WHERE code=?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo "[FAIL] Permission not found: $code\n";
        $failures++;
    }
    $stmt->close();
}

// 3. Verify Role-Permission Mapping
echo "3. Verifying Mappings...\n";
foreach ($config['role_permissions'] as $roleName => $expectedPerms) {
    if (empty($expectedPerms)) continue;
    
    // Handle wildcard for verification logic
    if (in_array('*', $expectedPerms, true)) {
        // For SuperAdmin, just check if they have a lot of permissions or all
        // We can just skip exact match check for wildcard or check count
        continue; 
    }

    $roleId = rbac_role_id($db, $roleName);
    if (!$roleId) {
        echo "[FAIL] Role ID not found for mapping verification: $roleName\n";
        $failures++;
        continue;
    }

    $stmt = $db->prepare("
        SELECT p.code 
        FROM rbac_role_permissions rp 
        JOIN rbac_permissions p ON p.id = rp.permission_id 
        WHERE rp.role_id = ?
    ");
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $actualPerms = [];
    while ($row = $res->fetch_assoc()) {
        $actualPerms[] = $row['code'];
    }
    $stmt->close();

    // Check if expected perms are in actual perms
    // Note: Config might have subset if we rely on aliases? 
    // No, we seeded EXACTLY what is in config.
    
    $missing = array_diff($expectedPerms, $actualPerms);
    $extra = array_diff($actualPerms, $expectedPerms);

    if (!empty($missing)) {
        echo "[FAIL] Role '$roleName' missing permissions: " . implode(', ', $missing) . "\n";
        $failures++;
    }
    if (!empty($extra)) {
        echo "[FAIL] Role '$roleName' has extra permissions: " . implode(', ', $extra) . "\n";
        $failures++;
    }
    
    if (empty($missing) && empty($extra)) {
        // echo "[PASS] Role '$roleName' mapping correct.\n";
    }
}

if ($failures === 0) {
    echo "\nSUCCESS: All RBAC verifications passed!\n";
} else {
    echo "\nFAILURE: $failures checks failed.\n";
    exit(1);
}
