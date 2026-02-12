<?php
// admin/tools/fix_deletion.php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Database Deletion Constraints</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Fix Database Deletion Constraints</h1>
    <p>This script adds <code>ON DELETE CASCADE</code> to foreign keys, allowing you to delete records without constraint errors.</p>
    <hr>

<?php
// Try to connect with standard XAMPP defaults or env
$host = 'localhost';
$user = 'root';
$pass = '';
$db_names = ['tmm_tmm', 'tmm']; 

$conn = null;

// 1. Try environment variables if available
if (getenv('TMM_DB_HOST')) {
    $host = getenv('TMM_DB_HOST');
    $user = getenv('TMM_DB_USER');
    $pass = getenv('TMM_DB_PASS');
    $db_names = [getenv('TMM_DB_NAME')];
}

// 2. Connect
foreach ($db_names as $name) {
    try {
        $mysqli = @new mysqli($host, $user, $pass, $name);
        if (!$mysqli->connect_error) {
            $conn = $mysqli;
            echo "<p class='success'>✅ Connected to database: <strong>$name</strong></p>";
            break;
        }
    } catch (Exception $e) {}
}

if (!$conn) {
    echo "<p class='error'>❌ Failed to connect to database. Please check configuration.</p>";
    die("</body></html>");
}

function execute_query($conn, $sql, $description) {
    echo "<p>Processing: <strong>$description</strong>... ";
    try {
        if ($conn->query($sql) === TRUE) {
            echo "<span class='success'>✅ OK</span></p>";
        } else {
            $err = $conn->error;
            if (strpos($err, "check that column/key exists") !== false || strpos($err, "Can't DROP") !== false) {
                 echo "<span class='warning'>⚠️ (Skipped/Not Found)</span></p>";
            } else {
                 echo "<span class='error'>❌ Error: $err</span></p>";
            }
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Exception: " . $e->getMessage() . "</span></p>";
    }
}

function drop_fk($conn, $table, $constraint) {
    $conn->query("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
}

// --- FIXES ---

// 1. user_profiles
if ($conn->query("SHOW TABLES LIKE 'user_profiles'")->num_rows > 0) {
    drop_fk($conn, 'user_profiles', 'fk_user_profiles_user');
    drop_fk($conn, 'user_profiles', 'user_profiles_ibfk_1'); 
    
    $sql = "ALTER TABLE `user_profiles` 
            ADD CONSTRAINT `fk_user_profiles_user` 
            FOREIGN KEY (`user_id`) REFERENCES `rbac_users` (`id`) 
            ON DELETE CASCADE ON UPDATE CASCADE";
    execute_query($conn, $sql, "Fix user_profiles FK (Cascade Delete)");
} else {
    echo "<p class='warning'>⚠️ Table 'user_profiles' does not exist.</p>";
}

// 2. operator_portal_user_plates
if ($conn->query("SHOW TABLES LIKE 'operator_portal_user_plates'")->num_rows > 0) {
    drop_fk($conn, 'operator_portal_user_plates', 'fk_operator_portal_user_plates_user');
    drop_fk($conn, 'operator_portal_user_plates', 'fk_operator_portal_user_plates_plate');
    
    $sql1 = "ALTER TABLE `operator_portal_user_plates` 
             ADD CONSTRAINT `fk_operator_portal_user_plates_user` 
             FOREIGN KEY (`user_id`) REFERENCES `operator_portal_users` (`id`) 
             ON DELETE CASCADE";
    execute_query($conn, $sql1, "Fix operator_portal_user_plates (User -> Cascade)");
    
    $sql2 = "ALTER TABLE `operator_portal_user_plates` 
             ADD CONSTRAINT `fk_operator_portal_user_plates_plate` 
             FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) 
             ON DELETE CASCADE";
    execute_query($conn, $sql2, "Fix operator_portal_user_plates (Plate -> Cascade)");
}

// 3. ownership_transfers
if ($conn->query("SHOW TABLES LIKE 'ownership_transfers'")->num_rows > 0) {
    // Drop all FKs to be safe
    $result = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'ownership_transfers' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
    while($row = $result->fetch_assoc()) {
        drop_fk($conn, 'ownership_transfers', $row['CONSTRAINT_NAME']);
    }

    $sql = "ALTER TABLE `ownership_transfers` 
            ADD CONSTRAINT `fk_ownership_transfers_plate` 
            FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) 
            ON DELETE CASCADE";
    execute_query($conn, $sql, "Fix ownership_transfers FK");
}

