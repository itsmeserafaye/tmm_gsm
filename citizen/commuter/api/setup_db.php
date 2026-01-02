<?php
// C:\xampp\htdocs\tmm_gsm\citizen\commuter\api\setup_db.php
require_once 'db.php';

$conn = db();

$sql = "CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(64) NOT NULL UNIQUE,
  `vehicle_plate` varchar(32) DEFAULT NULL,
  `complaint_type` varchar(100) NOT NULL,
  `description` text,
  `media_path` varchar(255) DEFAULT NULL,
  `status` enum('Submitted', 'Under Review', 'Resolved') DEFAULT 'Submitted',
  `ai_category` varchar(100) DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'complaints' created successfully or already exists.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>