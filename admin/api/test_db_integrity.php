<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain');

try {
    $db = db();
    echo "Database Connected.\n";
    
    $tables = ['rbac_users', 'rbac_roles', 'rbac_user_roles'];
    
    foreach ($tables as $table) {
        echo "\n--- Checking table: $table ---\n";
        $res = $db->query("SHOW CREATE TABLE $table");
        if ($res && $row = $res->fetch_assoc()) {
            echo "Table exists.\n";
            echo $row['Create Table'] . "\n";
            
            // Check for AUTO_INCREMENT
            if ($table === 'rbac_users' || $table === 'rbac_roles') {
                if (stripos($row['Create Table'], 'AUTO_INCREMENT') !== false) {
                    echo "AUTO_INCREMENT detected.\n";
                } else {
                    echo "WARNING: AUTO_INCREMENT MISSING!\n";
                }
            }
        } else {
            echo "ERROR: Table $table DOES NOT EXIST.\n";
        }
    }
    
    // Check if we can insert a dummy user
    echo "\n--- Testing Write Permission (rbac_users) ---\n";
    $testEmail = 'test_integrity_' . time() . '@example.com';
    $db->query("INSERT INTO rbac_users (email, first_name, last_name, password_hash) VALUES ('$testEmail', 'Test', 'Integrity', 'hash')");
    $id = $db->insert_id;
    if ($id > 0) {
        echo "Insert successful. New ID: $id\n";
        $db->query("DELETE FROM rbac_users WHERE id = $id");
        echo "Delete successful.\n";
    } else {
        echo "ERROR: Insert failed or ID not returned. Error: " . $db->error . "\n";
    }

} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