// 4. terminal_assignments
if ($conn->query("SHOW TABLES LIKE 'terminal_assignments'")->num_rows > 0) {
    $result = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'terminal_assignments' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
    while($row = $result->fetch_assoc()) {
        drop_fk($conn, 'terminal_assignments', $row['CONSTRAINT_NAME']);
    }
    
    $sql = "ALTER TABLE `terminal_assignments` 
            ADD CONSTRAINT `fk_terminal_assignments_plate` 
            FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) 
            ON DELETE CASCADE";
    execute_query($conn, $sql, "Fix terminal_assignments FK");
}

// 5. Inspection Tables
// inspection_schedules
if ($conn->query("SHOW TABLES LIKE 'inspection_schedules'")->num_rows > 0) {
    drop_fk($conn, 'inspection_schedules', 'inspection_schedules_ibfk_1'); // plate
    drop_fk($conn, 'inspection_schedules', 'inspection_schedules_ibfk_2'); // vehicle
    drop_fk($conn, 'inspection_schedules', 'fk_inspection_schedules_plate');
    drop_fk($conn, 'inspection_schedules', 'fk_inspection_schedules_vehicle');
    
    $sql1 = "ALTER TABLE `inspection_schedules` 
             ADD CONSTRAINT `fk_inspection_schedules_plate` 
             FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) 
             ON DELETE CASCADE";
    execute_query($conn, $sql1, "Fix inspection_schedules (Plate -> Cascade)");

    $sql2 = "ALTER TABLE `inspection_schedules` 
             ADD CONSTRAINT `fk_inspection_schedules_vehicle` 
             FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) 
             ON DELETE SET NULL";
    execute_query($conn, $sql2, "Fix inspection_schedules (Vehicle -> Set Null)");
}

// inspection_results
if ($conn->query("SHOW TABLES LIKE 'inspection_results'")->num_rows > 0) {
    drop_fk($conn, 'inspection_results', 'inspection_results_ibfk_1');
    drop_fk($conn, 'inspection_results', 'fk_inspection_results_schedule');
    
    $sql = "ALTER TABLE `inspection_results` 
            ADD CONSTRAINT `fk_inspection_results_schedule` 
            FOREIGN KEY (`schedule_id`) REFERENCES `inspection_schedules` (`schedule_id`) 
            ON DELETE CASCADE";
    execute_query($conn, $sql, "Fix inspection_results (Schedule -> Cascade)");
}

// inspection_certificates
if ($conn->query("SHOW TABLES LIKE 'inspection_certificates'")->num_rows > 0) {
    drop_fk($conn, 'inspection_certificates', 'inspection_certificates_ibfk_1');
    drop_fk($conn, 'inspection_certificates', 'fk_inspection_certificates_schedule');
    
    $sql = "ALTER TABLE `inspection_certificates` 
            ADD CONSTRAINT `fk_inspection_certificates_schedule` 
            FOREIGN KEY (`schedule_id`) REFERENCES `inspection_schedules` (`schedule_id`) 
            ON DELETE CASCADE";
    execute_query($conn, $sql, "Fix inspection_certificates (Schedule -> Cascade)");
}

// 6. terminal_inspections
$sql = "CREATE TABLE IF NOT EXISTS `terminal_inspections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `terminal_id` INT NOT NULL,
  `inspector_name` VARCHAR(128) DEFAULT NULL,
  `inspection_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `location` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'Passed',
  `remarks` TEXT DEFAULT NULL,
  INDEX (`terminal_id`),
  CONSTRAINT `fk_terminal_inspections_terminal` FOREIGN KEY (`terminal_id`) REFERENCES `terminals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB";
execute_query($conn, $sql, "Ensure terminal_inspections table exists");

echo "<hr><p><strong>Done!</strong> Please check if you can now delete the records.</p>";
?>
</body>
</html>