-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 29, 2025 at 11:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tmm`
--

-- --------------------------------------------------------

--
-- Table structure for table `compliance_cases`
--

CREATE TABLE `compliance_cases` (
  `case_id` int(11) NOT NULL,
  `franchise_ref_number` varchar(50) NOT NULL,
  `violation_type` varchar(100) DEFAULT NULL,
  `status` enum('Open','Resolved','Escalated') DEFAULT 'Open',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_summary`
--

CREATE TABLE `compliance_summary` (
  `vehicle_plate` varchar(32) NOT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `violation_count` int(11) DEFAULT 0,
  `last_violation_date` date DEFAULT NULL,
  `compliance_status` varchar(32) DEFAULT 'Normal',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coops`
--

CREATE TABLE `coops` (
  `id` int(11) NOT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `chairperson_name` varchar(128) DEFAULT NULL,
  `lgu_approval_number` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `type` varchar(16) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(64) DEFAULT 'admin',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `endorsement_records`
--

CREATE TABLE `endorsement_records` (
  `endorsement_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `issued_date` date DEFAULT NULL,
  `permit_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence`
--

CREATE TABLE `evidence` (
  `evidence_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(32) DEFAULT NULL,
  `uploaded_by` varchar(64) DEFAULT 'officer',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `franchise_applications`
--

CREATE TABLE `franchise_applications` (
  `application_id` int(11) NOT NULL,
  `franchise_ref_number` varchar(50) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `coop_id` int(11) DEFAULT NULL,
  `vehicle_count` int(11) DEFAULT 1,
  `status` enum('Pending','Under Review','Endorsed','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `officer_id` int(11) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `role` varchar(64) DEFAULT NULL,
  `badge_no` varchar(64) DEFAULT NULL,
  `station_id` varchar(64) DEFAULT NULL,
  `active_status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `name`, `role`, `badge_no`, `station_id`, `active_status`, `created_at`) VALUES
(1, 'Officer Dela Cruz', 'Enforcer', 'MMDA-001', 'Station A', 1, '2025-12-29 10:34:46'),
(2, 'Officer Santos', 'Enforcer', 'MMDA-002', 'Station B', 1, '2025-12-29 10:34:46'),
(3, 'Officer Reyes', 'Supervisor', 'MMDA-010', 'HQ', 1, '2025-12-29 10:34:46'),
(4, 'Officer Garcia', 'Enforcer', 'MMDA-003', 'Station C', 1, '2025-12-29 10:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL,
  `full_name` varchar(128) DEFAULT NULL,
  `contact_info` varchar(128) DEFAULT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ownership_transfers`
--

CREATE TABLE `ownership_transfers` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `new_operator_name` varchar(128) DEFAULT NULL,
  `deed_ref` varchar(128) DEFAULT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_records`
--

CREATE TABLE `payment_records` (
  `payment_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `date_paid` datetime DEFAULT current_timestamp(),
  `receipt_ref` varchar(64) DEFAULT NULL,
  `verified_by_treasury` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `route_name` varchar(128) DEFAULT NULL,
  `max_vehicle_limit` int(11) DEFAULT 50,
  `status` varchar(32) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `route_id`, `route_name`, `max_vehicle_limit`, `status`, `created_at`, `updated_at`) VALUES
(1, 'R-12', 'Central Loop', 50, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(2, 'R-08', 'East Corridor', 30, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(3, 'R-05', 'North Spur', 40, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(4, 'R-99', 'Test Route', 25, 'Active', '2025-12-29 04:20:12', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_assignments`
--

CREATE TABLE `terminal_assignments` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `terminal_name` varchar(128) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'Authorized',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `terminal_assignments`
--

INSERT INTO `terminal_assignments` (`id`, `plate_number`, `route_id`, `terminal_name`, `status`, `assigned_at`) VALUES
(1, 'ABC-1234', 'R-99', 'West Yard', 'Authorized', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL,
  `ticket_number` varchar(32) DEFAULT NULL,
  `date_issued` datetime DEFAULT current_timestamp(),
  `violation_code` varchar(32) DEFAULT NULL,
  `vehicle_plate` varchar(32) DEFAULT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `coop_id` int(11) DEFAULT NULL,
  `driver_name` varchar(128) DEFAULT NULL,
  `issued_by` varchar(128) DEFAULT NULL,
  `status` enum('Pending','Validated','Settled','Escalated') DEFAULT 'Pending',
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `payment_ref` varchar(64) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `vehicle_type` varchar(64) DEFAULT NULL,
  `operator_name` varchar(128) DEFAULT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `plate_number`, `vehicle_type`, `operator_name`, `coop_name`, `franchise_id`, `route_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ABC-1234', 'Jeepney', 'Juan Dela Cruz', NULL, 'FR-000987', 'R-99', 'Active', '2025-12-29 04:09:02', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

CREATE TABLE `violation_types` (
  `violation_code` varchar(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `category` varchar(64) DEFAULT NULL,
  `sts_equivalent_code` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`violation_code`, `description`, `fine_amount`, `category`, `sts_equivalent_code`) VALUES
('DTS', 'Disregarding Traffic Signs', 500.00, 'General', 'STS-DTS'),
('IP', 'Illegal Parking', 1000.00, 'Parking', 'STS-IP'),
('NLZ', 'No Loading/Unloading Zone', 1000.00, 'Loading', 'STS-NLZ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `compliance_cases`
--
ALTER TABLE `compliance_cases`
  ADD PRIMARY KEY (`case_id`);

--
-- Indexes for table `compliance_summary`
--
ALTER TABLE `compliance_summary`
  ADD PRIMARY KEY (`vehicle_plate`);

--
-- Indexes for table `coops`
--
ALTER TABLE `coops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coop_name` (`coop_name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plate_number` (`plate_number`);

--
-- Indexes for table `endorsement_records`
--
ALTER TABLE `endorsement_records`
  ADD PRIMARY KEY (`endorsement_id`);

--
-- Indexes for table `evidence`
--
ALTER TABLE `evidence`
  ADD PRIMARY KEY (`evidence_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `franchise_ref_number` (`franchise_ref_number`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`officer_id`),
  ADD UNIQUE KEY `badge_no` (`badge_no`);

--
-- Indexes for table `operators`
--
ALTER TABLE `operators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `full_name` (`full_name`);

--
-- Indexes for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plate_number` (`plate_number`);

--
-- Indexes for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `route_id` (`route_id`);

--
-- Indexes for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_plate` (`plate_number`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `vehicle_plate` (`vehicle_plate`),
  ADD KEY `violation_code` (`violation_code`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD PRIMARY KEY (`violation_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `compliance_cases`
--
ALTER TABLE `compliance_cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coops`
--
ALTER TABLE `coops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `endorsement_records`
--
ALTER TABLE `endorsement_records`
  MODIFY `endorsement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence`
--
ALTER TABLE `evidence`
  MODIFY `evidence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `officer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `operators`
--
ALTER TABLE `operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `evidence`
--
ALTER TABLE `evidence`
  ADD CONSTRAINT `evidence_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD CONSTRAINT `ownership_transfers_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  ADD CONSTRAINT `terminal_assignments_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`violation_code`) REFERENCES `violation_types` (`violation_code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
